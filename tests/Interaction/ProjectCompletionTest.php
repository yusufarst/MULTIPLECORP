<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-055; INT-161; INT-162; IG-05; CI-05): each command through its route — success, a rejection named, one `command_id` replayed with one effect, the same identifier with another body
// CONFLICT, a missing reason at its field.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->project = $this->world->project();
    $this->owner = Authorization::owner();
});

afterEach(fn () => @unlink($this->hotFile));

function completionInteraction(object $test, string $url, array $body): array
{
    $answer = $test->actingAs($test->owner)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
    Auth::forgetGuards();

    return $answer;
}

it('answers INT-161 with its rejection named, then one command_id replayed once (C-39; CI-05)', function () {
    $url = "/proyek/{$this->project->public_id}/selesaikan";
    $refused = completionInteraction($this, $url, ['command_id' => (string) Str::uuid()]);
    expect([$refused['kind'], $refused['code']])->toBe(['REJECTED', 'C-39']);

    InvoiceWorld::readyToComplete($this->project->id);
    $body = ['command_id' => (string) Str::uuid()];
    expect(completionInteraction($this, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(completionInteraction($this, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(DB::table('project_state_transitions')->where('project_id', $this->project->id)->where('transition_kind', 'COMPLETE_NORMAL')->count())->toBe(1);
});

it('answers INT-162 with the reason at its field, one command_id replayed once, a changed body CONFLICT (MSG-40; CI-05)', function () {
    $url = "/proyek/{$this->project->public_id}/selesaikan-paksa";
    $hash = $this->actingAs($this->owner)->get("/proyek/{$this->project->public_id}/penyelesaian")->viewData('page')['props']['force']['hash'];
    $this->actingAs($this->owner)->post($url, ['command_id' => (string) Str::uuid(), 'snapshot' => $hash, 'alasan' => ''])->assertSessionHasErrors('alasan');

    $body = ['command_id' => (string) Str::uuid(), 'snapshot' => $hash, 'alasan' => 'Klien menutup kerja sama'];
    expect(completionInteraction($this, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(completionInteraction($this, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(completionInteraction($this, $url, [...$body, 'alasan' => 'Alasan lain sama sekali'])['kind'])->toBe('CONFLICT')
        ->and(DB::table('project_state_transitions')->where('project_id', $this->project->id)->where('transition_kind', 'FORCE_COMPLETE')->count())->toBe(1);
});
