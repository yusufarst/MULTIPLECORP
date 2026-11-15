<?php

namespace Tests\Support\Fixtures;

use App\Modules\DOC\Actions\SaveDocumentDraft;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Jobs\RenderDocument;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Queries\DocumentDetail;
use App\Modules\DOC\Services\MustacheTemplates;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\NumberingScheme;
use Carbon\CarbonImmutable;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\Documents\SyntheticSubjects;
use Throwable;

/**
 * TASK-029's browser fixtures (TL-09–TL-12; J-DOC-01; CJ-15): while a run has armed it, the synthetic subject provider
 * stands in for the issuing Tasks' (SyntheticSubjects); a draft prepared from a synthetic project through the real
 * ROUTE-221 action, its company's numbering of the type started, so PG-036 composes and issues it through the real
 * routes; and an issued version's rendition rendered at once or failed, as the absent queue worker would. Local only.
 */
final class DocumentFixture
{
    private const KEY = 'fixture-documents-armed';

    /** Called as the fixture routes load: the synthetic subjects for the requests of an armed run. */
    public static function apply(): void
    {
        try {
            $armed = Cache::get(self::KEY) === true;
        } catch (Throwable) {
            return;
        }

        if ($armed) {
            SyntheticSubjects::register();
        }
    }

    /** A draft of a type for a synthetic project of the company, its numbering started; the document's id. */
    public static function draft(Request $request): JsonResponse
    {
        Cache::put(self::KEY, true, 1800);
        SyntheticSubjects::register();
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $type = preg_match('/^DOC-(0[1-9]|1[0-4])$/', (string) $request->input('type')) === 1 ? (string) $request->input('type') : 'DOC-09';
        $year = substr(CarbonImmutable::now('Asia/Jakarta')->toDateString(), 0, 4);

        if (! DB::table('numbering_schemes')->where('company_id', $company->id)->where('sequence_type', $type)->exists()) {
            NumberingScheme::factory()->create(['company_id' => $company->id, 'sequence_type' => $type, 'period_granularity' => 'YEAR',
                'format_template' => 'UJI/'.substr($type, 4).'/{tahun}/{nomor:4}', 'effective_from' => "{$year}-01-01"]);
            $at = CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00', 'UTC');
            DB::table('audit_events')->insert(['occurred_at' => $at, 'recorded_at' => $at, 'action' => 'ORG.NumberingStarted.'.SequenceType::from($type)->auditSuffix(),
                'company_id' => $company->id, 'authority_class' => 'OWNER_ONLY', 'entity_type' => 'company', 'entity_id' => $company->id]);
        }

        $project = ProjectFactory::new()->createOne(['company_id' => $company->id]);
        $outcome = app(SaveDocumentDraft::class)->prepare((string) Str::uuid(), (int) $request->user()?->getAuthIdentifier(), new DocumentSubject($type, 'STANDARD', $company->id, ['project_id' => $project->id]));
        $document = DB::table('documents')->where('id', $outcome->resultId)->value('public_id');

        return response()->json(['document' => $document, 'outcome' => $outcome->kind]);
    }

    /**
     * TASK-065 (TEST_STRATEGY §9's tolerance; D-DS-23): the draft's paper exactly as PG-036 composes it for the viewer
     * (DocumentDetail), issued instead — unmarked and numbered, as DocumentIssuing composes it — and laid out by the
     * renderer's engine with its bundled assets: the page count and each block's box in millimetres. Nothing is written.
     */
    public static function layout(Request $request): JsonResponse
    {
        $document = Document::query()->where('public_id', (string) $request->query('dokumen'))->firstOrFail();
        $detail = app(DocumentDetail::class)->of((int) $request->user()?->getAuthIdentifier(), $document);
        $draft = is_array($detail['draft'] ?? null) ? $detail['draft'] : [];
        $paper = is_array($draft['paper'] ?? null) ? $draft['paper'] : abort(404, 'The document has no draft paper.');
        $year = substr(CarbonImmutable::now('Asia/Jakarta')->toDateString(), 0, 4);
        $issued = [...$paper, 'draft' => false, 'number' => 'UJI/'.substr($document->document_type_code, 4)."/{$year}/0001"];
        $html = MustacheTemplates::document(MustacheTemplates::render($document->document_type_code, $issued));
        $process = new Process([config()->string('files.renderer.python'), base_path('tests/Support/Documents/layout.py'), config()->string('files.renderer.assets')],
            null, ['HOME' => sys_get_temp_dir(), 'XDG_CACHE_HOME' => sys_get_temp_dir(), 'LANG' => 'C.UTF-8'], $html, 120);
        $process->mustRun();

        return response()->json(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));
    }

    /** The current version's latest attempt rendered now (`state` READY) or failed with its attempt counted (FAILED). */
    public static function rendition(Request $request): JsonResponse
    {
        $document = DB::table('documents')->where('public_id', (string) $request->input('document'))->value('id');
        $version = DocumentVersion::query()->where('document_id', $document)->where('state', 'ISSUED')->firstOrFail();
        $attempt = DocumentRendition::query()->where('document_version_id', $version->id)->orderByDesc('attempt_no')->firstOrFail();

        if ($request->input('state') === 'FAILED') {
            DB::table('document_renditions')->where('id', $attempt->id)->update(['state' => 'FAILED', 'error_summary' => 'Uji kegagalan.', 'updated_at' => now()]);
        } else {
            dispatch_sync(new RenderDocument($attempt->id));
        }

        return response()->json(['state' => DocumentRendition::query()->whereKey($attempt->id)->value('state')]);
    }
}
