<?php

use App\Modules\ADM\Queries\ProjectChecklist;
use App\Modules\ADM\Services\AdministrationPredicate;
use App\Modules\ADM\Services\Readiness;
use App\Modules\ADM\Services\RequirementLifecycle;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Queries\ProjectWorkspace;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\GuardUpdate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Administration;
use Tests\Support\Authorization;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-035; ROUTE-164–ROUTE-169; WF-ADM-01; BR-ADM-01–BR-ADM-03; criteria 1–6, 9, 10, 12, 14): a checklist per
// project whose statuses are derived from links, waivers and the linked records — a draft only *Disiapkan*, a generated
// item satisfied by an issued and rendered version, a client original only by an authentic upload —; an Admin adding,
// tightening and linking, never touching eligibility or loosening; *Tidak Berlaku* only on an eligible item with its
// reason; the Owner's eligibility, relaxation and removal OWNER_ONLY; a waiver superseded with both kept, an Owner's
// decision refused to an Admin; the default invoice item open until invoiced; a revalidation reopening waivers; the
// administration predicate false while a required item is open.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Projects::company();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'projects.confirm', 'finance.view', 'evidence.view', 'requirements.manage', 'requirements.waive', 'decisions.supersede']);
    $this->owner = Authorization::owner();
    $this->project = Administration::project($this->admin->id, $this->company);
});

afterEach(fn () => @unlink($this->hotFile));

function admAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** ROUTE-164's add, or its tightening when the body names a requirement. */
function admSave(object $test, array $body = [], ?object $actor = null, ?Project $project = null): TestResponse
{
    return $test->actingAs($actor ?? $test->admin)->post('/proyek/'.($project ?? $test->project)->public_id.'/persyaratan', [
        'command_id' => (string) Str::uuid(), 'jenis' => 'CATALOG_DOCUMENT', 'tipe_dokumen' => 'DOC-09', 'uraian' => 'SPK perusahaan', 'wajib' => true, 'cara' => 'GENERATED', 'asli_klien' => false, ...$body,
    ]);
}

/** One of ROUTE-165–ROUTE-168 on a requirement of the project. */
function admPost(object $test, int $requirement, string $step, array $body, ?object $actor = null): TestResponse
{
    return $test->actingAs($actor ?? $test->admin)->post("/proyek/{$test->project->public_id}/persyaratan/".Administration::handle($requirement)."/{$step}", ['command_id' => (string) Str::uuid(), ...$body]);
}

function admChecklist(object $test, ?object $viewer = null, ?Project $project = null): array
{
    $project ??= $test->project;

    return app(ProjectChecklist::class)->checklist(($viewer ?? $test->admin)->id, new ProjectRef($project->id, $project->company_id, $project->public_id)) ?? [];
}

function admLockVersion(int $requirement): int
{
    return (int) DB::table('admin_requirements')->where('id', $requirement)->value('lock_version');
}

it('keeps a checklist per project, each status derived from its links and records, a draft only prepared (CAP-09; AC-09; BR-ADM-01; criteria 1, 2)', function () {
    $other = Administration::project($this->admin->id, $this->company);
    admSave($this)->assertRedirect();
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED']);
    admSave($this, ['jenis' => 'EXTERNAL_DOCUMENT', 'tipe_dokumen' => null, 'tipe_bukti' => 'CLIENT_SPK', 'uraian' => 'SPK asli dari sekolah', 'cara' => 'UPLOADED', 'asli_klien' => true], null, $other);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED']);

    $mine = DB::table('admin_requirements')->where('project_id', $this->project->id)->sole();
    expect(collect(admChecklist($this)['items'])->pluck('kind')->all())->toBe(['CATALOG_DOCUMENT'])
        ->and(collect(admChecklist($this, null, $other)['items'])->pluck('kind')->all())->toBe(['EXTERNAL_DOCUMENT'])
        ->and(Administration::status($this->project, (int) $mine->id))->toBe(Readiness::MISSING)
        // No stored status, fraction or progress (WF-ADM-01; PT-29).
        ->and(Schema::getColumnListing('admin_requirements'))->not->toContain('status')->not->toContain('progress');

    // A draft of its document prepares the item and never satisfies it, nor can it be linked (architecture challenge 15b).
    $draft = Administration::document($this->project, 'DOC-09', null, false);
    expect(Administration::status($this->project, (int) $mine->id))->toBe(Readiness::PREPARED);
    admPost($this, (int) $mine->id, 'tautkan', ['dokumen' => Administration::publicIdOf($draft)]);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'BR-ADM-01'])->and(DB::table('admin_requirement_links')->count())->toBe(0);

    // An issued version whose rendition is still pending is linked yet does not satisfy until rendered.
    $issued = Administration::document($this->project, 'DOC-09', 'PENDING');
    admPost($this, (int) $mine->id, 'tautkan', ['dokumen' => Administration::publicIdOf($issued)]);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(Administration::status($this->project, (int) $mine->id))->toBe(Readiness::PREPARED);

    Administration::rendered($issued);
    expect(Administration::status($this->project, (int) $mine->id))->toBe(Readiness::SATISFIED)
        ->and(admChecklist($this)['items'][0]['links'][0])->toMatchArray(['kind' => 'document', 'type' => 'DOC-09', 'number' => $issued->formatted_number]);

    // The same link twice is refused; a satisfied item no longer changes.
    admPost($this, (int) $mine->id, 'tautkan', ['dokumen' => Administration::publicIdOf($issued)]);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'NX-03']);
    admSave($this, ['persyaratan' => Administration::handle((int) $mine->id), 'lock_version' => admLockVersion((int) $mine->id), 'cara' => 'GENERATED']);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'WF-ADM-01']);
});

it('satisfies a client original only by an authentic upload, a reproduction staying open and why only to its rendition\'s viewers (BR-ADM-01; FS-12; GAP-034; criterion 3)', function () {
    $item = Administration::requirement($this->project, ['requirement_kind' => 'EXTERNAL_DOCUMENT', 'document_type_code' => null, 'evidence_type' => 'CLIENT_SPK',
        'description' => 'SPK asli dari sekolah', 'satisfaction_mode' => 'UPLOADED', 'requires_client_original' => true]);
    $company = Administration::document($this->project, 'DOC-09');

    // A company document never satisfies a client original.
    admPost($this, $item, 'tautkan', ['dokumen' => Administration::publicIdOf($company)]);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'BR-ADM-01']);

    $reproduction = Administration::evidence($this->company, 'CLIENT_SPK', Administration::renditionDigest($company));
    admPost($this, $item, 'tautkan', ['bukti' => $reproduction->public_id]);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(Administration::status($this->project, $item))->toBe(Readiness::MISSING)
        ->and(admChecklist($this)['items'][0]['reproduction'])->toBeTrue()
        // A viewer who may not see the company document's rendition (finance.view for DOC-09) is not told why.
        ->and(admChecklist($this, Authorization::admin([$this->company->id], ['projects.view', 'evidence.view']))['items'][0]['reproduction'])->toBeFalse()
        ->and(DB::table('evidence_links')->where('evidence_document_id', $reproduction->id)->where('admin_requirement_id', $item)->value('role'))->toBe('CLIENT_ORIGINAL');

    $authentic = Administration::evidence($this->company, 'CLIENT_SPK');
    admPost($this, $item, 'tautkan', ['bukti' => $authentic->public_id]);
    expect(Administration::status($this->project, $item))->toBe(Readiness::SATISFIED);

    // Evidence of another type, or of another company, is refused: the first by the mode, the second as unknown.
    $other = Administration::requirement($this->project, ['requirement_kind' => 'EXTERNAL_DOCUMENT', 'document_type_code' => null, 'evidence_type' => 'CLIENT_ORDER', 'satisfaction_mode' => 'UPLOADED']);
    admPost($this, $other, 'tautkan', ['bukti' => $authentic->public_id]);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'BR-ADM-01']);
    admPost($this, $other, 'tautkan', ['bukti' => Administration::evidence(Projects::company(), 'CLIENT_ORDER')->public_id])->assertSessionHasErrors('bukti');
});

it('lets an Admin add and tighten, refusing eligibility, loosening and the Owner\'s routes (OD-05; OD-06; ST-01; criterion 4)', function () {
    admSave($this, ['kelayakan' => true]);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'OD-05'])
        ->and(DB::table('admin_requirements')->where('project_id', $this->project->id)->count())->toBe(0);

    admSave($this, ['wajib' => false, 'cara' => 'EITHER']);
    $item = (int) DB::table('admin_requirements')->where('project_id', $this->project->id)->value('id');
    expect((bool) DB::table('admin_requirements')->where('id', $item)->value('waiver_eligible'))->toBeFalse();

    // Stricter: required, then uploaded only; a stale version CONFLICT.
    $tighten = fn (array $body, ?int $version = null) => admSave($this, ['persyaratan' => Administration::handle($item), 'lock_version' => $version ?? admLockVersion($item), ...$body]);
    $tighten(['wajib' => true, 'cara' => 'EITHER']);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED']);
    $tighten(['wajib' => true, 'cara' => 'UPLOADED'], admLockVersion($item) - 1);
    expect(admAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'ST-01']);
    $tighten(['wajib' => true, 'cara' => 'UPLOADED']);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED']);

    // Looser on a required non-waivable item is the Owner's.
    $tighten(['wajib' => false, 'cara' => 'UPLOADED']);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'OD-06']);
    $tighten(['wajib' => true, 'cara' => 'EITHER']);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'OD-06'])
        ->and(DB::table('admin_requirements')->where('id', $item)->first(['is_required', 'satisfaction_mode']))->toMatchObject(['is_required' => true, 'satisfaction_mode' => 'UPLOADED']);

    // The Owner's controls and the eligibility field are absent for the Admin, the routes answering as missing pages (IP-21).
    $checklist = admChecklist($this);
    expect($checklist['can'])->toMatchArray(['owner' => false, 'relax' => false])
        ->and($checklist['items'][0])->not->toHaveKey('waiver_eligible');
    admPost($this, $item, 'kelayakan', ['lock_version' => admLockVersion($item), 'layak' => true])->assertNotFound();
    admPost($this, $item, 'longgarkan', ['lock_version' => admLockVersion($item), 'hapus' => true, 'alasan' => 'Tidak diperlukan'])->assertNotFound();
});

it('marks only an eligible item Tidak Berlaku with its reason, the Owner deciding eligibility (BR-ADM-02; OD-05; criteria 5, 6)', function () {
    $item = Administration::requirement($this->project);

    admPost($this, $item, 'tidak-berlaku', ['alasan' => 'Sekolah tidak meminta SPK']);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'BR-ADM-02']);

    admPost($this, $item, 'kelayakan', ['lock_version' => admLockVersion($item), 'layak' => true], $this->owner);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(DB::table('audit_events')->where('action', 'ADM.WaiverEligibilitySet')->value('authority_class'))->toBe('OWNER_ONLY')
        ->and(admChecklist($this, $this->owner)['items'][0]['waiver_eligible'])->toBeTrue()
        ->and(admChecklist($this)['items'][0]['may_waive'])->toBeTrue();

    admPost($this, $item, 'tidak-berlaku', ['alasan' => ''])->assertSessionHasErrors('alasan');
    admPost($this, $item, 'tidak-berlaku', ['alasan' => 'Sekolah tidak meminta SPK']);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(Administration::status($this->project, $item))->toBe(Readiness::WAIVED)
        ->and(DB::table('admin_requirement_waivers')->where('admin_requirement_id', $item)->sole())->toMatchObject(['reason' => 'Sekolah tidak meminta SPK', 'decided_by' => $this->admin->id]);

    admPost($this, $item, 'tidak-berlaku', ['alasan' => 'Lagi']);
    expect(admAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'ST-02']);
});

it('leaves the relaxation and removal of a required item to the Owner, each an OWNER_ONLY event with its reason (OD-06; AZ-10; criterion 6)', function () {
    $item = Administration::requirement($this->project);

    admPost($this, $item, 'longgarkan', ['lock_version' => admLockVersion($item), 'hapus' => false, 'wajib' => false, 'cara' => 'EITHER', 'asli_klien' => false, 'alasan' => 'Sekolah menerima salinan'], $this->owner);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(DB::table('admin_requirements')->where('id', $item)->first(['is_required', 'satisfaction_mode']))->toMatchObject(['is_required' => false, 'satisfaction_mode' => 'EITHER'])
        ->and(DB::table('audit_events')->where('action', 'ADM.RequirementRelaxed')->first(['authority_class', 'reason']))->toMatchObject(['authority_class' => 'OWNER_ONLY', 'reason' => 'Sekolah menerima salinan']);

    admPost($this, $item, 'longgarkan', ['lock_version' => admLockVersion($item), 'hapus' => true, 'alasan' => 'Tidak diminta klien'], $this->owner);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(DB::table('admin_requirements')->where('id', $item)->first(['state', 'removed_reason']))->toMatchObject(['state' => 'REMOVED', 'removed_reason' => 'Tidak diminta klien'])
        ->and(DB::table('audit_events')->where('action', 'ADM.RequirementRemoved')->value('authority_class'))->toBe('OWNER_ONLY')
        ->and(admChecklist($this)['items'])->toBe([]);
});

it('supersedes a waiver recorded in error with both decisions kept, an Owner\'s waiver refused to an Admin (CM-27; OD-15; criterion 10)', function () {
    $item = Administration::requirement($this->project, ['waiver_eligible' => true]);
    admPost($this, $item, 'tidak-berlaku', ['alasan' => 'Salah pilih']);
    $waiver = (int) DB::table('admin_requirement_waivers')->where('admin_requirement_id', $item)->value('id');
    $supersede = fn (int $id, ?object $actor = null) => $this->actingAs($actor ?? $this->admin)->post('/keputusan/'.RowHandle::of('admin_requirement_waivers', $id).'/ganti', [
        'command_id' => (string) Str::uuid(), 'proyek' => $this->project->public_id, 'alasan' => 'Persyaratan ini tetap diminta sekolah',
    ]);

    $supersede($waiver);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(Administration::status($this->project, $item))->toBe(Readiness::MISSING)
        ->and(DB::table('admin_requirement_waivers')->where('admin_requirement_id', $item)->orderBy('id')->get(['supersedes_waiver_id', 'reopened_by_correction'])->map(fn ($row) => (array) $row)->all())
        ->toBe([['supersedes_waiver_id' => null, 'reopened_by_correction' => false], ['supersedes_waiver_id' => $waiver, 'reopened_by_correction' => true]])
        ->and(collect(admChecklist($this)['items'][0]['waivers'])->pluck('current')->all())->toBe([true, false]);

    // The superseded decision is no longer the one in force.
    $supersede($waiver);
    expect(admAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'ST-02']);

    // An Owner's waiver is superseded only by the Owner (OD-15).
    $owned = Administration::requirement($this->project, ['waiver_eligible' => true]);
    admPost($this, $owned, 'tidak-berlaku', ['alasan' => 'Keputusan Pemilik'], $this->owner);
    $ownerWaiver = (int) DB::table('admin_requirement_waivers')->where('admin_requirement_id', $owned)->value('id');
    $supersede($ownerWaiver);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'OD-15']);
    $supersede($ownerWaiver, $this->owner);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED']);

    // A decision of another project answers as unknown (AZ-11).
    $this->actingAs($this->admin)->post('/keputusan/'.RowHandle::of('admin_requirement_waivers', $ownerWaiver).'/ganti', [
        'command_id' => (string) Str::uuid(), 'proyek' => Administration::project($this->admin->id, $this->company)->public_id, 'alasan' => 'Salah proyek',
    ])->assertNotFound();
});

it('adds the default invoice item at the confirmation, open until the invoiced value covers the confirmed value (L-27; criterion 12)', function () {
    $evidence = Administration::evidence($this->company, 'CLIENT_SPK');
    $this->actingAs($this->admin)->post("/proyek/{$this->project->public_id}/konfirmasi", [
        'command_id' => (string) Str::uuid(), 'lock_version' => $this->project->fresh()?->lock_version, 'bukti' => $evidence->public_id, 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true,
    ]);
    $invoice = DB::table('admin_requirements')->where('project_id', $this->project->id)->where('is_default_invoice_item', true)->sole();
    $confirmed = (string) DB::table('project_commercial_balances')->where('project_id', $this->project->id)->value('confirmed_sales_value');

    expect($invoice)->toMatchObject(['is_required' => true, 'waiver_eligible' => false])
        ->and(Administration::status($this->project, (int) $invoice->id))->toBe(Readiness::MISSING);

    // The invoice item is no link's to satisfy and no edit's to change.
    admPost($this, (int) $invoice->id, 'tautkan', ['bukti' => $evidence->public_id]);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'WF-ADM-01']);

    GuardUpdate::apply(DB::connection(), 'project_commercial_balances', [['key' => ['project_id' => $this->project->id], 'deltas' => ['invoiced_sales_value' => number_format((float) $confirmed - 1, 2, '.', '')]]]);
    expect(Administration::status($this->project, (int) $invoice->id))->toBe(Readiness::MISSING);
    GuardUpdate::apply(DB::connection(), 'project_commercial_balances', [['key' => ['project_id' => $this->project->id], 'deltas' => ['invoiced_sales_value' => '1.00']]]);
    expect(Administration::status($this->project, (int) $invoice->id))->toBe(Readiness::SATISFIED);
});

it('reads the administration predicate false while a required item is open, one blocker per kind, never for an optional item (WF-PRJ-03; AC-21; criterion 14)', function () {
    $states = fn (bool $money = true) => app(AdministrationPredicate::class)->states([$this->project->id], $money)[$this->project->id][Predicates::ADMINISTRATION] ?? null;
    Administration::requirement($this->project, ['is_required' => false]);
    expect($states()?->applies)->toBeFalse();

    Administration::requirement($this->project);
    Administration::requirement($this->project);
    Administration::requirement($this->project, ['requirement_kind' => 'FREE_TEXT', 'document_type_code' => null, 'description' => 'Foto serah terima', 'satisfaction_mode' => 'UPLOADED']);
    $invoice = Administration::requirement($this->project, ['requirement_kind' => 'FREE_TEXT', 'document_type_code' => null, 'description' => 'Invoice', 'is_default_invoice_item' => true]);

    expect($states()?->met())->toBeFalse()
        ->and(array_map(fn ($item) => [$item->key, $item->params, $item->blocker], $states()->items))->toBe([
            ['projects.item.requirements_catalog', ['jumlah' => '2'], true],
            ['projects.item.requirements_other', ['jumlah' => '1'], true],
            ['projects.item.requirements_invoice', [], true],
        ])
        // The invoice item is a money item, never given to a viewer without finance.view (PJ-13).
        ->and(collect($states(false)->items)->pluck('key')->all())->not->toContain('projects.item.requirements_invoice');

    DB::table('admin_requirements')->where('project_id', $this->project->id)->where('id', '!=', $invoice)->update(['waiver_eligible' => true]);
    foreach (DB::table('admin_requirements')->where('project_id', $this->project->id)->where('id', '!=', $invoice)->pluck('id') as $id) {
        admPost($this, (int) $id, 'tidak-berlaku', ['alasan' => 'Tidak diminta']);
    }

    expect(collect($states()->items)->pluck('key')->all())->toBe(['projects.item.requirements_invoice']);
});

it('reopens the project\'s standing waivers on a revalidation, each kept and marked as reopened (SF-REVAL; BR-ADM-03; criterion 9)', function () {
    $item = Administration::requirement($this->project, ['waiver_eligible' => true]);
    admPost($this, $item, 'tidak-berlaku', ['alasan' => 'Tidak diminta']);
    expect(Administration::status($this->project, $item))->toBe(Readiness::WAIVED);

    $request = new CommandRequest((string) Str::uuid(), 'test.revalidate', $this->admin->id, $this->company->id, 'projects.view', Fingerprint::of('test.revalidate', null, ['project' => $this->project->id]));
    $outcome = app(CommandEnvelope::class)->run($request, function (CommandContext $context): CommandResult {
        $context->lock((new LockSet)->add('projects', ['id' => $this->project->id]));
        app(RequirementLifecycle::class)->revalidated($context, $this->project->id);

        return new CommandResult('project', $this->project->id);
    });

    expect($outcome->kind)->toBe('COMMITTED')
        ->and(Administration::status($this->project, $item))->toBe(Readiness::MISSING)
        ->and(DB::table('admin_requirement_waivers')->where('admin_requirement_id', $item)->orderBy('id')->pluck('reopened_by_correction')->all())->toBe([false, true])
        ->and(DB::table('audit_events')->where('action', 'ADM.WaiverReopened')->value('authority_class'))->toBe('SYSTEM');
});

it('shows the checklist in PG-011\'s documents group (D-IA-10; J-ADM-01)', function () {
    Administration::requirement($this->project);
    $group = app(ProjectWorkspace::class)->documents($this->admin->id, $this->project);

    expect($group['checklist']['items'])->toHaveCount(1)
        ->and($group['checklist']['can'])->toMatchArray(['manage' => true, 'waive' => true, 'supersede' => true])
        ->and($group['checklist']['options']['document_types'])->toContain('DOC-09')
        ->and(app(ProjectWorkspace::class)->documents(Authorization::admin([Projects::company()->id], ['projects.view'])->id, $this->project)['checklist'])->toBeNull();
});

it('links only a document of the item\'s own project, read through its subject (BR-ADM-01)', function () {
    $item = Administration::requirement($this->project);
    $other = Administration::document(Administration::project($this->admin->id, $this->company));

    admPost($this, $item, 'tautkan', ['dokumen' => Administration::publicIdOf($other)])->assertSessionHasErrors('dokumen');
    expect(collect(admChecklist($this)['options']['documents'])->pluck('id')->all())->not->toContain(Administration::publicIdOf($other))
        ->and(DB::table('admin_requirement_links')->count())->toBe(0);
});

it('refuses an Admin any loosening, of a waiver-eligible or optional item too, and the removal of a required waiver-eligible item (OD-06; WF-ADM-01)', function () {
    $eligible = Administration::requirement($this->project, ['waiver_eligible' => true]);
    $optional = Administration::requirement($this->project, ['is_required' => false, 'satisfaction_mode' => 'EITHER']);
    $loosen = fn (int $item, array $body) => admSave($this, ['persyaratan' => Administration::handle($item), 'lock_version' => admLockVersion($item), ...$body]);

    $loosen($eligible, ['wajib' => false]);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'OD-06']);
    $loosen($optional, ['wajib' => false, 'cara' => 'UPLOADED', 'asli_klien' => false]);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED']);
    $loosen($optional, ['wajib' => false, 'cara' => 'EITHER']);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'OD-06'])
        ->and(DB::table('audit_events')->where('action', 'ADM.RequirementRelaxed')->count())->toBe(0);

    admPost($this, $eligible, 'longgarkan', ['lock_version' => admLockVersion($eligible), 'hapus' => true, 'alasan' => 'Tidak diminta'], $this->owner);
    expect(admAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'WF-ADM-01'])
        ->and(DB::table('admin_requirements')->where('id', $eligible)->value('state'))->toBe('ACTIVE');

    // The Owner's own change is audited with the Owner's authority, a tightening included.
    admPost($this, $optional, 'longgarkan', ['lock_version' => admLockVersion($optional), 'hapus' => false, 'wajib' => true, 'cara' => 'UPLOADED', 'asli_klien' => false, 'alasan' => 'Diminta sekolah'], $this->owner);
    expect(DB::table('audit_events')->where('action', 'ADM.RequirementTightened')->orderByDesc('id')->value('authority_class'))->toBe('OWNER_ONLY');
});

it('shows the default invoice item only with finance.view, on PG-011 and PG-039 (PJ-13)', function () {
    Administration::requirement($this->project, ['requirement_kind' => 'FREE_TEXT', 'document_type_code' => null, 'description' => 'Invoice', 'is_default_invoice_item' => true]);
    Administration::requirement($this->project);
    $reader = Authorization::admin([$this->company->id], ['projects.view']);
    $listed = function (object $viewer): array {
        $props = [];
        $this->actingAs($viewer)->get('/dokumen/administrasi')->assertInertia(function (AssertableInertia $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return collect($props['rows'])->pluck('default_invoice')->sort()->values()->all();
    };

    expect(collect(admChecklist($this, $reader)['items'])->pluck('default_invoice')->all())->toBe([false])
        ->and(collect(admChecklist($this)['items'])->pluck('default_invoice')->sort()->values()->all())->toBe([false, true])
        ->and($listed($reader))->toBe([false])
        ->and($listed($this->admin))->toBe([false, true]);
});

it('reopens a standing waiver when the Owner withdraws eligibility, so a later grant revives nothing (BR-ADM-02; OD-05)', function () {
    $item = Administration::requirement($this->project, ['waiver_eligible' => true]);
    admPost($this, $item, 'tidak-berlaku', ['alasan' => 'Tidak diminta']);
    expect(Administration::status($this->project, $item))->toBe(Readiness::WAIVED);

    admPost($this, $item, 'kelayakan', ['lock_version' => admLockVersion($item), 'layak' => false], $this->owner);
    expect(Administration::status($this->project, $item))->toBe(Readiness::MISSING)
        ->and(DB::table('admin_requirement_waivers')->where('admin_requirement_id', $item)->orderBy('id')->pluck('reopened_by_correction')->all())->toBe([false, true]);

    admPost($this, $item, 'kelayakan', ['lock_version' => admLockVersion($item), 'layak' => true], $this->owner);
    expect(Administration::status($this->project, $item))->toBe(Readiness::MISSING);
    admPost($this, $item, 'tidak-berlaku', ['alasan' => 'Keputusan baru']);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED'])->and(Administration::status($this->project, $item))->toBe(Readiness::WAIVED);
});

it('keeps the default invoice item open while the invoiced value exceeds the confirmed value less the cancelled scope (L-27)', function () {
    Authorization::grant($this->admin, 'projects.cancel_scope');
    $evidence = Administration::evidence($this->company, 'CLIENT_SPK');
    $this->actingAs($this->admin)->post("/proyek/{$this->project->public_id}/konfirmasi", [
        'command_id' => (string) Str::uuid(), 'lock_version' => $this->project->fresh()?->lock_version, 'bukti' => $evidence->public_id, 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true,
    ]);
    $invoice = (int) DB::table('admin_requirements')->where('project_id', $this->project->id)->where('is_default_invoice_item', true)->value('id');
    $confirmed = (string) DB::table('project_commercial_balances')->where('project_id', $this->project->id)->value('confirmed_sales_value');
    GuardUpdate::apply(DB::connection(), 'project_commercial_balances', [['key' => ['project_id' => $this->project->id], 'deltas' => ['invoiced_sales_value' => $confirmed]]]);
    expect(Administration::status($this->project, $invoice))->toBe(Readiness::SATISFIED);

    $line = RowHandle::of('project_lines', (int) DB::table('project_lines')->where('project_id', $this->project->id)->where('line_no', 2)->value('id'));
    $this->actingAs($this->admin)->post("/proyek/{$this->project->public_id}/batalkan-sisa", [
        'command_id' => (string) Str::uuid(), 'baris' => [['baris' => $line, 'jumlah' => '1']], 'alasan' => 'Klien membatalkan', 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true,
    ]);
    expect(admAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(Administration::status($this->project, $invoice))->toBe(Readiness::MISSING);
});

it('tells why a client original stays open only to a viewer of the matched rendition\'s own company and family (PJ-15; SM-23)', function () {
    $item = Administration::requirement($this->project, ['requirement_kind' => 'EXTERNAL_DOCUMENT', 'document_type_code' => null, 'evidence_type' => 'CLIENT_SPK',
        'satisfaction_mode' => 'UPLOADED', 'requires_client_original' => true]);
    $foreign = Projects::company();
    $rendition = Administration::document(Administration::project(Authorization::admin([$foreign->id], ['projects.view', 'projects.manage', 'finance.view'])->id, $foreign));
    admPost($this, $item, 'tautkan', ['bukti' => Administration::evidence($this->company, 'CLIENT_SPK', Administration::renditionDigest($rendition))->public_id]);

    // The upload reproduces another company's rendition: open for everyone, its reason only for that company's viewers.
    expect(Administration::status($this->project, $item))->toBe(Readiness::MISSING)
        ->and(admChecklist($this)['items'][0]['reproduction'])->toBeFalse();
    Authorization::grantCompany($this->admin, $foreign->id);
    Authorization::forget();
    expect(admChecklist($this)['items'][0]['reproduction'])->toBeTrue();
});

it('offers the evidence of the types its open items take, searchable by issuer or number (PT-27; IP-17)', function () {
    Administration::requirement($this->project, ['requirement_kind' => 'EXTERNAL_DOCUMENT', 'document_type_code' => null, 'evidence_type' => 'CLIENT_SPK', 'satisfaction_mode' => 'UPLOADED']);
    $old = Administration::evidence($this->company, 'CLIENT_SPK', null, 'SMA Lama Sekali');
    $new = Administration::evidence($this->company, 'CLIENT_SPK');
    $tax = Administration::evidence($this->company, 'NPWP');
    $offered = fn (?string $term = null): array => collect(app(ProjectWorkspace::class)->documents($this->admin->id, $this->project, $term)['checklist']['options']['evidence'])->pluck('id')->all();

    expect($offered())->toBe([$new->public_id, $old->public_id])
        ->and($offered())->not->toContain($tax->public_id)
        ->and($offered('lama sek'))->toBe([$old->public_id])
        ->and($offered('100%_'))->toBe([]);
});
