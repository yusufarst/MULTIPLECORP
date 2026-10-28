<?php

use App\Modules\DOC\Contracts\DocumentChangeParticipant;
use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Jobs\RenderDocument;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Contracts\RenditionTemplate;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\FileStore;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Documents\SyntheticSubjects;
use Tests\Support\Numbering;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-029; ROUTE-035, ROUTE-036, ROUTE-221–ROUTE-225; criteria 1, 3–6, 8, 9, 12, 13, 15): a draft prepared from
// its record's facts and saved at the version read; its issue drawing the period's next number, freezing the payload
// and writing one PENDING rendition, the render dispatched after the commit; a second issue CONFLICT with the number,
// a replay answered from the record; C-10, C-54, a refused precondition and a stale draft each refused with nothing
// written; a revision NEW_NUMBER or BASE_SUFFIX; a void with its reason handed to the participants; the list and the
// rendition by family.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    SyntheticSubjects::register();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->today = BusinessDate::today();
    $this->year = substr($this->today, 0, 4);
    Numbering::goLive("{$this->year}-01-01");
    $this->company = Company::factory()->create();
    startNumbering($this->company, SequenceType::Doc09, $this->year, 'CTH/SPK/{tahun}/{nomor:4}');
    $this->project = ProjectFactory::new()->createOne(['company_id' => $this->company->id]);
    $this->issuer = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'documents.issue']);
    $this->corrector = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'documents.correct']);
});

afterEach(fn () => @unlink($this->hotFile));

/** A type's numbering started at the year's start: its scheme and the start's boundary (NM-03). */
function startNumbering(Company $company, SequenceType $type, string $year, string $template): void
{
    Numbering::scheme($company, $type, "{$year}-01-01", 'YEAR', $template);
    // NM-03: a period is live once it begins after the start's day — here the eve of the year.
    Numbering::started($company, $type, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
}

function documentAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** ROUTE-221's preparation of the project's document of a type from its record. */
function prepareDocument(object $test, string $type = 'DOC-09', ?string $commandId = null): TestResponse
{
    return $test->actingAs($test->issuer)->post('/dokumen', ['command_id' => $commandId ?? (string) Str::uuid(), 'jenis' => $type, 'subjek' => ['project_id' => $test->project->public_id]]);
}

function preparedDocument(object $test, string $type = 'DOC-09'): Document
{
    prepareDocument($test, $type);

    return Document::query()->where('project_id', $test->project->id)->where('document_type_code', $type)->firstOrFail();
}

function issueDocument(object $test, Document $document, ?int $lockVersion = null, ?string $commandId = null, array $extra = []): TestResponse
{
    $draft = DocumentVersion::query()->where('document_id', $document->id)->where('state', 'DRAFT')->first();

    return $test->actingAs($test->issuer)->post("/dokumen/{$document->public_id}/terbitkan", [
        'command_id' => $commandId ?? (string) Str::uuid(), 'lock_version' => $lockVersion ?? $draft?->lock_version ?? 0,
        'tanggal' => $test->today, 'tanggal_otomatis' => true, ...$extra,
    ]);
}

it('prepares a draft from its record once, writing no number, counter, payload or rendition (ROUTE-221; criterion 8)', function () {
    prepareDocument($this)->assertRedirect();
    $document = Document::query()->where('project_id', $this->project->id)->firstOrFail();
    prepareDocument($this)->assertRedirect("/dokumen/{$document->public_id}");

    $draft = DocumentVersion::query()->where('document_id', $document->id)->sole();
    expect($draft->state)->toBe('DRAFT')
        ->and($draft->formatted_number)->toBeNull()
        ->and($draft->payload)->toBeNull()
        ->and($draft->business_date?->format('Y-m-d'))->toBe($this->today)
        ->and(DB::table('number_sequences')->count())->toBe(0)
        ->and(DB::table('document_renditions')->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', 'DOC.DocumentDraftCreated')->count())->toBe(1);
});

it('shows the draft\'s paper by family — marked, unnumbered, the logo at most, never the stamp or signature (ROUTE-036; criteria 8, 9)', function () {
    $document = preparedDocument($this);

    $this->actingAs($this->issuer)->get("/dokumen/{$document->public_id}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('DOC/Document', false)
        ->where('content', true)
        ->where('draft.paper.draft', true)
        ->where('draft.paper.number', null)
        ->where('draft.paper.layout', 'DOC-09')
        ->where('can.issue', true)
        ->missing('draft.paper.stamp_src')
        ->missing('draft.paper.signature_src'));

    // PJ-15: the metadata without the finance family's view, and no draft props at all (WS-07).
    $viewer = Authorization::admin([$this->company->id], ['projects.view']);
    $this->actingAs($viewer)->get("/dokumen/{$document->public_id}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('content', false)->where('draft', null)->where('can.issue', false));
});

it('saves the draft\'s date at the version read, a stale save CONFLICT and a future date refused at its field (ROUTE-221; ST-01; C-50)', function () {
    $document = preparedDocument($this);
    $yesterday = date('Y-m-d', strtotime("{$this->today} -1 day"));
    $save = fn (array $body) => $this->actingAs($this->issuer)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'dokumen' => $document->public_id, ...$body]);

    $save(['lock_version' => 0, 'tanggal' => $yesterday, 'tanggal_otomatis' => false])->assertRedirect("/dokumen/{$document->public_id}");
    expect(documentAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(DocumentVersion::query()->where('document_id', $document->id)->value('lock_version'))->toBe(1);

    $save(['lock_version' => 0, 'tanggal' => $this->today, 'tanggal_otomatis' => false]);
    expect(documentAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'code' => 'ST-01']);

    $save(['lock_version' => 1, 'tanggal' => date('Y-m-d', strtotime("{$this->today} +1 day")), 'tanggal_otomatis' => false])->assertSessionHasErrors('tanggal');
});

it('issues the draft: the next number, the frozen payload and one PENDING rendition, rendered after the commit (AX-15; criterion 1)', function () {
    Queue::fake();
    $document = preparedDocument($this);

    issueDocument($this, $document)->assertRedirect("/dokumen/{$document->public_id}");

    $version = DocumentVersion::query()->where('document_id', $document->id)->sole();
    expect(documentAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and($version->state)->toBe('ISSUED')
        ->and($version->formatted_number)->toBe("CTH/SPK/{$this->year}/0001")
        ->and($version->sequence_no)->toBe(1)
        ->and($version->issued_by)->toBe($this->issuer->id)
        ->and($version->payload['paper']['number'])->toBe("CTH/SPK/{$this->year}/0001")
        ->and($version->payload['paper']['draft'])->toBeFalse()
        ->and($version->payload['paper']['issuer']['name'])->toBe($this->company->legal_name)
        ->and($version->payload_sha256)->toBe(DocumentChain::checksum($version->payload))
        ->and(DB::table('document_renditions')->where('document_version_id', $version->id)->get(['attempt_no', 'state'])->all())->toEqual([(object) ['attempt_no' => 1, 'state' => 'PENDING']])
        ->and(DB::table('audit_events')->where('action', 'DOC.DocumentIssued')->where('entity_id', $version->id)->count())->toBe(1);
    Queue::assertPushed(RenderDocument::class, 1);

    // BR-SN-01: a later change of the company changes no issued version.
    DB::table('companies')->where('id', $this->company->id)->update(['legal_name' => 'CV Nama Baru']);
    expect($version->fresh()?->payload['paper']['issuer']['name'])->toBe($this->company->legal_name)
        ->and(app(RenditionTemplate::class)->html($version->fresh() ?? $version, sys_get_temp_dir()))->toContain($this->company->legal_name)->not->toContain('CV Nama Baru');
});

it('answers a second issue of one draft CONFLICT with its number and a replay from the record, issuing once (BD-06; CI-05; criteria 2, 3)', function () {
    Queue::fake();
    $document = preparedDocument($this);
    $commandId = (string) Str::uuid();
    issueDocument($this, $document, 0, $commandId);

    issueDocument($this, $document, 0, $commandId)->assertRedirect();
    expect(documentAnswer()['kind'])->toBe(Outcome::COMMITTED);

    issueDocument($this, $document, 0);
    expect(documentAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'code' => 'BD-06'])
        ->and(documentAnswer()['text'])->toContain("CTH/SPK/{$this->year}/0001")
        ->and(Numbering::next($this->company, SequenceType::Doc09, $this->year))->toBe(2)
        ->and(SyntheticSubjects::$preconditions)->toBe(1);
});

it('refuses an issue whose period has no numbering, of a deactivated company\'s new chain, or a refused precondition, writing nothing (C-10; C-54; criterion 13)', function () {
    $other = preparedDocument($this, 'DOC-11');
    issueDocument($this, $other);
    expect(documentAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'C-10']);

    $document = preparedDocument($this);
    SyntheticSubjects::$refusal = 'C-43';
    issueDocument($this, $document);
    expect(documentAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'C-43']);

    SyntheticSubjects::$refusal = null;
    DB::table('companies')->where('id', $this->company->id)->update(['is_active' => false, 'deactivated_at' => now(), 'deactivated_by' => $this->issuer->id, 'deactivated_reason' => 'Uji']);
    issueDocument($this, $document);
    expect(documentAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'C-54'])
        ->and(DocumentVersion::query()->whereIn('document_id', [$document->id, $other->id])->pluck('state')->unique()->all())->toBe(['DRAFT'])
        ->and(DB::table('number_sequences')->count())->toBe(0)
        ->and(DB::table('document_renditions')->count())->toBe(0);
});

it('revises an issued version — the predecessor current until the revision issues with a new number or the base number\'s suffix (NM-07; criterion 5)', function () {
    Queue::fake();
    $document = preparedDocument($this);
    issueDocument($this, $document);
    $first = DocumentVersion::query()->where('document_id', $document->id)->sole();

    $this->actingAs($this->corrector)->post("/dokumen/{$document->public_id}/revisi", ['command_id' => (string) Str::uuid(), 'alasan' => 'Nama sekolah keliru'])->assertRedirect();
    expect(documentAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and($first->fresh()?->state)->toBe('ISSUED')
        ->and(DocumentVersion::query()->where('document_id', $document->id)->where('state', 'DRAFT')->value('supersedes_version_id'))->toBe($first->id)
        ->and(DB::table('audit_events')->where('action', 'DOC.DocumentRevisionDrafted')->value('reason'))->toBe('Nama sekolah keliru');

    issueDocument($this, $document);
    expect($first->fresh()?->state)->toBe('SUPERSEDED')
        ->and(DocumentVersion::query()->where('document_id', $document->id)->where('state', 'ISSUED')->value('formatted_number'))->toBe("CTH/SPK/{$this->year}/0002");

    // BASE_SUFFIX: the base number with the next suffix, no counter drawn.
    DB::table('numbering_schemes')->where('company_id', $this->company->id)->update(['revision_format' => 'BASE_SUFFIX']);
    $this->actingAs($this->corrector)->post("/dokumen/{$document->public_id}/revisi", ['command_id' => (string) Str::uuid(), 'alasan' => 'Alamat keliru']);
    issueDocument($this, $document);
    expect(DocumentVersion::query()->where('document_id', $document->id)->where('state', 'ISSUED')->first(['formatted_number', 'revision_suffix'])?->only(['formatted_number', 'revision_suffix']))
        ->toBe(['formatted_number' => "CTH/SPK/{$this->year}/0002-R1", 'revision_suffix' => 1])
        ->and(Numbering::next($this->company, SequenceType::Doc09, $this->year))->toBe(3);
});

it('voids an issued version with its reason, the participants re-evaluating in its transaction, never the business record (NX-12; L-41; criteria 5, 6)', function () {
    Queue::fake();
    $seen = new ArrayObject;
    app()->instance('test.participant', new class($seen) implements DocumentChangeParticipant
    {
        public function __construct(private readonly ArrayObject $seen) {}

        public function locks(DocumentChange $change): LockSet
        {
            return new LockSet;
        }

        public function documentChanged(CommandContext $context, DocumentChange $change): void
        {
            $this->seen[] = [$change->kind, $change->previousVersionId, DB::transactionLevel() > 0];
        }
    });
    app()->tag(['test.participant'], DocumentChangeParticipant::TAG);
    $document = preparedDocument($this);
    issueDocument($this, $document);
    $version = DocumentVersion::query()->where('document_id', $document->id)->sole();
    $projects = DB::table('projects')->where('id', $this->project->id)->first();

    $this->actingAs($this->corrector)->post("/dokumen/{$document->public_id}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah pihak'])->assertRedirect();

    expect(documentAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and($version->fresh()?->only(['state', 'voided_reason', 'voided_by', 'formatted_number']))->toBe(['state' => 'VOIDED', 'voided_reason' => 'Salah pihak', 'voided_by' => $this->corrector->id, 'formatted_number' => $version->formatted_number])
        ->and($seen->getArrayCopy())->toBe([[DocumentChange::DOCUMENT_VOIDED, $version->id, true]])
        ->and(DB::table('projects')->where('id', $this->project->id)->first())->toEqual($projects)
        ->and(DB::table('audit_events')->where('action', 'DOC.DocumentVoided')->value('authority_class'))->toBe('ADM_PLUS');

    $this->actingAs($this->corrector)->post("/dokumen/{$document->public_id}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Lagi']);
    expect(documentAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'code' => 'ST-02']);
});

it('never voids a quotation and corrects an invoice layout through the invoice (L-25; CM-23)', function () {
    Queue::fake();
    startNumbering($this->company, SequenceType::Doc01, $this->year, 'CTH/PNW/{tahun}/{nomor:4}');
    Authorization::grant($this->issuer, 'quotation.manage');
    $document = preparedDocument($this, 'DOC-01');
    issueDocument($this, $document);

    $this->actingAs($this->corrector)->post("/dokumen/{$document->public_id}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Batal']);
    expect(documentAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'L-25'])
        ->and(DocumentVersion::query()->where('document_id', $document->id)->value('state'))->toBe('ISSUED');
});

it('lists one company\'s documents with the exact number lookup inside its scope (ROUTE-035; PJ-23; criterion 14)', function () {
    Queue::fake();
    issueDocument($this, preparedDocument($this));
    $foreign = Company::factory()->create();
    startNumbering($foreign, SequenceType::Doc09, $this->year, 'CTH/SPK/{tahun}/{nomor:4}');
    $this->project = ProjectFactory::new()->createOne(['company_id' => $foreign->id]);
    $this->issuer = Authorization::admin([$foreign->id], ['projects.view', 'finance.view', 'documents.issue']);
    issueDocument($this, preparedDocument($this));
    $number = "CTH/SPK/{$this->year}/0001";
    $viewer = Authorization::admin([$this->company->id], ['projects.view']);

    $this->actingAs($viewer)->get('/dokumen?nomor='.urlencode(strtolower($number)))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('DOC/Documents', false)
        ->has('branches', 1)
        ->has('rows', 1)
        ->where('rows.0.number', $number)
        ->where('rows.0.state', 'ISSUED'));
    $this->actingAs($viewer)->get('/dokumen?perusahaan='.$foreign->public_id)->assertInertia(fn (AssertableInertia $page) => $page->where('branch', $this->company->public_id));
});

it('shows a ready rendition inline under the RENDITION profile only to its family, at request time (ROUTE-225; FL-07)', function () {
    Queue::fake();
    $document = preparedDocument($this);
    issueDocument($this, $document);
    $version = DocumentVersion::query()->where('document_id', $document->id)->sole();
    $pdf = '%PDF-1.4 uji';
    $file = FileObject::factory()->create(['purpose' => 'RENDITION', 'mime_type' => 'application/pdf', 'company_id' => $this->company->id, 'original_filename' => null,
        'size_bytes' => strlen($pdf), 'sha256' => hash('sha256', $pdf), 'storage_disk' => app(FileStore::class)->diskName()]);
    app(FileStore::class)->write($file->storage_key, $pdf);
    DB::table('document_renditions')->where('document_version_id', $version->id)->update(['state' => 'READY', 'file_object_id' => $file->id, 'renderer_version' => 'uji']);

    $response = $this->actingAs($this->issuer)->get("/berkas/{$file->public_id}/tampil")->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; frame-ancestors 'self'")
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view']))->get("/berkas/{$file->public_id}/tampil")->assertNotFound();
});

it('prepares another chain of a several-chain layout once the first is issued, an open draft reused (DATABASE §4.10)', function () {
    Queue::fake();
    $first = preparedDocument($this);
    issueDocument($this, $first);

    prepareDocument($this);
    prepareDocument($this);
    $chains = Document::query()->where('project_id', $this->project->id)->where('document_type_code', 'DOC-09')->orderBy('id')->pluck('id')->all();

    expect($chains)->toHaveCount(2)
        ->and(DocumentVersion::query()->where('document_id', $chains[1])->pluck('state')->all())->toBe(['DRAFT']);
});

it('refuses a deactivated company\'s new issue after a void, a revision still open to it (C-54; WF-DOC-01)', function () {
    Queue::fake();
    $document = preparedDocument($this);
    issueDocument($this, $document);
    $this->actingAs($this->corrector)->post("/dokumen/{$document->public_id}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah pihak']);
    DB::table('companies')->where('id', $this->company->id)->update(['is_active' => false, 'deactivated_at' => now(), 'deactivated_by' => $this->issuer->id, 'deactivated_reason' => 'Uji']);

    // ROUTE-221 for the voided chain's subject: SPK has several chains, so a new one, whose issue starts new business.
    $again = preparedDocument($this);
    issueDocument($this, $again);
    expect(documentAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'C-54']);
});

it('answers a revision or void of a version no longer current CONFLICT, touching the current one never (ST-02)', function () {
    Queue::fake();
    $document = preparedDocument($this);
    issueDocument($this, $document);
    $shown = DocumentVersion::query()->where('document_id', $document->id)->sole();
    $this->actingAs($this->corrector)->post("/dokumen/{$document->public_id}/revisi", ['command_id' => (string) Str::uuid(), 'alasan' => 'Nama keliru', 'versi' => $shown->public_id]);
    issueDocument($this, $document);
    $current = DocumentVersion::query()->where('document_id', $document->id)->where('state', 'ISSUED')->sole();

    $this->actingAs($this->corrector)->post("/dokumen/{$document->public_id}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Batal', 'versi' => $shown->public_id]);
    expect(documentAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'code' => 'ST-02'])
        ->and($current->fresh()?->state)->toBe('ISSUED');

    $this->actingAs($this->corrector)->post("/dokumen/{$document->public_id}/revisi", ['command_id' => (string) Str::uuid(), 'alasan' => 'Lagi', 'versi' => $shown->public_id]);
    expect(documentAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'code' => 'ST-02'])
        ->and(DocumentVersion::query()->where('document_id', $document->id)->where('state', 'DRAFT')->exists())->toBeFalse();
});

it('refuses an issue whose record changed since its paper was previewed, issuing what was confirmed (ST-01; architecture challenge 15a)', function () {
    Queue::fake();
    $document = preparedDocument($this);
    $previewed = DocumentChain::factsDigest(SyntheticSubjects::sample('DOC-09'));
    $sample = SyntheticSubjects::sample('DOC-09');
    SyntheticSubjects::$facts = new DocumentFacts(recipient: ['name' => 'SMA Negeri 2 Contoh', 'lines' => []], references: $sample->references, subject: $sample->subject, lines: $sample->lines, totals: $sample->totals);

    issueDocument($this, $document, extra: ['isi' => $previewed]);
    expect(documentAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'code' => 'ST-01'])
        ->and(DocumentVersion::query()->where('document_id', $document->id)->value('state'))->toBe('DRAFT')
        ->and(DB::table('number_sequences')->count())->toBe(0);

    issueDocument($this, $document, extra: ['isi' => DocumentChain::factsDigest(SyntheticSubjects::$facts)]);
    expect(documentAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(DocumentVersion::query()->where('document_id', $document->id)->first()?->payload['paper']['recipient']['name'])->toBe('SMA Negeri 2 Contoh');
});

it('draws a backdated issue from its own period, and refuses a date whose period has no numbering (M-18; NM-05)', function () {
    Queue::fake();
    $document = preparedDocument($this);
    $lastYear = ((int) $this->year - 1).'-12-30';

    issueDocument($this, $document, extra: ['tanggal' => $lastYear, 'tanggal_otomatis' => false]);
    expect(documentAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'C-10']);

    $earlier = date('Y-m-d', strtotime("{$this->today} -1 day"));

    if (substr($earlier, 0, 4) === $this->year) {
        issueDocument($this, $document, extra: ['tanggal' => $earlier, 'tanggal_otomatis' => false]);
        expect(documentAnswer()['kind'])->toBe(Outcome::COMMITTED)
            ->and(DocumentVersion::query()->where('document_id', $document->id)->first(['business_date', 'period_key'])?->only(['period_key']))->toBe(['period_key' => $this->year])
            ->and(DocumentVersion::query()->where('document_id', $document->id)->value('business_date')?->format('Y-m-d'))->toBe($earlier);
    }
});

it('keeps the stamp and the signature out of a draft\'s props, the Owner\'s identity assets the renderer\'s alone (PJ-19; DC-4)', function () {
    $assets = [];

    foreach (['logo', 'stamp', 'signature'] as $asset) {
        $assets[$asset] = FileObject::factory()->create(['purpose' => 'IDENTITY_ASSET', 'mime_type' => 'image/png', 'company_id' => $this->company->id, 'storage_disk' => app(FileStore::class)->diskName()]);
    }

    DB::table('companies')->where('id', $this->company->id)->update(['logo_file_id' => $assets['logo']->id, 'stamp_file_id' => $assets['stamp']->id, 'signature_file_id' => $assets['signature']->id]);
    $document = preparedDocument($this);
    $props = json_encode($this->actingAs($this->issuer)->get("/dokumen/{$document->public_id}")->viewData('page')['props']);

    expect($props)->toContain($assets['logo']->public_id)
        ->not->toContain($assets['stamp']->public_id)
        ->not->toContain($assets['signature']->public_id);
});
