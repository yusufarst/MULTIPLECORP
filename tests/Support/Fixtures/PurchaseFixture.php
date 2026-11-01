<?php

namespace Tests\Support\Fixtures;

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Actions\ConfirmWithoutQuotation;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\Projects;
use Tests\Support\PurchaseWorld;

/**
 * TL-09–TL-12 (TASK-037): a company with its PURCHASE and PO numbering started, two suppliers, goods and a service, a tax treatment, a supplier bill, and a confirmed warehouse project with its shortage. Synthetic data only.
 */
final class PurchaseFixture
{
    public static function create(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $actor = $request->user()?->getAuthIdentifier();
        $actor = is_int($actor) ? $actor : 0;
        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::goLive("{$year}-01-01");
        NumberingFixture::arm(Request::create('/', 'POST', ['go_live' => "{$year}-01-01"]));
        $start = CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC');

        foreach ([[SequenceType::Project, 'PRJ'], [SequenceType::Purchase, 'BELI'], [SequenceType::Doc02, 'PO'], [SequenceType::Doc14, 'SP']] as [$type, $prefix]) {
            Numbering::scheme($company, $type, "{$year}-01-01", 'YEAR', "UJI/{$prefix}/{tahun}/{nomor:4}");
            Numbering::started($company, $type, $start);
        }

        foreach (['DOC-02', 'DOC-14'] as $layout) {
            DocumentTypeFactory::ensure($layout);
        }

        $tag = Str::upper(Str::random(5));
        $first = PurchaseWorld::supplier();
        $first->forceFill(['name' => "Pemasok Satu {$tag}"])->save();
        $second = PurchaseWorld::supplier();
        $second->forceFill(['name' => "Pemasok Dua {$tag}"])->save();
        $goods = Projects::product('GOODS', '250000.00');
        $goods['product']->forceFill(['name' => "Barang Uji {$tag}"])->save();
        $service = Projects::product('SERVICE', '250000.00');
        $service['product']->forceFill(['name' => "Jasa Uji {$tag}"])->save();
        $tax = PurchaseWorld::tax($company->id, 'INPUT_TO_ACQUISITION_COST', "{$year}-01-01");
        $bill = PurchaseWorld::bill($company->id, '500000');
        $client = Projects::client();
        Authorization::forget();
        $payload = Projects::payload($company, $client, [$goods]);
        $lines = array_map(fn (array $line): array => [...$line, 'mode' => 'WAREHOUSE', 'jumlah' => '10', 'harga' => '250000'], is_array($payload['baris']) ? $payload['baris'] : []);
        $created = Projects::create($actor, $company, $client, [$goods], null, ['baris' => $lines]);

        if (! str_starts_with($created, 'COMMITTED')) {
            return response()->json(['error' => $created], 500);
        }

        $project = Project::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $order = EvidenceDocumentFactory::new()->createOne(['company_id' => $company->id, 'evidence_type' => 'CLIENT_SPK', 'declared_issuer' => 'SMA Uji '.$company->code]);
        $outcome = app(ConfirmWithoutQuotation::class)->run((string) Str::uuid(), $actor, $project, $project->lock_version, $order->id, $order->public_id, null, BusinessDate::today(), false);

        if ($outcome->kind !== 'COMMITTED') {
            return response()->json(['error' => "confirm {$outcome->kind} {$outcome->code}"], 500);
        }

        $purchased = null;

        if ($request->boolean('purchased')) {
            $base = (int) $goods['base']->id;
            PurchaseWorld::save($actor, PurchaseWorld::entry($company->id, $first->id, $goods['product']->id, $base, BusinessDate::today()));
            $purchased = PurchaseWorld::latest($company->id)->public_id;
        }

        return response()->json([
            'purchase' => $purchased,
            'code' => $company->code,
            'project' => $project->public_id,
            'project_number' => $project->project_number,
            'supplier' => $first->name,
            'other_supplier' => $second->name,
            'goods' => $goods['product']->name,
            'service' => $service['product']->name,
            'base_unit' => $goods['base']->code,
            'tax' => $tax,
            'bill' => $bill,
        ]);
    }
}
