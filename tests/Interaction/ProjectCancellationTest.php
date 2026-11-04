<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-056; INT-163; IG-05; CI-05): "Batalkan Proyek" through its route — the reason at its field, success, one `command_id` replayed with one effect, the same identifier with
// another body CONFLICT, a second cancellation REJECTED by AX-27.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->project = $this->world->project();
    $this->actor = Authorization::admin([$this->world->company->id], ['projects.view', 'projects.cancel']);
});

afterEach(fn () => @unlink($this->hotFile));

function cancellationInteraction(object $test, array $body): array
{
    $answer = $test->actingAs($test->actor)->followingRedirects()->post("/proyek/{$test->project->public_id}/batalkan", $body)->viewData('page')['flash']['answer'];
    Auth::forgetGuards();

    return $answer;
}

it('answers INT-163 with the reason at its field, one command_id replayed once, a changed body CONFLICT and a second cancellation REJECTED (MSG-40; CI-05; AX-27)', function () {
    $version = (int) DB::table('projects')->where('id', $this->project->id)->value('lock_version');
    $this->actingAs($this->actor)->post("/proyek/{$this->project->public_id}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => '', 'versi' => $version])->assertSessionHasErrors('alasan');

    $body = ['command_id' => (string) Str::uuid(), 'alasan' => 'Klien membatalkan kontrak', 'versi' => $version];
    expect(cancellationInteraction($this, $body)['kind'])->toBe('COMMITTED')
        ->and(cancellationInteraction($this, $body)['kind'])->toBe('COMMITTED')
        ->and(cancellationInteraction($this, [...$body, 'alasan' => 'Alasan lain sama sekali'])['kind'])->toBe('CONFLICT')
        ->and(cancellationInteraction($this, [...$body, 'command_id' => (string) Str::uuid(), 'versi' => $version + 1])['code'])->toBe('AX-27')
        ->and(DB::table('project_state_transitions')->where('project_id', $this->project->id)->where('transition_kind', 'CANCEL')->count())->toBe(1);
});
