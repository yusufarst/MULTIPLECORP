<?php

use App\Modules\FUL\Services\HandoverPredicate;
use App\Modules\IAM\Models\User;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\HandoverWorld;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-047; ROUTE-033; ROUTE-208; ROUTE-209; AX-09; WF-FUL-04; J-FUL-04; L-39; L-49; C-30; CM-27; criteria 1, 2, 5, 6):
// a service line's handover records its date, work, recipient and evidence and no stock event, a service-only project reaching
// it with no warehouse step; handovers repeat up to the pinned demand, the excess refused with the remainder; a likely duplicate
// refused with its matches until confirmed with a reason; one exact reversal line, at most once. Also DOC-08's precondition on
// PG-033 through ROUTE-221 and ROUTE-222 (criterion 4), and the journey's *Jasa* stage leading here.

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
});

afterEach(fn () => @unlink($this->hotFile));

function handoverAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** @param  array<string, mixed>  $overrides */
function postHandover(object $test, array $overrides = [], ?object $project = null, ?int $line = null): TestResponse
{
    return $test->actingAs($test->actor)->post('/proyek/'.($project ?? $test->project)->public_id.'/serah-terima', [
        'command_id' => (string) Str::uuid(),
        'tanggal' => BusinessDate::today(),
        'penerima' => 'Bu Sari',
        'baris' => [['baris' => RowHandle::of('project_lines', $line ?? $test->line), 'jumlah' => '4', 'lingkup' => 'Instalasi tahap satu']],
        ...$overrides,
    ]);
}

function standingHandover(object $test): object
{
    return DB::table('service_handover_lines as l')->join('service_handovers as h', 'h.id', '=', 'l.service_handover_id')->where('h.project_id', $test->project->id)->whereNull('l.reverses_line_id')
        ->orderByDesc('l.id')->first(['l.id as line', 'h.id as handover', 'h.public_id']);
}

function reverseHandover(object $test, object $handover, string $reason = 'Salah catat jumlah'): TestResponse
{
    return $test->actingAs($test->actor)->post("/proyek/{$test->project->public_id}/serah-terima/{$handover->public_id}/balikkan", [
        'command_id' => (string) Str::uuid(), 'baris' => RowHandle::of('service_handover_lines', $handover->line), 'alasan' => $reason,
    ]);
}

it('records a handover with its date, work, recipient and evidence and moves no stock, then answers on PG-011 (BR-INV-11; FS-05; AC-20; NAV-033; criterion 1)', function () {
    $evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $this->world->company->id, 'evidence_type' => 'DELIVERY_PROOF']);
    $before = ['movements' => DB::table('stock_movements')->count(), 'entries' => DB::table('stock_movement_entries')->count(), 'deliveries' => DB::table('deliveries')->count(), 'purchases' => DB::table('purchases')->count()];

    postHandover($this, ['bukti' => $evidence->public_id])->assertRedirect("/proyek/{$this->project->public_id}");

    $handover = DB::table('service_handovers')->where('project_id', $this->project->id)->sole();
    $line = DB::table('service_handover_lines')->where('service_handover_id', $handover->id)->sole();

    expect(handoverAnswer())->toMatchArray(['kind' => 'COMMITTED', 'text' => 'Serah terima jasa tercatat.'])
        ->and($handover->recipient_name)->toBe('Bu Sari')
        ->and($handover->business_date)->toBe(BusinessDate::today())
        ->and($handover->recorded_by)->toBe($this->world->actorId)
        ->and($line->qty_base)->toBe('4.000')
        ->and($line->entered_qty)->toBe('4.000')
        ->and($line->scope_note)->toBe('Instalasi tahap satu')
        ->and(HandoverWorld::handedOver($this->line))->toBe('4.000')
        ->and(DB::table('evidence_links')->where('service_handover_id', $handover->id)->where('evidence_document_id', $evidence->id)->count())->toBe(1)
        ->and(['movements' => DB::table('stock_movements')->count(), 'entries' => DB::table('stock_movement_entries')->count(), 'deliveries' => DB::table('deliveries')->count(), 'purchases' => DB::table('purchases')->count()])->toBe($before);
    $event = DB::table('audit_events')->where('action', 'FUL.HandoverRecorded')->sole();
    expect($event->actor_user_id)->toBe($this->world->actorId)->and($event->authority_class)->toBe('ADM')->and($event->entity_public_id)->toBe($this->project->public_id);
});

it('takes a handover without evidence, which the signed BAST itself may later prove (AC-07; L-19)', function () {
    postHandover($this)->assertRedirect("/proyek/{$this->project->public_id}");

    expect(DB::table('evidence_links')->whereNotNull('service_handover_id')->count())->toBe(0)
        ->and(HandoverWorld::handedOver($this->line))->toBe('4.000');
});

it('repeats partial handovers up to the pinned demand and refuses the excess with the remainder, the input kept in place (PX-01; PX-03; C-30; criterion 2)', function () {
    postHandover($this, ['tanggal' => BusinessDate::today()]);
    postHandover($this, ['baris' => [['baris' => RowHandle::of('project_lines', $this->line), 'jumlah' => '5', 'lingkup' => 'Tahap dua']]]);
    expect(HandoverWorld::handedOver($this->line))->toBe('9.000');

    postHandover($this, ['baris' => [['baris' => RowHandle::of('project_lines', $this->line), 'jumlah' => '2', 'lingkup' => 'Tahap tiga']]])
        ->assertRedirect("/proyek/{$this->project->public_id}/serah-terima");

    expect(handoverAnswer()['kind'])->toBe('REJECTED')
        ->and(handoverAnswer()['code'])->toBe('C-30')
        ->and(handoverAnswer()['text'])->toContain('1.000')
        ->and(HandoverWorld::handedOver($this->line))->toBe('9.000')
        ->and(DB::table('service_handovers')->where('project_id', $this->project->id)->count())->toBe(2);

    // Finer than the product's scale, whatever remains: refused by its own rule (C-09).
    postHandover($this, ['baris' => [['baris' => RowHandle::of('project_lines', $this->line), 'jumlah' => '0.5', 'lingkup' => 'Tahap tiga']]]);
    expect(handoverAnswer()['code'])->toBe('C-09')->and(HandoverWorld::handedOver($this->line))->toBe('9.000');
});

it('refuses a goods line, an unconfirmed line and a closed project, naming each (L-39; FS-05; L-20; L-11)', function () {
    $mixed = $this->world->project(['WAREHOUSE', 'SERVICE']);
    postHandover($this, [], $mixed, HandoverWorld::line($mixed, 1))->assertRedirect("/proyek/{$mixed->public_id}/serah-terima");
    expect(handoverAnswer()['code'])->toBe('L-39');

    $draft = $this->world->project(['SERVICE'], '10', false);
    postHandover($this, [], $draft, HandoverWorld::line($draft));
    expect(handoverAnswer()['code'])->toBe('L-20');

    DB::table('projects')->where('id', $this->project->id)->update(['state' => 'CANCELLED']);
    postHandover($this);
    expect(handoverAnswer()['code'])->toBe('L-11')->and(DB::table('service_handovers')->count())->toBe(0);
});

it('answers validation at the fields: a future date, zero, no work stated, a line of another project (IP-14; C-50; MSG-12)', function () {
    $other = $this->world->project();

    postHandover($this, ['tanggal' => '2999-01-01'])->assertSessionHasErrors('tanggal');
    postHandover($this, ['baris' => [['baris' => RowHandle::of('project_lines', $this->line), 'jumlah' => '0', 'lingkup' => 'Tahap satu']]])->assertSessionHasErrors('baris.0.jumlah');
    postHandover($this, ['baris' => [['baris' => RowHandle::of('project_lines', $this->line), 'jumlah' => '1', 'lingkup' => '']]])->assertSessionHasErrors('baris.0.lingkup');
    postHandover($this, ['penerima' => ''])->assertSessionHasErrors('penerima');
    postHandover($this, ['baris' => [['baris' => RowHandle::of('project_lines', HandoverWorld::line($other)), 'jumlah' => '1', 'lingkup' => 'Tahap satu']]])->assertSessionHasErrors('baris.0.baris');
    postHandover($this, ['bukti' => (string) Str::uuid()])->assertSessionHasErrors('bukti');

    expect(DB::table('service_handovers')->count())->toBe(0);
});

it('refuses a likely duplicate with its matches until the actor confirms it with a reason, under the acknowledged action (L-49; BD-13; QS-21; INT-015; criterion 6)', function () {
    postHandover($this);
    $first = standingHandover($this);

    postHandover($this)->assertRedirect("/proyek/{$this->project->public_id}/serah-terima");
    $refused = handoverAnswer();
    expect($refused['kind'])->toBe('REJECTED')->and($refused['code'])->toBe('L-49')
        ->and($refused['state']['matches'][0]['id'])->toBe($first->public_id)
        ->and($refused['text'])->toContain('Bu Sari')
        ->and(HandoverWorld::handedOver($this->line))->toBe('4.000');

    // Named without a reason, or not naming the match: still refused.
    postHandover($this, ['acknowledged' => [$first->public_id]]);
    expect(handoverAnswer()['code'])->toBe('L-49');
    postHandover($this, ['reason' => 'Ini tahap yang berbeda']);
    expect(handoverAnswer()['code'])->toBe('L-49');

    // Another quantity or date is no likely duplicate.
    postHandover($this, ['baris' => [['baris' => RowHandle::of('project_lines', $this->line), 'jumlah' => '1', 'lingkup' => 'Sisa kecil']]]);
    expect(handoverAnswer()['kind'])->toBe('COMMITTED');

    postHandover($this, ['acknowledged' => [$first->public_id], 'reason' => 'Ini tahap yang berbeda'])->assertRedirect("/proyek/{$this->project->public_id}");
    expect(handoverAnswer()['kind'])->toBe('COMMITTED')
        ->and(HandoverWorld::handedOver($this->line))->toBe('9.000');
    $event = DB::table('audit_events')->where('action', 'FUL.HandoverDuplicateConfirmed')->sole();
    expect($event->reason)->toBe('Ini tahap yang berbeda');
});

it('reverses a line by one exact reversal line with its reason, the guard moved back, at most once, ADM+ for the Owner\'s review (CM-27; C-30; AZ-10; criterion 5)', function () {
    postHandover($this);
    $handover = standingHandover($this);

    reverseHandover($this, $handover)->assertRedirect("/proyek/{$this->project->public_id}/serah-terima");
    $reversal = DB::table('service_handover_lines')->where('reverses_line_id', $handover->line)->sole();
    $event = DB::table('audit_events')->where('action', 'FUL.HandoverReversed')->sole();

    expect(handoverAnswer()['kind'])->toBe('COMMITTED')
        ->and($reversal->qty_base)->toBe('4.000')
        ->and($reversal->entered_qty)->toBe('4.000')
        ->and($reversal->scope_note)->toBe('Salah catat jumlah')
        ->and(HandoverWorld::handedOver($this->line))->toBe('0.000')
        ->and($event->authority_class)->toBe('ADM_PLUS')
        ->and($event->reason)->toBe('Salah catat jumlah')
        ->and($event->metadata)->toContain('REVERSES')
        ->and($event->metadata)->toContain($handover->public_id);

    reverseHandover($this, $handover);
    expect(handoverAnswer()['kind'])->toBe('REJECTED')->and(handoverAnswer()['code'])->toBe('C-30')
        ->and(DB::table('service_handover_lines')->where('reverses_line_id', $handover->line)->count())->toBe(1);

    // A reversal line itself is no line to reverse.
    reverseHandover($this, (object) ['public_id' => $handover->public_id, 'line' => $reversal->id]);
    expect(handoverAnswer()['code'])->toBe('C-30')->and(HandoverWorld::handedOver($this->line))->toBe('0.000');
});

it('lets the same handover be recorded again once its twin is reversed: a reversed line is no likely duplicate (L-49; CM-27)', function () {
    postHandover($this);
    reverseHandover($this, standingHandover($this));
    postHandover($this)->assertRedirect("/proyek/{$this->project->public_id}");

    expect(handoverAnswer()['kind'])->toBe('COMMITTED')->and(HandoverWorld::handedOver($this->line))->toBe('4.000');
});

it('requires a reason for the reversal and answers a handover or line of elsewhere as unknown (IP-09; MSG-40; AZ-11)', function () {
    postHandover($this);
    $handover = standingHandover($this);

    reverseHandover($this, $handover, '')->assertSessionHasErrors('alasan');
    $this->actingAs($this->actor)->post("/proyek/{$this->project->public_id}/serah-terima/".Str::uuid().'/balikkan', [
        'command_id' => (string) Str::uuid(), 'baris' => RowHandle::of('service_handover_lines', $handover->line), 'alasan' => 'Salah catat',
    ])->assertNotFound();
    $this->actingAs($this->actor)->post("/proyek/{$this->project->public_id}/serah-terima/{$handover->public_id}/balikkan", [
        'command_id' => (string) Str::uuid(), 'baris' => str_repeat('0', 32), 'alasan' => 'Salah catat',
    ])->assertSessionHasErrors('baris');

    expect(HandoverWorld::handedOver($this->line))->toBe('4.000');
});

it('shows PG-033 with the lines\' progress, the handovers and their reversal lines, and no stock figure (ROUTE-033; SCR-31; FS-05)', function () {
    postHandover($this);
    $handover = standingHandover($this);
    postHandover($this, ['baris' => [['baris' => RowHandle::of('project_lines', $this->line), 'jumlah' => '2', 'lingkup' => 'Tahap dua']]]);
    reverseHandover($this, $handover);

    $this->actingAs($this->actor)->get("/proyek/{$this->project->public_id}/serah-terima")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('FUL/ServiceHandover', false)
        ->where('project.id', $this->project->public_id)
        ->where('lines.0.demand', '10.000')
        ->where('lines.0.handed_over', '2.000')
        ->where('lines.0.remainder', '8.000')
        ->where('handovers.0.lines.0.reversal', null)
        ->where('handovers.0.lines.0.can_reverse', true)
        ->where('handovers.1.id', $handover->public_id)
        ->where('handovers.1.lines.0.reversal.reason', 'Salah catat jumlah')
        ->where('handovers.1.lines.0.can_reverse', false)
        ->where('handovers.1.can_issue', false)
        ->where('can.record', true)
        ->where('paper.draft', true)
        ->where('paper.number', null)
        ->where('paper.layout', 'DOC-08')
        ->missing('stock')
        ->missing('lines.0.available'));
});

it('offers no form on a closed project, and only the service lines (L-11; L-39)', function () {
    $mixed = $this->world->project(['WAREHOUSE', 'SERVICE']);
    $this->actingAs($this->actor)->get("/proyek/{$mixed->public_id}/serah-terima")->assertInertia(fn (AssertableInertia $page) => $page->has('lines', 1)->where('lines.0.no', 2)->where('can.record', true));

    DB::table('projects')->where('id', $this->project->id)->update(['state' => 'CANCELLED']);
    $this->actingAs($this->actor)->get("/proyek/{$this->project->public_id}/serah-terima")->assertInertia(fn (AssertableInertia $page) => $page->where('can.record', false));
});

it('leads the journey\'s *Jasa* stage to PG-033 and counts the open service lines in the fulfilment predicate (J-FUL-04; L-39; BR-PRJ-02)', function () {
    $this->actingAs($this->actor)->get("/proyek/{$this->project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('journey.current', 'service')
        ->where('journey.next.key', 'projects.next.service')
        ->where('journey.next.href', "/proyek/{$this->project->public_id}/serah-terima"));
    $predicates = app(HandoverPredicate::class)->states([$this->project->id], false)[$this->project->id]['fulfilment'];
    expect($predicates->items[0]->key)->toBe('projects.item.handovers_open')->and($predicates->items[0]->params)->toBe(['jumlah' => '1'])->and($predicates->met())->toBeFalse();

    postHandover($this, ['baris' => [['baris' => RowHandle::of('project_lines', $this->line), 'jumlah' => '10', 'lingkup' => 'Selesai seluruhnya']]]);

    expect(app(HandoverPredicate::class)->states([$this->project->id], false)[$this->project->id]['fulfilment']->met())->toBeTrue();
    $this->actingAs($this->actor)->get("/proyek/{$this->project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->whereNot('journey.current', 'service'));
});

it('issues the BAST in place on PG-033 from the handover\'s confirmed facts, numbered at issue, a second issue CONFLICT with the number (WF-DOC-01; BR-DOC-04; BD-06; IP-08; criterion 4)', function () {
    $this->world->numbering();
    postHandover($this);
    $handover = standingHandover($this);
    $page = "/proyek/{$this->project->public_id}/serah-terima";

    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-08', 'subjek' => ['service_handover_id' => $handover->public_id], 'kembali' => $page])->assertRedirect($page);
    expect(handoverAnswer()['kind'])->toBe('COMMITTED');
    $document = DB::table('documents')->where('service_handover_id', $handover->handover)->sole();
    $version = DB::table('document_versions')->where('document_id', $document->id)->sole();
    expect($version->state)->toBe('DRAFT')->and($version->formatted_number)->toBeNull();

    $facts = '';
    $this->actingAs($this->actor)->get($page)->assertInertia(function (AssertableInertia $inertia) use (&$facts, $version, $document) {
        $inertia->where('handovers.0.bast.document', $document->public_id)->where('handovers.0.bast.draft.id', $version->public_id)->where('handovers.0.can_issue', true);
        $facts = $inertia->toArray()['props']['handovers'][0]['bast']['draft']['facts'];
    });

    $issue = ['command_id' => (string) Str::uuid(), 'draf' => $version->public_id, 'lock_version' => $version->lock_version, 'tanggal' => BusinessDate::today(), 'isi' => $facts, 'kembali' => $page];
    $this->actingAs($this->actor)->post("/dokumen/{$document->public_id}/terbitkan", $issue)->assertRedirect($page);
    expect(handoverAnswer()['kind'])->toBe('COMMITTED');
    $issued = DB::table('document_versions')->where('document_id', $document->id)->sole();
    $payload = json_decode($issued->payload, true);
    expect($issued->state)->toBe('ISSUED')
        ->and($issued->formatted_number)->toStartWith('BAST/')
        ->and($payload['paper']['recipient']['name'])->toBe('Bu Sari')
        ->and($payload['paper']['lines'])->toHaveCount(1)
        ->and($payload['paper']['lines'][0]['note'])->toBe('Instalasi tahap satu')
        ->and(collect($payload['paper']['references'])->pluck('label')->all())->toContain('Tanggal Serah Terima')
        ->and(DB::table('stock_movements')->count())->toBe(0);
    $this->actingAs($this->actor)->get($page)->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('handovers.0.bast.issued', $issued->formatted_number)->where('handovers.0.can_issue', false)->where('handovers.0.bast_affected', false));

    $this->actingAs($this->actor)->post("/dokumen/{$document->public_id}/terbitkan", [...$issue, 'command_id' => (string) Str::uuid()])->assertRedirect($page);
    expect(handoverAnswer()['kind'])->toBe('CONFLICT')->and(handoverAnswer()['code'])->toBe('BD-06')->and(handoverAnswer()['text'])->toContain($issued->formatted_number);
});

it('refuses the BAST of a handover wholly reversed and flags an issued one a reversal affects, PG-036 revising or voiding it (BR-DOC-04; CM-27; WORKFLOWS §8; criteria 4, 5)', function () {
    $this->world->numbering();
    postHandover($this);
    $handover = standingHandover($this);
    $page = "/proyek/{$this->project->public_id}/serah-terima";

    // Issued first, then reversed: flagged, its issued version untouched.
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-08', 'subjek' => ['service_handover_id' => $handover->public_id], 'kembali' => $page]);
    $document = DB::table('documents')->where('service_handover_id', $handover->handover)->sole();
    $version = DB::table('document_versions')->where('document_id', $document->id)->sole();
    $this->actingAs($this->actor)->post("/dokumen/{$document->public_id}/terbitkan", ['command_id' => (string) Str::uuid(), 'draf' => $version->public_id, 'lock_version' => $version->lock_version, 'tanggal' => BusinessDate::today(), 'kembali' => $page]);
    reverseHandover($this, $handover);

    $this->actingAs($this->actor)->get($page)->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('handovers.0.bast_affected', true)->where('handovers.0.can_issue', false));
    expect(DB::table('document_versions')->where('document_id', $document->id)->value('state'))->toBe('ISSUED');

    // A fresh handover reversed before any BAST: its draft can be prepared, never issued from no facts.
    postHandover($this);
    $second = standingHandover($this);
    reverseHandover($this, $second);
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-08', 'subjek' => ['service_handover_id' => $second->public_id], 'kembali' => $page]);
    $draftDocument = DB::table('documents')->where('service_handover_id', $second->handover)->sole();
    $draft = DB::table('document_versions')->where('document_id', $draftDocument->id)->sole();
    $this->actingAs($this->actor)->post("/dokumen/{$draftDocument->public_id}/terbitkan", ['command_id' => (string) Str::uuid(), 'draf' => $draft->public_id, 'lock_version' => $draft->lock_version, 'tanggal' => BusinessDate::today(), 'kembali' => $page])->assertRedirect($page);

    expect(handoverAnswer()['kind'])->toBe('REJECTED')->and(handoverAnswer()['code'])->toBe('WF-DOC-01')
        ->and(DB::table('document_versions')->where('document_id', $draftDocument->id)->value('formatted_number'))->toBeNull();
});

it('answers on PG-036 when the request names no page, or another page, to come back to (NAV-036; ROUTE-222)', function () {
    postHandover($this);
    $handover = standingHandover($this);

    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-08', 'subjek' => ['service_handover_id' => $handover->public_id], 'kembali' => 'https://contoh.test/lain'])
        ->assertRedirect('/dokumen/'.DB::table('documents')->where('service_handover_id', $handover->handover)->value('public_id'));
});
