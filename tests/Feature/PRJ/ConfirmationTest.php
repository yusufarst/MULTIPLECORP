<?php

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\CommercialConfirmation;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-034; ROUTE-158–ROUTE-160; AX-13; AX-14; WF-PRJ-02; WF-FUL-01; criteria 1–3, 5–8): "Konfirmasi Proyek" on the
// client's order evidence pins each line's conversion, price and tax in base units with the guards and the SIPLAH lock, a
// second one CONFLICT; "Batalkan Sisa" cancels a remainder in place with its reason, never above it; "Ubah Cara
// Pemenuhan" splits the unstarted remainder into a new line of another mode with the same snapshot; PG-011 shows each
// line's progress by mode, prices only with finance.view.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Projects::company();
    $this->client = Projects::client();
    $this->goods = Projects::product();
    $this->service = Projects::product('SERVICE', '250000.00');
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'projects.confirm', 'projects.cancel_scope', 'finance.view', 'evidence.view']);
    expect(Projects::create($this->admin->id, $this->company, $this->client, [$this->goods, $this->service]))->toStartWith('COMMITTED');
    $this->project = Project::query()->sole();
    $this->evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $this->company->id, 'evidence_type' => 'CLIENT_SPK']);
});

afterEach(fn () => @unlink($this->hotFile));

function confirmationAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function confirmProject(object $test, array $extra = [], ?object $actor = null): TestResponse
{
    return $test->actingAs($actor ?? $test->admin)->post("/proyek/{$test->project->public_id}/konfirmasi", [
        'command_id' => (string) Str::uuid(), 'bukti' => $test->evidence->public_id, 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true, ...$extra,
    ]);
}

function lineHandle(object $test, int $number): string
{
    return RowHandle::of('project_lines', (int) DB::table('project_lines')->where('project_id', $test->project->id)->where('line_no', $number)->value('id'));
}

it('confirms without a quotation on the client\'s order evidence, pinning each line in base units with its price and tax (ROUTE-158; AX-13; criteria 1, 2)', function () {
    confirmProject($this)->assertRedirect("/proyek/{$this->project->public_id}");
    expect(confirmationAnswer())->toMatchArray(['kind' => 'COMMITTED']);

    $confirmation = DB::table('commercial_confirmations')->where('project_id', $this->project->id)->sole();
    $pins = DB::table('confirmation_line_pins')->where('commercial_confirmation_id', $confirmation->id)->orderBy('id')->get();

    expect($confirmation->kind)->toBe('NO_QUOTATION')
        ->and($pins)->toHaveCount(2)
        ->and(DB::table('project_lines')->where('project_id', $this->project->id)->whereNull('current_pin_id')->count())->toBe(0)
        ->and(DB::table('project_line_balances')->whereIn('project_line_id', DB::table('project_lines')->where('project_id', $this->project->id)->pluck('id'))->pluck('demand_qty_base')->sort()->values()->all())
        ->toBe($pins->pluck('qty_base')->sort()->values()->all())
        ->and((float) DB::table('project_commercial_balances')->where('project_id', $this->project->id)->value('confirmed_sales_value'))->toBe((float) $pins->sum('sales_value'))
        ->and(DB::table('evidence_links')->where('commercial_confirmation_id', $confirmation->id)->where('evidence_document_id', $this->evidence->id)->exists())->toBeTrue();

    // One confirmation per project on this path (BD-05).
    confirmProject($this);
    expect(confirmationAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'BD-05'])
        ->and(DB::table('commercial_confirmations')->count())->toBe(1);
});

it('refuses evidence of another type or company at its field and an account without projects.confirm (ROUTE-158; PJ-08; SM-04)', function () {
    $foreign = EvidenceDocumentFactory::new()->createOne(['evidence_type' => 'CLIENT_SPK']);
    confirmProject($this, ['bukti' => $foreign->public_id])->assertSessionHasErrors('bukti');
    $tax = EvidenceDocumentFactory::new()->createOne(['company_id' => $this->company->id, 'evidence_type' => 'NPWP']);
    confirmProject($this, ['bukti' => $tax->public_id])->assertSessionHasErrors('bukti');
    confirmProject($this, [], Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'evidence.view']))->assertForbidden();

    expect(DB::table('commercial_confirmations')->count())->toBe(0);
});

it('cancels a remainder in place with its reason and refuses more than the remainder (ROUTE-159; AX-14; ST-06; criteria 5, 6)', function () {
    confirmProject($this);
    $goods = lineHandle($this, 1);
    $demand = DB::table('project_line_balances')->where('project_line_id', DB::table('project_lines')->where('project_id', $this->project->id)->where('line_no', 1)->value('id'))->value('demand_qty_base');
    $cancel = fn (string $quantity, ?object $actor = null) => $this->actingAs($actor ?? $this->admin)->post("/proyek/{$this->project->public_id}/batalkan-sisa", [
        'command_id' => (string) Str::uuid(), 'baris' => [['baris' => $goods, 'jumlah' => $quantity]], 'alasan' => 'Klien membatalkan sebagian', 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true,
    ]);

    $cancel('999999');
    expect(confirmationAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'ST-06']);

    $cancel('2', Authorization::admin([$this->company->id], ['projects.view', 'projects.manage']))->assertForbidden();

    $cancel('2');
    expect(confirmationAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and(DB::table('line_scope_cancellations')->sole())->toMatchObject(['reason' => 'Klien membatalkan sebagian'])
        ->and((float) DB::table('project_line_balances')->where('cancelled_qty_base', '>', 0)->value('cancelled_qty_base'))->toBe(2.0)
        ->and(DB::table('audit_events')->where('action', 'PRJ.RemainingScopeCancelled')->value('authority_class'))->toBe('ADM_PLUS')
        ->and($demand)->not->toBeNull();
});

it('splits a goods line\'s unstarted remainder into a new line of another mode with the same snapshot (ROUTE-160; L-21; criterion 7)', function () {
    confirmProject($this);
    $origin = (int) DB::table('project_lines')->where('project_id', $this->project->id)->where('line_no', 1)->value('id');
    $before = DB::table('project_line_balances')->where('project_line_id', $origin)->value('demand_qty_base');

    $this->actingAs($this->admin)->post("/proyek/{$this->project->public_id}/ubah-pemenuhan", [
        'command_id' => (string) Str::uuid(), 'baris' => lineHandle($this, 1), 'cara' => 'DROPSHIP', 'alasan' => 'Dikirim langsung oleh pemasok',
    ]);
    expect(confirmationAnswer())->toMatchArray(['kind' => 'COMMITTED']);

    $split = DB::table('project_lines')->where('split_from_line_id', $origin)->sole();
    expect($split->fulfillment_mode)->toBe('DROPSHIP')
        ->and($split->current_pin_id)->not->toBeNull()
        ->and(DB::table('project_line_balances')->where('project_line_id', $split->id)->value('demand_qty_base'))->toBe($before)
        ->and((float) DB::table('project_line_balances')->where('project_line_id', $origin)->value('demand_qty_base'))->toBe(0.0);

    // A service line has one mode; the same mode is no re-plan (L-21).
    $this->actingAs($this->admin)->post("/proyek/{$this->project->public_id}/ubah-pemenuhan", [
        'command_id' => (string) Str::uuid(), 'baris' => lineHandle($this, 2), 'cara' => 'WAREHOUSE', 'alasan' => 'Uji',
    ]);
    expect(confirmationAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'L-21']);
});

it('shows PG-011\'s lines by mode with their progress, prices only with finance.view (J-FUL-01; PJ-12; criterion 8)', function () {
    $this->actingAs($this->admin)->get("/proyek/{$this->project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('fulfilment.confirmation', null)->where('fulfilment.can.confirm', true)->has('fulfilment.lines', 0));

    confirmProject($this);

    $this->actingAs($this->admin)->get("/proyek/{$this->project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('fulfilment.confirmation.kind', 'NO_QUOTATION')->where('fulfilment.can.confirm', false)->has('fulfilment.lines', 2)
        ->where('fulfilment.lines.0.mode', 'WAREHOUSE')->where('fulfilment.lines.1.mode', 'SERVICE')->has('fulfilment.lines.0.price')
        ->where('fulfilment.lines.0.can.cancel', true));

    $viewer = Authorization::admin([$this->company->id], ['projects.view']);
    $this->actingAs($viewer)->get("/proyek/{$this->project->public_id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->missing('fulfilment.lines.0.price')->missing('fulfilment.lines.0.available')->where('fulfilment.lines.0.can', ['cancel' => false, 'replan' => false]));
});

it('issues the company SPK (DOC-09) only for a confirmed project, its lines as pinned and the company its issuer (WF-PRJ-02; BR-DOC-04; criterion 10)', function () {
    Queue::fake();
    DocumentTypeFactory::ensure('DOC-09');
    $year = substr(BusinessDate::today(), 0, 4);
    Numbering::goLive("{$year}-01-01");
    Numbering::scheme($this->company, SequenceType::Doc09, "{$year}-01-01", 'YEAR', 'UJI/SPK/{tahun}/{nomor:4}');
    Numbering::started($this->company, SequenceType::Doc09, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
    $issuer = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'documents.issue']);
    $this->actingAs($issuer)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-09', 'subjek' => ['project_id' => $this->project->public_id]])->assertRedirect();
    $document = DB::table('documents')->where('document_type_code', 'DOC-09')->sole();
    $issue = function () use ($issuer, $document) {
        $draft = DB::table('document_versions')->where('document_id', $document->id)->where('state', 'DRAFT')->first();

        return $this->actingAs($issuer)->post("/dokumen/{$document->public_id}/terbitkan", [
            'command_id' => (string) Str::uuid(), 'lock_version' => $draft?->lock_version ?? 0, 'draf' => $draft?->public_id, 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true,
        ]);
    };

    $issue();
    expect(confirmationAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'WF-DOC-01']);

    confirmProject($this);
    $issue();
    $paper = json_decode((string) DB::table('document_versions')->where('document_id', $document->id)->where('state', 'ISSUED')->value('payload'), true)['paper'];

    expect(confirmationAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and($paper['lines'])->toHaveCount(2)
        ->and($paper['recipient']['name'])->toBe($this->client['organization']->name)
        ->and($paper['issuer']['name'])->toBe(DB::table('companies')->where('id', $this->company->id)->value('legal_name'));
});

it('writes a MIGRATION confirmation for an open project carried over through the same step (WF-MIG-01; DATABASE §24)', function () {
    $request = new CommandRequest((string) Str::uuid(), 'test.migrate', $this->admin->id, $this->company->id, 'projects.view', Fingerprint::of('test.migrate', null, ['project' => $this->project->id]));
    $outcome = app(CommandEnvelope::class)->run($request, function (CommandContext $context): CommandResult {
        $context->lock(CommercialConfirmation::locks($this->project->id));

        return new CommandResult('commercial_confirmation', app(CommercialConfirmation::class)->confirm($context, $this->project->id, $this->company->id, 'MIGRATION', null, BusinessDate::today(), 'Data pembukaan'));
    });

    expect($outcome->kind)->toBe('COMMITTED')
        ->and(DB::table('commercial_confirmations')->sole()->kind)->toBe('MIGRATION')
        ->and(DB::table('confirmation_line_pins')->count())->toBe(2);
});
