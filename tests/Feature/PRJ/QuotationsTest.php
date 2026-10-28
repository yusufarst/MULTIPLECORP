<?php

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\QuotationRecords;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-033; ROUTE-014, ROUTE-037, ROUTE-154–ROUTE-157; WF-QUO-01; J-QUO-01; criteria 1–5, 9): PG-014 and PG-037
// read with their values only under `finance.view`; the draft created from the project's lines and saved at the version
// read, a stale save CONFLICT; the issue's MSG-17 refusal before numbering and its CONFLICT with the number; "Buat Revisi";
// the client's answer — the approval answered on PG-011, the rejection with its reason, an expired revision refused —;
// DOC-01 never voided through PG-036 (BR-CR-06).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Queue::fake();
    Authorization::catalogue();
    DocumentTypeFactory::ensure('DOC-01');
    $this->company = Projects::company();
    $this->year = substr(BusinessDate::today(), 0, 4);
    $this->client = Projects::client();
    $this->goods = Projects::product();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view', 'quotation.manage', 'projects.confirm']);
    expect(Projects::create($this->admin->id, $this->company, $this->client, [$this->goods]))->toStartWith('COMMITTED');
    $this->project = Project::query()->sole();
});

afterEach(fn () => @unlink($this->hotFile));

function quotationAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function startQuotationNumbering(object $test): void
{
    Numbering::scheme($test->company, SequenceType::Doc01, "{$test->year}-01-01", 'YEAR', 'UJI/PNW/{tahun}/{nomor:4}');
    Numbering::started($test->company, SequenceType::Doc01, CarbonImmutable::parse(((int) $test->year - 1).'-12-31 03:00:00', 'UTC'));
}

function quotationPost(object $test, string $path, array $payload, ?object $actor = null): TestResponse
{
    return $test->actingAs($actor ?? $test->admin)->post("/proyek/{$test->project->public_id}/penawaran{$path}", ['command_id' => (string) Str::uuid(), ...$payload]);
}

/** The project's draft, created from its lines and saved with a validity two weeks out and the line priced. */
function savedQuotation(object $test): array
{
    quotationPost($test, '', [])->assertRedirect("/proyek/{$test->project->public_id}/penawaran");
    $revision = QuotationRecords::latest($test->project->id);
    $line = (int) DB::table('quotation_revision_lines')->where('quotation_revision_id', $revision['id'])->value('id');
    quotationPost($test, '', ['revisi' => $revision['public_id'], 'lock_version' => $revision['lock_version'], 'berlaku' => CarbonImmutable::parse(BusinessDate::today())->addDays(14)->toDateString(),
        'baris' => [['baris' => RowHandle::of('quotation_revision_lines', $line), 'jumlah' => '12', 'harga' => '20000', 'keterangan' => 'Buku paket']]]);
    expect(quotationAnswer())->toMatchArray(['kind' => 'COMMITTED']);

    return QuotationRecords::latest($test->project->id);
}

function issuedQuotation(object $test): array
{
    $revision = savedQuotation($test);
    quotationPost($test, '/terbitkan', ['revisi' => $revision['public_id'], 'lock_version' => $revision['lock_version'], 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true]);
    expect(quotationAnswer())->toMatchArray(['kind' => 'COMMITTED']);

    return QuotationRecords::latest($test->project->id);
}

it('shows PG-014 empty, then the draft from the project\'s lines with its paper, values only with finance.view (ROUTE-014, ROUTE-154; criterion 9)', function () {
    $this->actingAs($this->admin)->get("/proyek/{$this->project->public_id}/penawaran")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('PRJ/Quotation', false)->where('current', null)->where('can.manage', true)->has('revisions', 0));

    savedQuotation($this);

    $this->actingAs($this->admin)->get("/proyek/{$this->project->public_id}/penawaran")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('current.state', 'DRAFT')->where('current.lines.0.price', '20000.00')->where('current.lines.0.value', '240000.00')
        ->where('current.paper.layout', 'DOC-01')->where('current.paper.draft', true)->has('revisions', 1));

    // `projects.confirm` alone reads the quotation for the client's answer, without its values (RG-06; PJ-15).
    $confirmer = Authorization::admin([$this->company->id], ['projects.view', 'projects.confirm']);
    $this->actingAs($confirmer)->get("/proyek/{$this->project->public_id}/penawaran")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('current.paper', null)->missing('current.lines.0.price')->missing('current.lines.0.value')->where('can.manage', false)->where('can.approve', true)->where('taxes', []));
});

it('refuses PG-014 to an account with neither path and answers an unknown project alike (ROUTE-014; AZ-11)', function () {
    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view', 'quotation.manage']))->get("/proyek/{$this->project->public_id}/penawaran")->assertForbidden();
    $this->actingAs(Authorization::admin([Projects::company()->id], ['projects.view', 'quotation.manage', 'finance.view']))->get("/proyek/{$this->project->public_id}/penawaran")->assertNotFound();
    $this->actingAs($this->admin)->get('/proyek/'.Str::uuid().'/penawaran')->assertNotFound();
});

it('keeps a stale save\'s input with CONFLICT and refuses a past validity at its field (ROUTE-154; ST-01; criterion 7)', function () {
    $revision = savedQuotation($this);
    $handle = RowHandle::of('quotation_revision_lines', (int) DB::table('quotation_revision_lines')->where('quotation_revision_id', $revision['id'])->value('id'));

    quotationPost($this, '', ['revisi' => $revision['public_id'], 'lock_version' => $revision['lock_version'] - 1, 'baris' => [['baris' => $handle, 'jumlah' => '5', 'harga' => '1000']]]);
    expect(quotationAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'ST-01', 'cause' => 'stale']);

    quotationPost($this, '', ['revisi' => $revision['public_id'], 'lock_version' => $revision['lock_version'], 'berlaku' => CarbonImmutable::parse(BusinessDate::today())->subDay()->toDateString(), 'baris' => [['baris' => $handle, 'jumlah' => '5', 'harga' => '1000']]])
        ->assertSessionHasErrors('berlaku');

    quotationPost($this, '', ['revisi' => $revision['public_id'], 'lock_version' => $revision['lock_version'], 'baris' => [['baris' => str_repeat('a', 32), 'jumlah' => '5', 'harga' => '1000']]])
        ->assertSessionHasErrors('baris.0.baris');
    expect(DB::table('quotation_revision_lines')->where('quotation_revision_id', $revision['id'])->value('entered_qty'))->toBe('12.000');
});

it('refuses the issue in words until DOC-01 numbering starts, then issues once — a second issue CONFLICT with its number (ROUTE-155; C-10; BD-06; criterion 1)', function () {
    $revision = savedQuotation($this);
    $issue = fn () => quotationPost($this, '/terbitkan', ['revisi' => $revision['public_id'], 'lock_version' => $revision['lock_version'], 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true]);

    $issue()->assertRedirect("/proyek/{$this->project->public_id}/penawaran");
    expect(quotationAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-10'])
        ->and(quotationAnswer()['text'])->toContain('Penawaran')
        ->and(QuotationRecords::latest($this->project->id)['state'])->toBe('DRAFT');

    startQuotationNumbering($this);
    $issue();
    expect(quotationAnswer())->toMatchArray(['kind' => 'COMMITTED'])->and(QuotationRecords::latest($this->project->id)['state'])->toBe('SENT');

    $issue();
    expect(quotationAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'BD-06'])
        ->and(quotationAnswer()['text'])->toContain("UJI/PNW/{$this->year}/0001");
});

it('records the client\'s rejection with its reason, revises, and approves the latest sent revision onto PG-011 (ROUTE-156, ROUTE-157; criteria 3, 4)', function () {
    startQuotationNumbering($this);
    $first = issuedQuotation($this);
    $answer = fn (array $revision, string $choice, ?string $reason = null, ?object $actor = null) => quotationPost($this, '/jawaban',
        ['revisi' => $revision['public_id'], 'jawaban' => $choice, 'alasan' => $reason, 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true], $actor);

    $answer($first, 'TOLAK')->assertSessionHasErrors('alasan');
    $answer($first, 'TOLAK', 'Harga terlalu tinggi')->assertRedirect("/proyek/{$this->project->public_id}/penawaran");
    expect(quotationAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(DB::table('quotation_revisions')->where('id', $first['id'])->first())->toMatchObject(['state' => 'REJECTED', 'decision_reason' => 'Harga terlalu tinggi']);
    $answer($first, 'SETUJU');
    expect(quotationAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'BD-05']);

    quotationPost($this, '/revisi', ['revisi' => $first['public_id']]);
    expect(quotationAnswer())->toMatchArray(['kind' => 'COMMITTED']);
    $second = QuotationRecords::latest($this->project->id);
    quotationPost($this, '/terbitkan', ['revisi' => $second['public_id'], 'lock_version' => $second['lock_version'], 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true]);

    // `quotation.manage` alone never approves; `projects.confirm` does, onto PG-011 (AX-13; SM-04).
    $answer($second, 'SETUJU', null, Authorization::admin([$this->company->id], ['projects.view', 'quotation.manage', 'finance.view']))->assertForbidden();
    $answer($second, 'SETUJU', null, Authorization::admin([$this->company->id], ['projects.view', 'projects.confirm']))->assertRedirect("/proyek/{$this->project->public_id}");
    expect(quotationAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(DB::table('commercial_confirmations')->where('quotation_revision_id', $second['id'])->count())->toBe(1)
        ->and(DB::table('document_versions')->whereNotNull('quotation_revision_id')->orderBy('version_no')->pluck('state')->all())->toBe(['SUPERSEDED', 'ISSUED']);
});

it('refuses the approval of an expired revision in words and offers only "Buat Revisi" (L-25; BR-QUO-01; criterion 2)', function () {
    startQuotationNumbering($this);
    $revision = issuedQuotation($this);
    DB::table('quotation_revisions')->where('id', $revision['id'])->update(['valid_until' => CarbonImmutable::parse(BusinessDate::today())->subDay()->toDateString()]);

    quotationPost($this, '/jawaban', ['revisi' => $revision['public_id'], 'jawaban' => 'SETUJU', 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true]);
    expect(quotationAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'L-25'])
        ->and(DB::table('commercial_confirmations')->count())->toBe(0);

    $this->actingAs($this->admin)->get("/proyek/{$this->project->public_id}/penawaran")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('revisions.0.state', 'EXPIRED')->where('current.state', 'SENT'));
});

it('never voids a quotation through PG-036 — a revision is the correction (L-25; BR-CR-06)', function () {
    startQuotationNumbering($this);
    $revision = issuedQuotation($this);
    $document = DB::table('documents')->where('id', DB::table('document_versions')->where('quotation_revision_id', $revision['id'])->value('document_id'))->value('public_id');
    $corrector = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'documents.correct']);

    $this->actingAs($corrector)->post("/dokumen/{$document}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Batal']);
    expect(quotationAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'L-25'])
        ->and(DB::table('document_versions')->where('quotation_revision_id', $revision['id'])->value('state'))->toBe('ISSUED');
});

it('lists one company\'s revisions on PG-037 with their state, the value only with finance.view (ROUTE-037; PJ-15; criterion 9)', function () {
    savedQuotation($this);

    $this->actingAs($this->admin)->get('/dokumen/penawaran')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('PRJ/Quotations', false)->where('money', true)->has('rows', 1)->where('rows.0.state', 'DRAFT')->where('rows.0.value', '240000.00'));

    $viewer = Authorization::admin([$this->company->id], ['projects.view']);
    $this->actingAs($viewer)->get('/dokumen/penawaran')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('money', false)->has('rows', 1)->where('rows.0.state', 'DRAFT')->missing('rows.0.value'));

    $this->actingAs(Authorization::admin([$this->company->id], ['finance.view']))->get('/dokumen/penawaran')->assertForbidden();
});
