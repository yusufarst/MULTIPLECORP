<?php

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\QuotationRecords;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-033; IG-01–IG-05; INT-154–INT-157; CI-05): "Simpan Draf", "Terbitkan Penawaran", "Buat Revisi" and "Catat
// Jawaban Klien" server side — each one's success, its `command_id` replayed answering the recorded outcome with nothing
// written twice and no second number drawn, the same identifier with other content a CONFLICT.

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
    Numbering::scheme($this->company, SequenceType::Doc01, "{$this->year}-01-01", 'YEAR', 'UJI/PNW/{tahun}/{nomor:4}');
    Numbering::started($this->company, SequenceType::Doc01, CarbonImmutable::parse(((int) $this->year - 1).'-12-31 03:00:00', 'UTC'));
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view', 'quotation.manage', 'projects.confirm']);
    expect(Projects::create($this->admin->id, $this->company, Projects::client(), [Projects::product()]))->toStartWith('COMMITTED');
    $this->project = Project::query()->sole();
    $this->path = "/proyek/{$this->project->public_id}/penawaran";
});

afterEach(fn () => @unlink($this->hotFile));

function quotationsInteractionAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** The same request twice — the second its retry —, answering the second's outcome kind. */
function twice(object $test, string $path, array $body): string
{
    $test->actingAs($test->admin)->post($path, $body);
    $test->actingAs($test->admin)->post($path, $body);

    return (string) (quotationsInteractionAnswer()['kind'] ?? '');
}

it('saves the draft once, its command_id replayed answering the recorded outcome, other content a CONFLICT (INT-154; CI-05)', function () {
    $start = ['command_id' => (string) Str::uuid()];
    expect(twice($this, $this->path, $start))->toBe(Outcome::COMMITTED)->and(DB::table('quotation_revisions')->count())->toBe(1);

    $revision = QuotationRecords::latest($this->project->id);
    $handle = RowHandle::of('quotation_revision_lines', (int) DB::table('quotation_revision_lines')->value('id'));
    $body = ['command_id' => (string) Str::uuid(), 'revisi' => $revision['public_id'], 'lock_version' => $revision['lock_version'], 'berlaku' => CarbonImmutable::parse(BusinessDate::today())->addDays(7)->toDateString(),
        'baris' => [['baris' => $handle, 'jumlah' => '12', 'harga' => '20000']]];

    expect(twice($this, $this->path, $body))->toBe(Outcome::COMMITTED)
        ->and(QuotationRecords::latest($this->project->id)['lock_version'])->toBe($revision['lock_version'] + 1)
        ->and(DB::table('audit_events')->where('action', 'PRJ.QuotationDraftSaved')->count())->toBe(1);

    $this->actingAs($this->admin)->post($this->path, [...$body, 'baris' => [['baris' => $handle, 'jumlah' => '13', 'harga' => '20000']]]);
    expect(quotationsInteractionAnswer()['kind'])->toBe(Outcome::CONFLICT);
});

it('issues, revises and answers once each, every replay answering its recorded outcome with no second number drawn (INT-155–INT-157; CI-05; IG-05)', function () {
    $this->actingAs($this->admin)->post($this->path, ['command_id' => (string) Str::uuid()]);
    $revision = QuotationRecords::latest($this->project->id);
    $this->actingAs($this->admin)->post($this->path, ['command_id' => (string) Str::uuid(), 'revisi' => $revision['public_id'], 'lock_version' => $revision['lock_version'],
        'berlaku' => CarbonImmutable::parse(BusinessDate::today())->addDays(7)->toDateString(), 'baris' => [['baris' => RowHandle::of('quotation_revision_lines', (int) DB::table('quotation_revision_lines')->value('id')), 'jumlah' => '12', 'harga' => '20000']]]);
    $revision = QuotationRecords::latest($this->project->id);

    expect(twice($this, "{$this->path}/terbitkan", ['command_id' => (string) Str::uuid(), 'revisi' => $revision['public_id'], 'lock_version' => $revision['lock_version'], 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true]))
        ->toBe(Outcome::COMMITTED)
        ->and(Numbering::next($this->company, SequenceType::Doc01, $this->year))->toBe(2);

    expect(twice($this, "{$this->path}/jawaban", ['command_id' => (string) Str::uuid(), 'revisi' => $revision['public_id'], 'jawaban' => 'SETUJU', 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true]))
        ->toBe(Outcome::COMMITTED)
        ->and(DB::table('commercial_confirmations')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'PRJ.QuotationApproved')->count())->toBe(1);

    // "Buat Revisi" after the approval: a new draft needing re-approval (BR-QUO-02), made once.
    expect(twice($this, "{$this->path}/revisi", ['command_id' => (string) Str::uuid(), 'revisi' => $revision['public_id']]))->toBe(Outcome::COMMITTED)
        ->and(DB::table('quotation_revisions')->count())->toBe(2);
});
