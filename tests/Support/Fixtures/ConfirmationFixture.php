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
use Tests\Support\Numbering;
use Tests\Support\Projects;

/**
 * TASK-034's browser fixture (TL-09–TL-12; ROUTE-158–ROUTE-160; J-PRJ-02; J-FUL-01): for the run's company — whose
 * go-live the numbering fixture armed —, its PROJECT numbering started before this year, a mixed project of a warehouse
 * goods line and a service line created through ROUTE-151's action, the client's SPK registered as order evidence, and —
 * when asked — the project confirmed through ROUTE-158's own action. Local only; synthetic names.
 */
final class ConfirmationFixture
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

        $client = Projects::client();
        $client['organization']->forceFill(['name' => 'SMA Uji '.$company->code])->save();
        $goods = Projects::product();
        $goods['product']->forceFill(['name' => 'Buku Uji '.$company->code])->save();
        $service = Projects::product('SERVICE', '250000.00');
        $service['product']->forceFill(['name' => 'Pelatihan Uji '.$company->code])->save();
        Authorization::forget();
        $created = Projects::create($actor, $company, $client, [$goods, $service]);

        if (! str_starts_with($created, 'COMMITTED')) {
            return response()->json(['error' => $created], 500);
        }

        $project = Project::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $company->id, 'evidence_type' => 'CLIENT_SPK', 'declared_issuer' => 'SMA Uji '.$company->code]);

        if ($request->boolean('confirmed')) {
            $outcome = app(ConfirmWithoutQuotation::class)->run((string) Str::uuid(), $actor, $project, $project->lock_version, $evidence->id, $evidence->public_id, null, BusinessDate::today(), true);

            if ($outcome->kind !== 'COMMITTED') {
                return response()->json(['error' => "confirm {$outcome->kind} {$outcome->code}"], 500);
            }
        }

        return response()->json(['code' => $company->code, 'project' => $project->public_id, 'number' => $project->project_number, 'goods' => $goods['product']->name, 'service' => $service['product']->name]);
    }
}
