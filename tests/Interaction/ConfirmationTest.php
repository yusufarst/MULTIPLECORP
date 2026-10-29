<?php

use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use App\Support\RowHandle;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-034; IG-01–IG-05; INT-158–INT-160; CI-05; M-09; M-10): "Konfirmasi Proyek", "Batalkan Sisa" and "Ubah Cara
// Pemenuhan" server side — each one's success, its `command_id` replayed answering the recorded outcome with nothing
// written twice, the same identifier with other content a CONFLICT.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Projects::company();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'projects.confirm', 'projects.cancel_scope', 'finance.view', 'evidence.view']);
    expect(Projects::create($this->admin->id, $this->company, Projects::client(), [Projects::product(), Projects::product()]))->toStartWith('COMMITTED');
    $this->project = Project::query()->sole();
    $this->evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $this->company->id, 'evidence_type' => 'CLIENT_ORDER']);
    $this->path = "/proyek/{$this->project->public_id}";
});

afterEach(fn () => @unlink($this->hotFile));

function confirmationInteractionAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function sentTwice(object $test, string $path, array $body): string
{
    $test->actingAs($test->admin)->post($path, $body);
    $test->actingAs($test->admin)->post($path, $body);

    return (string) (confirmationInteractionAnswer()['kind'] ?? '');
}

function handleOf(object $test, int $number): string
{
    return RowHandle::of('project_lines', (int) DB::table('project_lines')->where('project_id', $test->project->id)->where('line_no', $number)->value('id'));
}

it('confirms, cancels and re-plans once each, every replay answering its recorded outcome (INT-158–INT-160; CI-05)', function () {
    $confirm = ['command_id' => (string) Str::uuid(), 'lock_version' => $this->project->lock_version, 'bukti' => $this->evidence->public_id, 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true];
    expect(sentTwice($this, "{$this->path}/konfirmasi", $confirm))->toBe(Outcome::COMMITTED)
        ->and(DB::table('commercial_confirmations')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'PRJ.ProjectConfirmed')->count())->toBe(1);

    $cancel = ['command_id' => (string) Str::uuid(), 'baris' => [['baris' => handleOf($this, 1), 'jumlah' => '1']], 'alasan' => 'Klien mengurangi', 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true];
    expect(sentTwice($this, "{$this->path}/batalkan-sisa", $cancel))->toBe(Outcome::COMMITTED)
        ->and(DB::table('line_scope_cancellations')->count())->toBe(1);

    $this->actingAs($this->admin)->post("{$this->path}/batalkan-sisa", [...$cancel, 'alasan' => 'Alasan lain']);
    expect(confirmationInteractionAnswer()['kind'])->toBe(Outcome::CONFLICT);

    $replan = ['command_id' => (string) Str::uuid(), 'baris' => handleOf($this, 2), 'cara' => 'DROPSHIP', 'alasan' => 'Pemasok kirim langsung'];
    expect(sentTwice($this, "{$this->path}/ubah-pemenuhan", $replan))->toBe(Outcome::COMMITTED)
        ->and(DB::table('project_lines')->whereNotNull('split_from_line_id')->count())->toBe(1);
});
