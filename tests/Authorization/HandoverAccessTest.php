<?php

use App\Modules\IAM\Models\User;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\HandoverWorld;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-047; AZ-01; AZ-11; IG-01; SM-01; SM-04; CS-03; criterion 7): ROUTE-033 and ROUTE-208 with `handover.record`, ROUTE-209
// with `fulfillment.correct` (ADM+), the BAST with `documents.issue`; without each the route refused and its control absent;
// another company's project, handover and line unknown.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = HandoverWorld::make();
    $this->project = $this->world->project();
    $this->line = HandoverWorld::line($this->project);
    expect(HandoverWorld::record($this->world->actorId, $this->project->id, $this->line, '4'))->toBe('COMMITTED');
    $this->handover = DB::table('service_handovers')->where('project_id', $this->project->id)->sole();
    $this->handed = DB::table('service_handover_lines')->where('service_handover_id', $this->handover->id)->sole();
    $this->body = ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'penerima' => 'Bu Sari', 'baris' => [['baris' => RowHandle::of('project_lines', $this->line), 'jumlah' => '1', 'lingkup' => 'Tahap dua']]];
    $this->reversal = ['command_id' => (string) Str::uuid(), 'baris' => RowHandle::of('service_handover_lines', $this->handed->id), 'alasan' => 'Salah catat'];
});

afterEach(fn () => @unlink($this->hotFile));

it('refuses PG-033 and the handover without handover.record (AZ-01; IG-01; SM-04; criterion 7)', function () {
    $viewer = Authorization::admin([$this->world->company->id], ['projects.view', 'projects.manage', 'fulfillment.correct']);

    $this->actingAs($viewer)->get("/proyek/{$this->project->public_id}/serah-terima")->assertForbidden();
    $this->actingAs($viewer)->post("/proyek/{$this->project->public_id}/serah-terima", $this->body)->assertForbidden();

    expect(HandoverWorld::handedOver($this->line))->toBe('4.000');
});

it('refuses the reversal without fulfillment.correct, its control absent; holding it alone opens no page (AZ-01; SM-04; criterion 7)', function () {
    $recorder = Authorization::admin([$this->world->company->id], ['projects.view', 'handover.record']);

    $this->actingAs($recorder)->post("/proyek/{$this->project->public_id}/serah-terima/{$this->handover->public_id}/balikkan", $this->reversal)->assertForbidden();
    $this->actingAs($recorder)->get("/proyek/{$this->project->public_id}/serah-terima")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('handovers.0.lines.0.can_reverse', false)->where('can.record', true));

    expect(DB::table('service_handover_lines')->whereNotNull('reverses_line_id')->count())->toBe(0);
});

it('offers the BAST\'s issue only with documents.issue, the handover\'s own capability not enough (TL-06; SM-04)', function () {
    $recorder = Authorization::admin([$this->world->company->id], ['projects.view', 'handover.record']);
    $this->actingAs($recorder)->get("/proyek/{$this->project->public_id}/serah-terima")->assertInertia(fn (AssertableInertia $page) => $page->where('handovers.0.can_issue', false));
    $this->actingAs($recorder)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-08', 'subjek' => ['service_handover_id' => $this->handover->public_id]])->assertForbidden();

    $this->actingAs(User::query()->findOrFail($this->world->actorId))->get("/proyek/{$this->project->public_id}/serah-terima")->assertInertia(fn (AssertableInertia $page) => $page->where('handovers.0.can_issue', true));
});

it('gives no journey link to PG-033 without handover.record (AZ-01; PL-3)', function () {
    $viewer = Authorization::admin([$this->world->company->id], ['projects.view']);

    $this->actingAs($viewer)->get("/proyek/{$this->project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('journey.next.key', 'projects.next.service')->where('journey.next.href', null));
});

it('answers another company\'s project, handover and BAST subject as unknown, each command writing nothing (SM-01; CS-03; CS-04; AZ-11)', function () {
    $stranger = HandoverWorld::make();
    $actor = User::query()->findOrFail($stranger->actorId);

    $this->actingAs($actor)->get("/proyek/{$this->project->public_id}/serah-terima")->assertNotFound();
    $this->actingAs($actor)->post("/proyek/{$this->project->public_id}/serah-terima", $this->body)->assertNotFound();
    $this->actingAs($actor)->post("/proyek/{$this->project->public_id}/serah-terima/{$this->handover->public_id}/balikkan", $this->reversal)->assertNotFound();
    $this->actingAs($actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-08', 'subjek' => ['service_handover_id' => $this->handover->public_id]])->assertNotFound();

    // Its own project names no handover of ours.
    $own = $stranger->project();
    $this->actingAs($actor)->post("/proyek/{$own->public_id}/serah-terima/{$this->handover->public_id}/balikkan", $this->reversal)->assertNotFound();

    expect(HandoverWorld::handedOver($this->line))->toBe('4.000')->and(DB::table('service_handovers')->count())->toBe(1);
});

it('does not cite evidence of another company or another family in the handover (PJ-08; CS-04)', function () {
    $stranger = HandoverWorld::make();
    $foreign = EvidenceDocumentFactory::new()->createOne(['company_id' => $stranger->company->id, 'evidence_type' => 'DELIVERY_PROOF']);
    $order = EvidenceDocumentFactory::new()->createOne(['company_id' => $this->world->company->id, 'evidence_type' => 'SUPPLIER_BILL']);
    $actor = User::query()->findOrFail($this->world->actorId);

    $this->actingAs($actor)->post("/proyek/{$this->project->public_id}/serah-terima", [...$this->body, 'bukti' => $foreign->public_id])->assertSessionHasErrors('bukti');
    $this->actingAs($actor)->post("/proyek/{$this->project->public_id}/serah-terima", [...$this->body, 'command_id' => (string) Str::uuid(), 'bukti' => $order->public_id])->assertSessionHasErrors('bukti');

    expect(DB::table('evidence_links')->whereNotNull('service_handover_id')->count())->toBe(0);
});
