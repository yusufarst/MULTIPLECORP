<?php

namespace Tests\Support\Fixtures;

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Actions\ConfirmWithoutQuotation;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\HandoverWorld;
use Tests\Support\Numbering;
use Tests\Support\Projects;

/**
 * TASK-047's browser fixture (TL-09–TL-12; ROUTE-033; ROUTE-208; ROUTE-209; J-FUL-04): for the run's company — whose go-live
 * the numbering fixture armed —, a service-only project of `lines` lines of 10 confirmed through ROUTE-158's own action, the BAST's
 * numbering started, a registered delivery proof to cite and — when asked — a handover of 4 already recorded through ROUTE-208's
 * action. Local only; synthetic names.
 */
final class HandoverFixture
{
    public static function create(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $actor = $request->user()?->getAuthIdentifier();
        $actor = is_int($actor) ? $actor : 0;
        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::goLive("{$year}-01-01");
        NumberingFixture::arm(Request::create('/', 'POST', ['go_live' => "{$year}-01-01"]));
        Numbering::scheme($company, SequenceType::Project, "{$year}-01-01", 'YEAR', 'UJI/PRJ/{tahun}/{nomor:4}');
        Numbering::started($company, SequenceType::Project, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
        Numbering::scheme($company, SequenceType::Doc08, "{$year}-01-01", 'YEAR', 'UJI/BAST/{tahun}/{nomor:4}');
        Numbering::started($company, SequenceType::Doc08, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));

        $client = Projects::client();
        $client['organization']->forceFill(['name' => 'SMA Uji '.$company->code])->save();
        $service = Projects::product('SERVICE', '250000.00');
        $service['product']->forceFill(['name' => 'Instalasi Uji '.$company->code])->save();
        Authorization::forget();
        $products = array_fill(0, max(1, (int) $request->input('lines', 1)), $service);
        $base = Projects::payload($company, $client, $products)['baris'];
        $lines = array_map(fn (array $line): array => [...$line, 'mode' => 'SERVICE', 'jumlah' => '10'], $base);
        $created = Projects::create($actor, $company, $client, $products, null, ['baris' => $lines]);

        if (! str_starts_with($created, 'COMMITTED')) {
            return response()->json(['error' => $created], 500);
        }

        $project = Project::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $order = EvidenceDocumentFactory::new()->createOne(['company_id' => $company->id, 'evidence_type' => 'CLIENT_SPK', 'declared_issuer' => 'SMA Uji '.$company->code]);
        $outcome = app(ConfirmWithoutQuotation::class)->run((string) Str::uuid(), $actor, $project, $project->lock_version, $order->id, $order->public_id, null, BusinessDate::today(), false);

        if ($outcome->kind !== 'COMMITTED') {
            return response()->json(['error' => "confirm {$outcome->kind} {$outcome->code}"], 500);
        }

        EvidenceDocumentFactory::new()->createOne(['company_id' => $company->id, 'evidence_type' => 'DELIVERY_PROOF', 'declared_issuer' => 'Bu Sari '.$company->code]);

        if ($request->boolean('recorded') && ($word = HandoverWorld::record($actor, $project->id, HandoverWorld::line($project->refresh()), '4')) !== 'COMMITTED') {
            return response()->json(['error' => "record {$word}"], 500);
        }

        return response()->json(['code' => $company->code, 'project' => $project->public_id, 'number' => $project->project_number, 'service' => $service['product']->name]);
    }
}
