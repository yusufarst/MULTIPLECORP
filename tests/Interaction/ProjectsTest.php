<?php

use App\Modules\PRJ\Models\Project;
use App\Support\Command\Outcome;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-032; IG-01–IG-05; INT-151–INT-153; CI-05): "Buat Proyek", "Ubah proyek" and "Buang Draf" server side —
// each one's success, its `command_id` replayed answering the recorded outcome with nothing written twice and no second
// number drawn, the same identifier with other content a CONFLICT.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Projects::company();
    $this->client = Projects::client();
    $this->goods = Projects::product();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view']);
});

afterEach(fn () => @unlink($this->hotFile));

function projectsInteractionAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('creates once, its command_id replayed answering the recorded outcome with no second number drawn (INT-151; CI-05; IG-05)', function () {
    $payload = Projects::payload($this->company, $this->client, [$this->goods]);

    $this->actingAs($this->admin)->post('/proyek', $payload)->assertRedirect();
    $project = Project::query()->sole();
    $this->actingAs($this->admin)->post('/proyek', $payload)->assertRedirect("/proyek/{$project->public_id}");

    expect(projectsInteractionAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(Project::query()->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'PRJ.ProjectCreated')->count())->toBe(1)
        ->and(DB::table('number_sequences')->where('company_id', $this->company->id)->value('next_value'))->toEqual(2);

    $this->actingAs($this->admin)->post('/proyek', [...$payload, 'judul' => 'Judul lain']);
    expect(projectsInteractionAnswer()['kind'])->toBe(Outcome::CONFLICT)->and(Project::query()->count())->toBe(1);
});

it('amends once, its command_id replayed answering the recorded outcome (INT-152; CI-05)', function () {
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods]));
    $project = Project::query()->sole();
    $line = RowHandle::of('project_lines', (int) DB::table('project_lines')->value('id'));
    $payload = Projects::payload($this->company, $this->client, [], ['lock_version' => $project->lock_version, 'judul' => 'Judul diubah',
        'baris' => [['baris' => $line, 'produk' => $this->goods['product']->public_id, 'satuan' => $this->goods['base']->code, 'jumlah' => '10', 'harga' => '15000', 'mode' => 'WAREHOUSE']]]);

    $this->actingAs($this->admin)->put("/proyek/{$project->public_id}", $payload);
    $version = $project->fresh()?->lock_version;
    $this->actingAs($this->admin)->put("/proyek/{$project->public_id}", $payload);

    expect(projectsInteractionAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and($project->fresh()?->lock_version)->toBe($version)
        ->and(DB::table('audit_events')->where('action', 'PRJ.ProjectAmended')->count())->toBe(1);
});

it('discards once, its command_id replayed answering the recorded outcome with one tombstone (INT-153; CI-05)', function () {
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['pic' => null]));
    $project = Project::query()->sole();
    $body = ['command_id' => (string) Str::uuid()];

    $this->actingAs($this->admin)->post("/proyek/{$project->public_id}/hapus-draf", $body)->assertRedirect('/proyek');
    expect(projectsInteractionAnswer()['kind'])->toBe(Outcome::COMMITTED)->and(DB::table('retired_numbers')->count())->toBe(1);
});
