<?php

use App\Support\Command\Outcome;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Administration;
use Tests\Support\Authorization;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-035; IG-01–IG-05; INT-164–INT-169; CI-05; ST-01; criterion 15): each checklist command server side — its
// success, its `command_id` replayed answering the recorded outcome with nothing written twice, the same identifier with
// other content a CONFLICT, a stale edit CONFLICT ST-01.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Projects::company();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view', 'evidence.view', 'requirements.manage', 'requirements.waive', 'decisions.supersede']);
    $this->owner = Authorization::owner();
    $this->project = Administration::project($this->admin->id, $this->company);
    $this->path = "/proyek/{$this->project->public_id}/persyaratan";
});

afterEach(fn () => @unlink($this->hotFile));

function requirementInteractionAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function requirementSentTwice(object $test, string $path, array $body, ?object $actor = null): string
{
    $test->actingAs($actor ?? $test->admin)->post($path, $body);
    $first = (string) (requirementInteractionAnswer()['kind'] ?? '');
    $test->actingAs($actor ?? $test->admin)->post($path, $body);

    return $first.' '.(string) (requirementInteractionAnswer()['kind'] ?? '');
}

it('adds, tightens and links once each, every replay answering its recorded outcome (INT-164; INT-165; CI-05)', function () {
    $add = ['command_id' => (string) Str::uuid(), 'jenis' => 'CATALOG_DOCUMENT', 'tipe_dokumen' => 'DOC-09', 'uraian' => 'SPK perusahaan', 'wajib' => false, 'cara' => 'EITHER', 'asli_klien' => false];
    expect(requirementSentTwice($this, $this->path, $add))->toBe(Outcome::COMMITTED.' '.Outcome::COMMITTED)
        ->and(DB::table('admin_requirements')->where('project_id', $this->project->id)->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'ADM.RequirementAdded')->count())->toBe(1);

    // The same identifier with other content is another request (CI-05).
    $this->actingAs($this->admin)->post($this->path, [...$add, 'uraian' => 'SPK lain']);
    expect(requirementInteractionAnswer()['kind'] ?? null)->toBe(Outcome::CONFLICT);

    $item = (int) DB::table('admin_requirements')->where('project_id', $this->project->id)->value('id');
    $tighten = ['command_id' => (string) Str::uuid(), 'persyaratan' => Administration::handle($item), 'lock_version' => 0, 'jenis' => 'CATALOG_DOCUMENT', 'tipe_dokumen' => 'DOC-09', 'uraian' => 'SPK perusahaan', 'wajib' => true, 'cara' => 'EITHER', 'asli_klien' => false];
    expect(requirementSentTwice($this, $this->path, $tighten))->toBe(Outcome::COMMITTED.' '.Outcome::COMMITTED)
        ->and(DB::table('admin_requirements')->where('id', $item)->value('lock_version'))->toBe(1);

    // A stale edit is CONFLICT ST-01 (ST-01).
    $this->actingAs($this->admin)->post($this->path, [...$tighten, 'command_id' => (string) Str::uuid(), 'cara' => 'GENERATED']);
    expect(requirementInteractionAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'code' => 'ST-01']);

    $version = Administration::document($this->project);
    $link = ['command_id' => (string) Str::uuid(), 'dokumen' => Administration::publicIdOf($version)];
    expect(requirementSentTwice($this, "{$this->path}/".Administration::handle($item).'/tautkan', $link))->toBe(Outcome::COMMITTED.' '.Outcome::COMMITTED)
        ->and(DB::table('admin_requirement_links')->where('admin_requirement_id', $item)->count())->toBe(1);

    // Its response lost and the document since voided, the replay still answers the recorded outcome (CI-05).
    DB::table('document_versions')->where('id', $version->id)->update(['state' => 'VOIDED', 'voided_by' => $this->admin->id, 'voided_at' => now(), 'voided_reason' => 'Salah pihak']);
    $this->actingAs($this->admin)->post("{$this->path}/".Administration::handle($item).'/tautkan', $link);
    expect(requirementInteractionAnswer()['kind'] ?? null)->toBe(Outcome::COMMITTED)
        ->and(DB::table('admin_requirement_links')->where('admin_requirement_id', $item)->count())->toBe(1);
    // A fresh link of the voided version is refused in place (BR-ADM-01).
    $this->actingAs($this->admin)->post("{$this->path}/".Administration::handle($item).'/tautkan', [...$link, 'command_id' => (string) Str::uuid()]);
    expect(requirementInteractionAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'BR-ADM-01']);
});

it('marks Tidak Berlaku, sets eligibility, relaxes and supersedes once each, every replay its recorded outcome (INT-166–INT-169; CI-05)', function () {
    $item = Administration::requirement($this->project);
    $handle = Administration::handle($item);

    expect(requirementSentTwice($this, "{$this->path}/{$handle}/kelayakan", ['command_id' => (string) Str::uuid(), 'lock_version' => 0, 'layak' => true], $this->owner))
        ->toBe(Outcome::COMMITTED.' '.Outcome::COMMITTED)
        ->and(DB::table('audit_events')->where('action', 'ADM.WaiverEligibilitySet')->count())->toBe(1);

    expect(requirementSentTwice($this, "{$this->path}/{$handle}/tidak-berlaku", ['command_id' => (string) Str::uuid(), 'alasan' => 'Tidak diminta sekolah']))
        ->toBe(Outcome::COMMITTED.' '.Outcome::COMMITTED)
        ->and(DB::table('admin_requirement_waivers')->where('admin_requirement_id', $item)->count())->toBe(1);

    $waiver = RowHandle::of('admin_requirement_waivers', (int) DB::table('admin_requirement_waivers')->where('admin_requirement_id', $item)->value('id'));
    expect(requirementSentTwice($this, "/keputusan/{$waiver}/ganti", ['command_id' => (string) Str::uuid(), 'proyek' => $this->project->public_id, 'alasan' => 'Salah catat']))
        ->toBe(Outcome::COMMITTED.' '.Outcome::COMMITTED)
        ->and(DB::table('admin_requirement_waivers')->where('admin_requirement_id', $item)->count())->toBe(2);

    $version = (int) DB::table('admin_requirements')->where('id', $item)->value('lock_version');
    expect(requirementSentTwice($this, "{$this->path}/{$handle}/longgarkan", ['command_id' => (string) Str::uuid(), 'lock_version' => $version, 'hapus' => false,
        'wajib' => false, 'cara' => 'EITHER', 'asli_klien' => false, 'alasan' => 'Sekolah menerima salinan'], $this->owner))
        ->toBe(Outcome::COMMITTED.' '.Outcome::COMMITTED)
        ->and(DB::table('audit_events')->where('action', 'ADM.RequirementRelaxed')->count())->toBe(1);

    // An optional item is removed once, its replay answering the recorded outcome.
    $version = (int) DB::table('admin_requirements')->where('id', $item)->value('lock_version');
    expect(requirementSentTwice($this, "{$this->path}/{$handle}/longgarkan", ['command_id' => (string) Str::uuid(), 'lock_version' => $version, 'hapus' => true, 'alasan' => 'Tidak diperlukan'], $this->owner))
        ->toBe(Outcome::COMMITTED.' '.Outcome::COMMITTED)
        ->and(DB::table('audit_events')->where('action', 'ADM.RequirementRemoved')->count())->toBe(1);
});
