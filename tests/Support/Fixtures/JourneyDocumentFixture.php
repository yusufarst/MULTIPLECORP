<?php

namespace Tests\Support\Fixtures;

use App\Modules\DOC\Contracts\RenditionTemplate;
use App\Modules\DOC\Jobs\RenderDocument;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\FIN\Actions\IssueInvoice;
use App\Modules\FIN\Actions\SaveInvoiceDraft;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceSubjects;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Models\Project;
use App\Modules\Reporting\Exports\RequestExport;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;

/**
 * TL-09 (TASK-063): the starting preconditions and the stand-ins for absent background work of the critical journeys
 * CJ-14 and CJ-15 that no module fixture holds — never a step a journey walks. Synthetic data only; local only.
 *
 * - `apply` (CJ-14): PF-28's row bound lowered for the requests of a run that names one in `X-Fixture-Export-Bound` —
 *   the `exports.row_bound` setting P9's disk guard lowers the same way —, so the journey meets MSG-21 on a report of a
 *   handful of rows; a request without the header keeps the bound as configured, and a header never raises it.
 * - `olderReceivable` (CJ-14): over InvoiceFixture's company and confirmed project, a second invoice of Rp500,000 issued
 *   and billed through their own actions on a business date forty days back — within the year, whose numbering the
 *   invoice fixture started —, its due date that day, so the receivable ages past it (CALC-07).
 * - `retry` (CJ-15): one tick of JB-02's sweeper for the issued version's latest attempt, its JB-01 run here as the absent
 *   queue worker would: RenderDocument itself claims a failed attempt only once its backoff has passed (Renditions::isDue —
 *   a minute after a first attempt), adding the next attempt under the same issued version; before that nothing changes.
 * - `paper` (CJ-15): the text of the HTML the renderer renders for the document's issued version, from its payload alone
 *   through the layout's own template (RenditionTemplate), so the issued content is asserted against the facts.
 */
final class JourneyDocumentFixture
{
    public const BOUND_HEADER = 'X-Fixture-Export-Bound';

    private const OVERDUE_DAYS = 40;

    private const OLDER_VALUE = '500000';

    /** Called as the fixture routes load: a run's own lower row bound for its requests. */
    public static function apply(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $bound = request()->header(self::BOUND_HEADER);

        if (is_string($bound) && preg_match('/^[1-9]\d{0,5}$/', $bound) === 1) {
            config(['exports.row_bound' => min((int) $bound, RequestExport::bound())]);
        }
    }

    /** The company's second invoice, issued and billed forty days back (or on the year's first day), due that day. */
    public static function olderReceivable(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $actor = $request->user()?->getAuthIdentifier();
        $actor = is_int($actor) ? $actor : 0;
        $today = BusinessDate::today();
        $year = substr($today, 0, 4);
        Numbering::goLive("{$year}-01-01");
        $due = max(CarbonImmutable::parse($today)->subDays(self::OVERDUE_DAYS)->toDateString(), "{$year}-01-01");
        $project = Project::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $ref = new ProjectRef($project->id, $project->company_id, $project->public_id);
        $saved = app(SaveInvoiceDraft::class)->run((string) Str::uuid(), $actor, $ref, null, null, InvoiceWorld::valueOnly(self::OLDER_VALUE, $due), ['fixture' => 'older']);

        if ($saved->kind !== 'COMMITTED') {
            return response()->json(['error' => "draft {$saved->kind} {$saved->code}"], 500);
        }

        $invoice = Invoice::query()->findOrFail($saved->resultId);
        $issued = app(IssueInvoice::class)->run((string) Str::uuid(), $actor, $ref, $invoice, $invoice->lock_version, 'DOC-06', $due, false);

        if ($issued->kind !== 'COMMITTED') {
            return response()->json(['error' => "issue {$issued->kind} {$issued->code}"], 500);
        }

        $billed = InvoiceWorld::billInCommand($actor, $invoice->id, $due, $due);

        if ($billed !== 'COMMITTED') {
            return response()->json(['error' => "bill {$billed}"], 500);
        }

        return response()->json(['invoice' => $invoice->public_id, 'number' => InvoiceSubjects::number($invoice->id), 'due' => $due, 'today' => $today]);
    }

    /** A sweeper tick for the issued version's latest attempt, its render run here; the latest attempt's state and number. */
    public static function retry(Request $request): JsonResponse
    {
        $version = self::issued((string) $request->input('document'));
        $attempt = DocumentRendition::query()->where('document_version_id', $version->id)->orderByDesc('attempt_no')->firstOrFail();

        if (! in_array($attempt->state, ['FAILED', 'READY'], true)) {
            return response()->json(['error' => "the latest attempt is {$attempt->state}"], 409);
        }

        // The job's own claim decides: a failed attempt not yet due is left as it is and nothing is rendered (JB-01; JB-02).
        if ($attempt->state === 'FAILED') {
            dispatch_sync(new RenderDocument($attempt->id));
        }

        $latest = DocumentRendition::query()->where('document_version_id', $version->id)->orderByDesc('attempt_no')->firstOrFail();

        return response()->json(['state' => $latest->state, 'attempt' => $latest->attempt_no]);
    }

    /** The issued version's paper as the renderer reads it, as text: its tags and style dropped, its spaces collapsed. */
    public static function paper(Request $request): JsonResponse
    {
        $version = self::issued((string) $request->input('dokumen'));
        $directory = sys_get_temp_dir().'/journey-paper-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($directory, 0700);

        try {
            $html = app(RenditionTemplate::class)->html($version, $directory);
        } finally {
            File::deleteDirectory($directory);
        }

        $body = (string) preg_replace('#<(head|style|script)\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = html_entity_decode((string) preg_replace('/<[^>]*>/', ' ', $body), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return response()->json(['text' => trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text))]);
    }

    private static function issued(string $document): DocumentVersion
    {
        $id = DB::table('documents')->where('public_id', $document)->value('id');

        return DocumentVersion::query()->where('document_id', is_numeric($id) ? (int) $id : 0)->where('state', 'ISSUED')->firstOrFail();
    }
}
