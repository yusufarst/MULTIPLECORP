<?php

use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-031; IG-01–IG-05; INT-271; CI-05): "Tutup Kasus"'s server side — its success, one `command_id` replayed
// answering the recorded outcome with nothing written twice, the same identifier with other content a CONFLICT.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $company = Company::factory()->create();
    $this->case = CorrectionCase::factory()->create(['company_id' => $company->id]);
    $this->closer = Authorization::admin([$company->id], ['projects.view', 'cases.close']);
});

afterEach(fn () => @unlink($this->hotFile));

function casesInteractionAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('closes once, its command_id replayed answering the recorded outcome (INT-271; CI-05; IG-05)', function () {
    $body = ['command_id' => (string) Str::uuid(), 'lock_version' => 0, 'alasan' => 'Kasus selesai ditinjau.'];

    $this->actingAs($this->closer)->post("/dokumen/kasus/{$this->case->public_id}/tutup", $body)->assertRedirect();
    expect(casesInteractionAnswer()['kind'])->toBe(Outcome::COMMITTED);
    $audit = DB::table('audit_events')->count();

    $this->actingAs($this->closer)->post("/dokumen/kasus/{$this->case->public_id}/tutup", $body)->assertRedirect();
    expect(casesInteractionAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(DB::table('audit_events')->count())->toBe($audit)
        ->and(DB::table('audit_events')->where('action', 'OPS.CorrectionCaseClosed')->count())->toBe(1);

    $this->actingAs($this->closer)->post("/dokumen/kasus/{$this->case->public_id}/tutup", [...$body, 'alasan' => 'Alasan lain untuk ditutup.']);
    expect(casesInteractionAnswer()['kind'])->toBe(Outcome::CONFLICT);
});
