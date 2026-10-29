<?php

use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-06 and TL-14 (TASK-034; PERMISSIONS_MATRIX §4 `projects.confirm`, `projects.cancel_scope`, `projects.manage`; §7.2
// rows 29–32, 35–37; PJ-01; PJ-12; AZ-01; AZ-11; SM-04; criteria 8, 12): each command only with its capability and
// another company's project unknown; PG-011's controls present with their capability and absent without; prices and
// values only with `finance.view`, availability only with `stock.view`.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Projects::company();
    $this->owner = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'projects.confirm', 'projects.cancel_scope', 'finance.view', 'stock.view', 'evidence.view']);
    expect(Projects::create($this->owner->id, $this->company, Projects::client(), [Projects::product()]))->toStartWith('COMMITTED');
    $this->project = Project::query()->sole();
    $this->evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $this->company->id, 'evidence_type' => 'CLIENT_SPK']);
    $this->path = "/proyek/{$this->project->public_id}";
    $this->line = RowHandle::of('project_lines', (int) DB::table('project_lines')->value('id'));
});

afterEach(fn () => @unlink($this->hotFile));

it('runs each command only with its capability and answers another company\'s project as unknown (ROUTE-158–ROUTE-160; SM-04; AZ-11)', function () {
    $viewer = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'evidence.view']);
    $confirm = fn (object $actor) => $this->actingAs($actor)->post("{$this->path}/konfirmasi", ['command_id' => (string) Str::uuid(), 'lock_version' => $this->project->lock_version, 'bukti' => $this->evidence->public_id, 'tanggal' => BusinessDate::today()]);

    $confirm($viewer)->assertForbidden();
    $this->actingAs($viewer)->post("{$this->path}/batalkan-sisa", ['command_id' => (string) Str::uuid(), 'baris' => [['baris' => $this->line, 'jumlah' => '1']], 'alasan' => 'Uji', 'tanggal' => BusinessDate::today()])->assertForbidden();
    $this->actingAs($viewer)->post("{$this->path}/ubah-pemenuhan", ['command_id' => (string) Str::uuid(), 'baris' => $this->line, 'cara' => 'DROPSHIP', 'alasan' => 'Uji'])->assertForbidden();
    $confirm(Authorization::admin([Projects::company()->id], ['projects.view', 'projects.confirm', 'evidence.view']))->assertNotFound();

    expect(DB::table('commercial_confirmations')->count())->toBe(0);
});

it('shows PG-011\'s commands with their capability, values with finance.view and availability with stock.view (AZ-01; PJ-01; PJ-12)', function () {
    $this->actingAs($this->owner)->post("{$this->path}/konfirmasi", ['command_id' => (string) Str::uuid(), 'lock_version' => $this->project->lock_version, 'bukti' => $this->evidence->public_id, 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true]);

    $this->actingAs($this->owner)->get($this->path)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('fulfilment.lines.0.can', ['cancel' => true, 'replan' => true])->has('fulfilment.lines.0.price')->has('fulfilment.lines.0.available'));

    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view']))->get($this->path)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('fulfilment.lines.0.can', ['cancel' => false, 'replan' => false])->missing('fulfilment.lines.0.price')->missing('fulfilment.lines.0.value')
        ->missing('fulfilment.lines.0.available')->where('fulfilment.can.confirm', false)->where('fulfilment.evidence', []));
});
