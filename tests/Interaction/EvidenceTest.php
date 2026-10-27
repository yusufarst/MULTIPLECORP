<?php

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\Command\Outcome;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Evidence;
use Tests\Support\Files;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-030; IG-01–IG-05; INT-228–INT-231; CI-05): each control's server side — present with `evidence.upload`
// and absent without it, its success, and one `command_id` replayed answering the recorded outcome with nothing written
// twice; the same identifier with other content a CONFLICT; a warning's second submission a new intent (INT-015).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Storage::fake('files');
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->actor = Authorization::admin([$this->company->id], ['projects.view', 'evidence.view', 'evidence.upload']);
    $this->project = Project::factory()->create(['company_id' => $this->company->id]);
    $this->actingAs($this->actor)->post('/bukti', Evidence::registration(Evidence::upload($this->actor, $this->company->id), $this->company->public_id));
    $this->evidence = EvidenceDocument::query()->sole();
});

afterEach(fn () => @unlink($this->hotFile));

/** Each INT row's request: its path and payload for the shared evidence. */
function evidenceInteraction(object $test, string $row): array
{
    $id = $test->evidence->public_id;

    return match ($row) {
        'INT-229' => ['/bukti', Evidence::registration(Evidence::upload($test->actor, $test->company->id), $test->company->public_id)],
        'INT-230' => ["/bukti/{$id}/versi", ['file' => Evidence::upload($test->actor, $test->company->id)->public_id, 'note' => 'Pindaian ulang']],
        default => ["/bukti/{$id}/tautan", ['action' => 'link', 'project' => $test->project->public_id, 'role' => 'SUPPORTING']],
    };
}

it('commits each command once, and its command_id replayed answers the recorded outcome writing nothing (CI-05; IG-05)', function (string $row) {
    [$path, $payload] = evidenceInteraction($this, $row);
    $payload = [...$payload, 'command_id' => (string) Str::uuid()];

    $this->actingAs($this->actor)->post($path, $payload)->assertRedirect();
    expect(Evidence::answer()['kind'] ?? null)->toBe(Outcome::COMMITTED);
    $counts = fn (): array => [DB::table('evidence_documents')->count(), DB::table('evidence_document_versions')->count(), DB::table('evidence_links')->count(), DB::table('audit_events')->count()];
    $before = $counts();
    $this->actingAs($this->actor)->get('/dokumen/bukti')->assertOk();

    $this->actingAs($this->actor)->post($path, $payload)->assertRedirect();
    expect(Evidence::answer()['kind'] ?? null)->toBe(Outcome::COMMITTED)
        ->and($counts())->toBe($before);

    $this->actingAs($this->actor)->post($path, [...$payload, 'issuer' => 'Lain', 'note' => 'Lain', 'role' => 'REVIEWED'])->assertRedirect();
    expect(Evidence::answer()['kind'] ?? null)->toBe(Outcome::CONFLICT);
})->with(['INT-229', 'INT-230', 'INT-231']);

it('answers ROUTE-228 without a command identity, nothing in the command log (INT-228; RB-03)', function () {
    $before = DB::table('command_log')->count();

    $this->actingAs($this->actor)->post('/berkas', ['purpose' => 'bukti', 'company' => $this->company->public_id,
        'file' => new UploadedFile(Files::path(Evidence::pdf()), 'b.pdf', null, null, true)], ['Accept' => 'application/json'])->assertCreated();

    expect(DB::table('command_log')->count())->toBe($before);
});

it('offers PG-038\'s commands with evidence.upload and answers them as forbidden without it (IG-01)', function () {
    $reader = Authorization::admin([$this->company->id], ['projects.view', 'evidence.view']);

    $this->actingAs($reader)->get('/dokumen/bukti')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('auth.capabilities', fn ($held) => ! collect($held)->contains('evidence.upload')));
    $this->actingAs($reader)->post('/bukti', Evidence::registration(Evidence::upload($this->actor, $this->company->id), $this->company->public_id))->assertForbidden();
    $this->actingAs($reader)->post("/bukti/{$this->evidence->public_id}/tautan", ['command_id' => (string) Str::uuid(), 'action' => 'replace', 'version' => 1])->assertForbidden();
});
