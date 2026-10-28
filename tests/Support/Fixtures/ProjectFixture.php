<?php

namespace Tests\Support\Fixtures;

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\Projects;

/**
 * TASK-032's browser fixture (TL-09–TL-12; ROUTE-010–ROUTE-013, ROUTE-151–ROUTE-153): for the run's company — whose
 * go-live the numbering fixture armed —, its PROJECT numbering started before this year unless the run needs it not
 * started (UXS-28), a client with its unit, address and PIC, goods and a service with a default selling price, the
 * signed-in account's grants on the company when it is not the Owner, and optionally a project created through
 * ROUTE-151's own action. Local only; synthetic names.
 */
final class ProjectFixture
{
    public static function create(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $user = $request->user();
        $year = substr(BusinessDate::today(), 0, 4);

        if ($request->boolean('numbered', true)) {
            Numbering::scheme($company, SequenceType::Project, "{$year}-01-01", 'YEAR', 'UJI/PRJ/{tahun}/{nomor:4}');
            Numbering::started($company, SequenceType::Project, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
        }

        if ($user !== null && $request->boolean('grant')) {
            Authorization::grantCompany($user, $company->id);

            foreach (['projects.view', 'projects.manage', 'finance.view', 'parties.manage'] as $capability) {
                Authorization::grant($user, $capability);
            }
        }

        $client = Projects::client();
        $client['organization']->forceFill(['name' => 'SMA Uji '.$company->code])->save();
        $goods = Projects::product();
        $goods['product']->forceFill(['name' => 'Buku Uji '.$company->code])->save();
        $service = Projects::product('SERVICE', '250000.00');
        $service['product']->forceFill(['name' => 'Pelatihan Uji '.$company->code])->save();
        $project = null;

        if ($request->boolean('project') && $user !== null) {
            $actor = $user->getAuthIdentifier();
            // A draft lacks its PIC, so it stays a DRAFT that "Buang Draf" may discard (SM:Project; L-37).
            Projects::create(is_int($actor) ? $actor : 0, $company, $client, [$goods, $service], null, $request->boolean('draft') ? ['pic' => null] : []);
            $project = Project::query()->where('company_id', $company->id)->latest('id')->first();
        }

        return response()->json([
            'code' => $company->code,
            'client' => $client['organization']->name,
            'unit' => $client['unit']->name,
            'contact' => $client['contact']->name,
            'goods' => $goods['product']->name,
            'service' => $service['product']->name,
            'project' => $project?->public_id,
            'number' => $project?->project_number,
        ]);
    }
}
