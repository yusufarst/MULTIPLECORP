<?php

use App\Modules\DOC\Contracts\RenderFailed;
use App\Modules\DOC\Contracts\RenditionTemplate;
use App\Modules\DOC\Jobs\RenderDocument;
use App\Modules\DOC\Jobs\RenditionSweeper;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Services\Renditions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-022; CONCURRENCY_IDEMPOTENCY JB-01, JB-02; M-31, M-32; GAP-007; SF-RENDER; criterion 3) on real
// PostgreSQL with the real renderer (DIR-099 C1): an issued version's PENDING attempt is rendered once into a private
// RENDITION file of the version's company and becomes READY, the version — its number, period, date and payload —
// untouched; a worker killed mid-render leaves RENDERING, which a later job takes over, one READY in the end; a crash
// after the issue's commit and before the dispatch is found by the sweeper after 2 minutes, never sooner; a failed
// attempt is followed after its backoff, five in all, then stays FAILED with the operator alerted; a revision issued
// while the predecessor's rendition is pending leaves that rendition the predecessor's; and a template naming a
// network address or a file outside the assets fails its attempt (SM-10).

uses(RefreshDatabase::class);

final class FixtureTemplate implements RenditionTemplate
{
    public static ?string $body = null;

    public function html(DocumentVersion $version, string $assetDirectory): string
    {
        $title = e((string) ($version->payload['judul'] ?? ''));
        $number = e((string) $version->formatted_number);

        return '<!doctype html><html lang="id"><head><meta charset="utf-8"><link rel="stylesheet" href="fonts.css">'
            .'<style>@page{size:A4;margin:20mm} body{font-family:"Inter Variable"}</style></head>'
            .'<body><h1>'.$title.'</h1><p>Nomor '.$number.'</p>'.(self::$body ?? '').'</body></html>';
    }
}

final class FailingTemplate implements RenditionTemplate
{
    public function html(DocumentVersion $version, string $assetDirectory): string
    {
        throw new RenderFailed('Template fixture failure.');
    }
}

beforeEach(function () {
    Storage::fake('files');
    FixtureTemplate::$body = null;
    app()->instance(RenditionTemplate::class, new FixtureTemplate);
});

function pendingAttempt(): DocumentRendition
{
    return DocumentRendition::factory()->create();
}

/** The version's columns BR-DOC-03 says a rendition never touches. */
function issuedFacts(int $versionId): array
{
    return (array) DB::table('document_versions')->where('id', $versionId)
        ->first(['state', 'business_date', 'period_key', 'sequence_no', 'formatted_number', 'payload', 'payload_sha256']);
}

it('renders a PENDING attempt once into a RENDITION file of the version\'s company, the version untouched (JB-01; BR-DOC-03)', function () {
    $attempt = pendingAttempt();
    $before = issuedFacts($attempt->document_version_id);

    RenderDocument::dispatch($attempt->id);

    $attempt->refresh();
    $file = FileObject::query()->findOrFail($attempt->file_object_id);
    $version = DocumentVersion::query()->findOrFail($attempt->document_version_id);

    expect($attempt->state)->toBe('READY')
        ->and($attempt->renderer_version)->toBe('weasyprint-68.1')
        ->and($file->purpose)->toBe('RENDITION')
        ->and($file->company_id)->toBe($version->company_id)
        ->and($file->mime_type)->toBe('application/pdf')
        ->and(str_starts_with((string) Storage::disk('files')->get($file->storage_key), '%PDF-'))->toBeTrue()
        ->and($file->sha256)->toBe(hash('sha256', (string) Storage::disk('files')->get($file->storage_key)))
        ->and(issuedFacts($attempt->document_version_id))->toBe($before);

    // A second dispatch for a READY attempt does nothing.
    RenderDocument::dispatch($attempt->id);

    expect(DocumentRendition::query()->where('document_version_id', $attempt->document_version_id)->count())->toBe(1)
        ->and(FileObject::query()->where('purpose', 'RENDITION')->count())->toBe(1);
});

it('takes over a worker\'s abandoned attempt, one READY in the end, never a second document (M-31)', function () {
    $attempt = pendingAttempt();
    $renditions = app(Renditions::class);

    // The first worker claims its attempt and dies while rendering.
    $claimed = $renditions->claim($attempt->id);
    expect($claimed?->state)->toBe('RENDERING');

    // The queue's redelivery of the same job takes the attempt over and renders it as the next one.
    RenderDocument::dispatch($attempt->id);

    $attempts = DocumentRendition::query()->where('document_version_id', $attempt->document_version_id)->orderBy('attempt_no')->get();

    expect($attempts->pluck('state')->all())->toBe(['FAILED', 'READY'])
        ->and($attempts[0]->error_summary)->toContain('Abandoned');

    // The dead worker, had it finished after all, is superseded: it writes nothing and leaves no bytes.
    $files = Storage::disk('files')->allFiles();
    expect($renditions->finish($claimed, '%PDF-1.7 late', 'weasyprint-68.1'))->toBeFalse()
        ->and(Storage::disk('files')->allFiles())->toBe($files)
        ->and(DocumentRendition::query()->where('document_version_id', $attempt->document_version_id)->where('state', 'READY')->count())->toBe(1);
});

it('finds a crash between the issue\'s commit and its dispatch after 2 minutes, never sooner (M-32; GAP-007; JB-02)', function () {
    $attempt = pendingAttempt();
    Queue::fake();

    $this->travel(90)->seconds();
    (new RenditionSweeper)->handle();
    Queue::assertNothingPushed();

    $this->travel(60)->seconds();
    $sweeper = new RenditionSweeper;
    $sweeper->handle();

    Queue::assertPushed(RenderDocument::class, fn (RenderDocument $job): bool => $job->renditionId === $attempt->id);
    expect($sweeper->redispatched)->toBe(1)
        ->and($attempt->refresh()->state)->toBe('PENDING');
});

it('follows a failed attempt after its backoff, five in all, then keeps it FAILED and alerts the operator (JB-01; JB-02)', function () {
    app()->instance(RenditionTemplate::class, new FailingTemplate);
    $attempt = pendingAttempt();
    Log::spy();

    RenderDocument::dispatch($attempt->id);
    expect($attempt->refresh()->state)->toBe('FAILED');

    foreach ([1 => 1, 2 => 5, 3 => 15, 4 => 60] as $number => $minutes) {
        $latest = DocumentRendition::query()->where('document_version_id', $attempt->document_version_id)->orderByDesc('attempt_no')->firstOrFail();
        expect($latest->attempt_no)->toBe($number);

        // Not yet due: a job dispatched now claims nothing.
        RenderDocument::dispatch($latest->id);
        expect(DocumentRendition::query()->where('document_version_id', $attempt->document_version_id)->count())->toBe($number);

        $this->travel($minutes)->minutes();
        RenderDocument::dispatch($latest->id);
    }

    $states = DocumentRendition::query()->where('document_version_id', $attempt->document_version_id)->orderBy('attempt_no')->pluck('state')->all();
    expect($states)->toBe(['FAILED', 'FAILED', 'FAILED', 'FAILED', 'FAILED']);
    Log::shouldHaveReceived('critical')->withArgs(fn (string $message): bool => str_contains($message, 'last attempt'))->atLeast()->once();

    // Spent: the sweeper owes it nothing any more.
    Queue::fake();
    $this->travel(2)->hours();
    (new RenditionSweeper)->handle();
    Queue::assertNothingPushed();
});

it('leaves a pending rendition the predecessor\'s when a revision is issued meanwhile (C-11; C-12; architecture challenge 7a)', function () {
    $attempt = pendingAttempt();
    $old = DocumentVersion::query()->findOrFail($attempt->document_version_id);
    DB::table('document_versions')->where('id', $old->id)->update(['state' => 'SUPERSEDED']);
    $new = DocumentVersion::factory()->issued()->create(['document_id' => $old->document_id, 'version_no' => 2, 'supersedes_version_id' => $old->id]);

    RenderDocument::dispatch($attempt->id);

    expect($attempt->refresh()->state)->toBe('READY')
        ->and($attempt->document_version_id)->toBe($old->id)
        ->and(DocumentRendition::query()->where('document_version_id', $new->id)->count())->toBe(0);
});

it('fails an attempt whose document names the network or a file outside the assets (FL-08; SM-10; H8-10)', function (string $body) {
    FixtureTemplate::$body = $body;
    $attempt = pendingAttempt();

    RenderDocument::dispatch($attempt->id);

    expect($attempt->refresh()->state)->toBe('FAILED')
        ->and($attempt->error_summary)->toContain('refused')
        ->and(FileObject::query()->where('purpose', 'RENDITION')->count())->toBe(0);
})->with([
    'a metadata address' => ['<img src="http://169.254.169.254/latest/meta-data/">'],
    'a remote stylesheet' => ['<link rel="stylesheet" href="https://example.invalid/x.css">'],
    'a local file' => ['<img src="file:///etc/passwd">'],
    'a path out of the assets' => ['<img src="../../../../../../etc/hostname">'],
    'a missing asset' => ['<img src="tidak-ada.png">'],
]);

it('locks the subject\'s project and the projects of the requirements linked to the version (LK-01; §5)', function () {
    $attempt = pendingAttempt();
    $project = (int) DB::table('documents')->join('document_versions', 'document_versions.document_id', '=', 'documents.id')
        ->where('document_versions.id', $attempt->document_version_id)->value('documents.project_id');

    expect(app(Renditions::class)->projectsOf($attempt->document_version_id))->toBe([$project]);
});
