<?php

use App\Modules\IAM\Models\User;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\HandoverWorld;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-047; IG-01–IG-05; INT-208; INT-209; INT-221; INT-222; CI-05; criterion 2): the handover, its reversal and the BAST
// server side — each success once, its `command_id` replayed answering the recorded outcome with nothing written twice, the same
// identifier with other content a CONFLICT; a refused handover replayed answers its current state.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = HandoverWorld::make();
    $this->actor = User::query()->findOrFail($this->world->actorId);
    $this->project = $this->world->project();
    $this->line = HandoverWorld::line($this->project);
    $this->body = ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'penerima' => 'Bu Sari', 'baris' => [['baris' => RowHandle::of('project_lines', $this->line), 'jumlah' => '4', 'lingkup' => 'Instalasi tahap satu']]];
});

afterEach(fn () => @unlink($this->hotFile));

function handoverInteractionAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('hands over once for one command_id sent twice, the same identifier with another quantity a CONFLICT (INT-208; CI-05; PX-03; criterion 2)', function () {
    $path = "/proyek/{$this->project->public_id}/serah-terima";

    $this->actingAs($this->actor)->post($path, $this->body);
    expect(handoverInteractionAnswer()['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->post($path, $this->body);
    expect(handoverInteractionAnswer()['kind'])->toBe('COMMITTED')
        ->and(HandoverWorld::handedOver($this->line))->toBe('4.000')
        ->and(DB::table('service_handovers')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'FUL.HandoverRecorded')->count())->toBe(1);

    $this->actingAs($this->actor)->post($path, [...$this->body, 'baris' => [['baris' => RowHandle::of('project_lines', $this->line), 'jumlah' => '5', 'lingkup' => 'Instalasi tahap satu']]]);
    expect(handoverInteractionAnswer()['kind'])->toBe('CONFLICT')->and(HandoverWorld::handedOver($this->line))->toBe('4.000');
});

it('replays a refused handover with its current state: the matches and the remainder (INT-208; INT-015; CI-05)', function () {
    $path = "/proyek/{$this->project->public_id}/serah-terima";
    $this->actingAs($this->actor)->post($path, $this->body);
    $duplicate = [...$this->body, 'command_id' => (string) Str::uuid()];

    $this->actingAs($this->actor)->post($path, $duplicate);
    $first = handoverInteractionAnswer();
    $this->actingAs($this->actor)->post($path, $duplicate);
    $replayed = handoverInteractionAnswer();
    expect($first['code'])->toBe('L-49')->and($replayed['code'])->toBe('L-49')->and($replayed['state']['matches'])->toBe($first['state']['matches']);

    $above = [...$this->body, 'command_id' => (string) Str::uuid(), 'baris' => [['baris' => RowHandle::of('project_lines', $this->line), 'jumlah' => '7', 'lingkup' => 'Tahap dua']]];
    $this->actingAs($this->actor)->post($path, $above);
    $this->actingAs($this->actor)->post($path, $above);
    expect(handoverInteractionAnswer()['code'])->toBe('C-30')->and(handoverInteractionAnswer()['text'])->toContain('6.000');
});

it('reverses once for one command_id sent twice (INT-209; CI-05; C-30)', function () {
    $this->actingAs($this->actor)->post("/proyek/{$this->project->public_id}/serah-terima", $this->body);
    $handover = DB::table('service_handovers')->sole();
    $line = DB::table('service_handover_lines')->sole();
    $path = "/proyek/{$this->project->public_id}/serah-terima/{$handover->public_id}/balikkan";
    $body = ['command_id' => (string) Str::uuid(), 'baris' => RowHandle::of('service_handover_lines', $line->id), 'alasan' => 'Salah catat'];

    $this->actingAs($this->actor)->post($path, $body);
    expect(handoverInteractionAnswer()['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->post($path, $body);
    expect(handoverInteractionAnswer()['kind'])->toBe('COMMITTED')
        ->and(DB::table('service_handover_lines')->whereNotNull('reverses_line_id')->count())->toBe(1)
        ->and(HandoverWorld::handedOver($this->line))->toBe('0.000');

    $this->actingAs($this->actor)->post($path, [...$body, 'alasan' => 'Alasan lain']);
    expect(handoverInteractionAnswer()['kind'])->toBe('CONFLICT');
});

it('prepares the BAST\'s draft once and finds it again on the next press, one draft per chain (INT-221; DATABASE §4.10)', function () {
    $this->actingAs($this->actor)->post("/proyek/{$this->project->public_id}/serah-terima", $this->body);
    $handover = DB::table('service_handovers')->sole();
    $page = "/proyek/{$this->project->public_id}/serah-terima";
    $prepare = ['jenis' => 'DOC-08', 'subjek' => ['service_handover_id' => $handover->public_id], 'kembali' => $page];
    $command = (string) Str::uuid();

    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => $command, ...$prepare])->assertRedirect($page);
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => $command, ...$prepare])->assertRedirect($page);
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), ...$prepare])->assertRedirect($page);

    expect(handoverInteractionAnswer()['kind'])->toBe('COMMITTED')
        ->and(DB::table('documents')->where('service_handover_id', $handover->id)->count())->toBe(1)
        ->and(DB::table('document_versions')->where('state', 'DRAFT')->count())->toBe(1);
});
