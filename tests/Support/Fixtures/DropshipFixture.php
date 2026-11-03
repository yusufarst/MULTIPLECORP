<?php

namespace Tests\Support\Fixtures;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Actions\ConfirmWithoutQuotation;
use App\Modules\PRJ\Models\Project;
use App\Modules\PUR\Data\PurchaseEntry;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\Projects;
use Tests\Support\PurchaseWorld;

/**
 * TL-09–TL-12 (TASK-046; J-FUL-03; CJ-01's drop-ship branch): for the signed-in account's company, a confirmed project whose
 * one line ships direct to the client, its purchase of that line from a supplier, a delivery proof of the company and
 * DOC-03's numbering; the account confirms, reverses and returns direct shipments and issues their *Surat Jalan*.
 * Synthetic data only.
 */
final class DropshipFixture
{
    public const CAPABILITIES = ['projects.view', 'dropship.confirm', 'fulfillment.correct', 'sales_return.record', 'cost.view', 'evidence.view', 'evidence.upload', 'documents.issue', 'stock.view'];

    public static function create(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();

        if (! $user instanceof User) {
            return response()->json(['error' => 'not signed in'], 401);
        }

        Authorization::grantCompany($user, $company->id);

        foreach (self::CAPABILITIES as $capability) {
            Authorization::grant($user, $capability);
        }

        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::goLive("{$year}-01-01");
        NumberingFixture::arm(Request::create('/', 'POST', ['go_live' => "{$year}-01-01"]));
        $start = CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC');

        foreach ([[SequenceType::Project, 'PRJ'], [SequenceType::Purchase, 'BELI'], [SequenceType::Doc03, 'SJ']] as [$type, $prefix]) {
            if (! DB::table('numbering_schemes')->where('company_id', $company->id)->where('sequence_type', $type->value)->exists()) {
                Numbering::scheme($company, $type, "{$year}-01-01", 'YEAR', "UJI/{$prefix}/{tahun}/{nomor:4}");
                Numbering::started($company, $type, $start);
            }
        }

        DocumentTypeFactory::ensure('DOC-03');
        $tag = Str::upper(Str::random(5));
        $supplier = PurchaseWorld::supplier();
        $supplier->forceFill(['name' => "Pemasok Langsung {$tag}"])->save();
        $goods = Projects::product('GOODS', '250000.00');
        $goods['product']->forceFill(['name' => "Barang Langsung {$tag}"])->save();
        $client = Projects::client();
        Authorization::forget();
        $payload = Projects::payload($company, $client, [$goods]);
        $lines = array_map(fn (array $line): array => [...$line, 'mode' => 'DROPSHIP', 'jumlah' => '5', 'harga' => '250000'], is_array($payload['baris']) ? $payload['baris'] : []);
        $created = Projects::create($user->id, $company, $client, [$goods], null, ['baris' => $lines]);

        if (! str_starts_with($created, 'COMMITTED')) {
            return response()->json(['error' => $created], 500);
        }

        $project = Project::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $order = EvidenceDocumentFactory::new()->createOne(['company_id' => $company->id, 'evidence_type' => 'CLIENT_SPK', 'declared_issuer' => 'SMA Uji '.$company->code]);
        $outcome = app(ConfirmWithoutQuotation::class)->run((string) Str::uuid(), $user->id, $project, $project->lock_version, $order->id, $order->public_id, null, BusinessDate::today(), false);

        if ($outcome->kind !== 'COMMITTED') {
            return response()->json(['error' => "confirm {$outcome->kind} {$outcome->code}"], 500);
        }

        $demand = DB::table('project_lines')->where('project_id', $project->id)->orderBy('line_no')->value('id');
        $base = (int) $goods['base']->id;
        $saved = PurchaseWorld::save($user->id, new PurchaseEntry($company->id, $project->id, $supplier->id, BusinessDate::today(), false, null,
            [['handle' => null, 'kind' => 'GOODS', 'product' => $goods['product']->id, 'line' => is_numeric($demand) ? (int) $demand : null, 'mode' => 'DROPSHIP', 'quantity' => '5', 'unit' => $base, 'price' => '200000', 'description' => 'Barang kirim langsung', 'taxes' => []]], []));

        if ($saved !== 'COMMITTED') {
            return response()->json(['error' => "purchase {$saved}"], 500);
        }

        $proof = EvidenceDocument::factory()->create(['company_id' => $company->id, 'evidence_scope' => 'COMPANY', 'evidence_type' => 'DELIVERY_PROOF', 'declared_issuer' => "Surat jalan pemasok {$tag}", 'issuer_reference' => "SJ-PMS-{$tag}"]);

        return response()->json([
            'project' => $project->public_id,
            'project_number' => $project->project_number,
            'purchase' => PurchaseWorld::latest($company->id)->public_id,
            'supplier' => $supplier->name,
            'proof' => $proof->public_id,
            'proof_label' => "Surat jalan pemasok {$tag} · SJ-PMS-{$tag}",
        ]);
    }
}
