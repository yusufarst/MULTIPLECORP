<?php

namespace Tests\Support\Fixtures;

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Actions\IssueQuotation;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\QuotationRecords;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\Projects;

/**
 * TASK-033's browser fixture (TL-09–TL-12; ROUTE-014, ROUTE-037, ROUTE-154–ROUTE-157; INT-012): for the run's company —
 * whose go-live the numbering fixture armed —, its PROJECT and DOC-01 numbering started before this year, a client and
 * `lines` goods, a project of them created through ROUTE-151's action and, by `stage`, its quotation drafted from its
 * lines with a validity and its prices (`draft`) or also issued (`sent`) through the quotation's own actions. Local only;
 * synthetic names.
 */
final class QuotationFixture
{
    public static function create(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $user = $request->user();
        $actor = $user?->getAuthIdentifier();
        $actor = is_int($actor) ? $actor : 0;
        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::goLive("{$year}-01-01");
        NumberingFixture::arm(Request::create('/', 'POST', ['go_live' => "{$year}-01-01"]));
        DocumentTypeFactory::ensure('DOC-01');

        foreach ([[SequenceType::Project, 'UJI/PRJ/{tahun}/{nomor:4}'], [SequenceType::Doc01, 'UJI/PNW/{tahun}/{nomor:4}']] as [$type, $template]) {
            Numbering::scheme($company, $type, "{$year}-01-01", 'YEAR', $template);
            Numbering::started($company, $type, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
        }

        $client = Projects::client();
        $client['organization']->forceFill(['name' => 'SMA Uji '.$company->code])->save();
        $products = [];

        foreach (range(1, max(1, min(30, $request->integer('lines', 1)))) as $number) {
            $goods = Projects::product();
            $goods['product']->forceFill(['name' => "Buku Uji {$company->code} {$number}"])->save();
            $products[] = $goods;
        }

        Authorization::forget();
        $created = Projects::create($actor, $company, $client, $products);

        if (! str_starts_with($created, 'COMMITTED')) {
            return response()->json(['error' => $created], 500);
        }

        $project = Project::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $stage = (string) $request->input('stage', 'none');

        if (in_array($stage, ['draft', 'sent'], true)) {
            $save = app(SaveQuotationDraft::class);
            $save->run((string) Str::uuid(), $actor, $project, null, null, null, [], []);
            $revision = QuotationRecords::latest($project->id) ?? throw new LogicException('The fixture drafted no quotation.');
            $lines = [];

            foreach (DB::table('quotation_revision_lines')->where('quotation_revision_id', $revision['id'])->orderBy('id')->get(['id', 'entered_qty', 'qty_base', 'description']) as $line) {
                $lines[(int) $line->id] = ['quantity' => (string) $line->entered_qty, 'quantity_base' => (string) $line->qty_base, 'price' => '20000', 'description' => (string) $line->description];
            }

            $outcome = $save->run((string) Str::uuid(), $actor, $project, $revision['public_id'], $revision['lock_version'], CarbonImmutable::parse(BusinessDate::today())->addDays(14)->toDateString(), $lines, ['fixture' => true]);

            if ($outcome->kind !== 'COMMITTED') {
                return response()->json(['error' => "draft {$outcome->kind} {$outcome->code}"], 500);
            }
        }

        if ($stage === 'sent') {
            $revision = QuotationRecords::latest($project->id) ?? throw new LogicException('The fixture drafted no quotation.');
            $outcome = app(IssueQuotation::class)->run((string) Str::uuid(), $actor, $project, $revision['public_id'], $revision['lock_version'], BusinessDate::today(), true);

            if ($outcome->kind !== 'COMMITTED') {
                return response()->json(['error' => "issue {$outcome->kind} {$outcome->code}"], 500);
            }
        }

        return response()->json(['code' => $company->code, 'client' => $client['organization']->name, 'project' => $project->public_id, 'number' => $project->project_number]);
    }
}
