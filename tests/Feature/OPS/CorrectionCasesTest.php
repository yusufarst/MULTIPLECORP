<?php

use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Queries\CorrectionTrail;
use App\Modules\OPS\Services\CorrectionCases;
use App\Modules\OPS\Services\Corrections;
use App\Modules\ORG\Models\Company;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-031; ROUTE-040, ROUTE-271; SF-CASE; criteria 1–4, 8): a case opened inside the correcting command that
// records it, with its reason; its closure with the outcome, REJECTED naming each residual in words while one stands
// and a purchase return closed only when its equation balances; a closed case reopened by a posting in that posting's
// transaction, audited; a correction recorded with reason, actor and link, read back as "Mengoreksi" and "Dikoreksi
// oleh"; PG-040's residuals by name, amounts by family, another company's causal project as the relation marker.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->project = ProjectFactory::new()->createOne(['company_id' => $this->company->id]);
    $this->closer = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'cost.view', 'cases.close']);
});

afterEach(fn () => @unlink($this->hotFile));

function caseAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** A correcting command's own transaction, as a module's Task will run it, the case service inside. */
function correcting(int $actorId, int $companyId, Closure $action): Outcome
{
    $request = new CommandRequest((string) Str::uuid(), 'test.correct', $actorId, $companyId, 'projects.view', Fingerprint::of('test.correct', null, ['at' => microtime()]));

    return app(CommandEnvelope::class)->run($request, $action);
}

function closeCase(object $test, CorrectionCase $case, string $reason = 'Barang sudah kembali dan dinilai.', ?int $lockVersion = null): void
{
    $test->actingAs($test->closer)->post("/dokumen/kasus/{$case->public_id}/tutup", ['command_id' => (string) Str::uuid(), 'lock_version' => $lockVersion ?? $case->fresh()?->lock_version, 'alasan' => $reason]);
}

it('opens a case only inside the correcting command, with its reason and the causal project and lot it cites (criterion 1; SF-CASE)', function () {
    $causal = ProjectFactory::new()->createOne(['company_id' => Company::factory()->create()->id]);
    $opened = null;
    $outcome = correcting($this->closer->id, $this->company->id, function (CommandContext $context) use ($causal, &$opened): CommandResult {
        $context->lock(CorrectionCases::openingLocks($this->project->id, $causal->id));
        $opened = app(CorrectionCases::class)->open($context, AuthorityClass::Adm, $this->company->id, 'SALES_RETURN', 'Klien mengembalikan 2 unit.', $this->project->id, $causal->id);

        return new CommandResult('correction_case', $opened->id);
    });

    expect($outcome->kind)->toBe(Outcome::COMMITTED)
        ->and(CorrectionCase::query()->sole()->only(['case_kind', 'state', 'reason', 'project_id', 'causal_project_id']))
        ->toBe(['case_kind' => 'SALES_RETURN', 'state' => 'OPEN', 'reason' => 'Klien mengembalikan 2 unit.', 'project_id' => $this->project->id, 'causal_project_id' => $causal->id])
        ->and(DB::table('audit_events')->where('action', 'OPS.CorrectionCaseOpened')->value('reason'))->toBe('Klien mengembalikan 2 unit.')
        ->and(fn () => correcting($this->closer->id, $this->company->id, function (CommandContext $context): CommandResult {
            $context->lock(new LockSet);
            app(CorrectionCases::class)->open($context, AuthorityClass::Adm, $this->company->id, 'OTHER', '  ');

            return new CommandResult;
        }))->toThrow(InvalidArgumentException::class);
});

it('closes a case without residual with its outcome, and refuses one with a residual naming it in words (criterion 2; C-46)', function () {
    $clear = CorrectionCase::factory()->create(['company_id' => $this->company->id, 'project_id' => $this->project->id]);
    closeCase($this, $clear);
    expect(caseAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and($clear->fresh()?->only(['state', 'closure_outcome', 'closed_by']))->toBe(['state' => 'CLOSED', 'closure_outcome' => 'Barang sudah kembali dan dinilai.', 'closed_by' => $this->closer->id])
        ->and(DB::table('audit_events')->where('action', 'OPS.CorrectionCaseClosed')->value('authority_class'))->toBe('ADM_PLUS');

    $owing = CorrectionCase::factory()->create(['company_id' => $this->company->id, 'residual_settlement_amount' => '150000.00', 'unbacked_refund_amount' => '25000.00']);
    closeCase($this, $owing);
    expect(caseAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'C-46'])
        ->and(caseAnswer()['text'])->toContain('pengembalian dana yang belum tertutup pembayaran')->toContain('potongan yang belum terselesaikan')
        ->and($owing->fresh()?->state)->toBe('OPEN');
});

it('closes a purchase return only when its returned cost equals refunded, replaced and unrecovered (criterion 2; AX-11)', function () {
    $unbalanced = CorrectionCase::factory()->create(['company_id' => $this->company->id, 'case_kind' => 'PURCHASE_RETURN', 'returned_cost' => '1000000.00', 'refunded_value' => '600000.00', 'replaced_value' => '300000.00']);
    closeCase($this, $unbalanced);
    expect(caseAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'C-46'])
        ->and(caseAnswer()['text'])->toContain('biaya retur yang belum seluruhnya dikembalikan');

    $balanced = CorrectionCase::factory()->create(['company_id' => $this->company->id, 'case_kind' => 'PURCHASE_RETURN', 'returned_cost' => '1000000.00', 'refunded_value' => '600000.00', 'replaced_value' => '300000.00', 'unrecovered_value' => '100000.00']);
    closeCase($this, $balanced);
    expect(caseAnswer()['kind'])->toBe(Outcome::COMMITTED)->and($balanced->fresh()?->state)->toBe('CLOSED');
});

it('answers a stale or already closed closure CONFLICT (ST-01; ST-02)', function () {
    $case = CorrectionCase::factory()->create(['company_id' => $this->company->id]);
    closeCase($this, $case, lockVersion: 7);
    expect(caseAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'code' => 'ST-01']);

    closeCase($this, $case);
    closeCase($this, $case, lockVersion: 1);
    expect(caseAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'code' => 'ST-02']);
});

it('reopens a closed case when a posting changes its figures, in that posting\'s transaction, audited (criterion 3; C-46; L-06)', function () {
    $case = CorrectionCase::factory()->create(['company_id' => $this->company->id, 'project_id' => $this->project->id]);
    closeCase($this, $case);

    $outcome = correcting($this->closer->id, $this->company->id, function (CommandContext $context) use ($case): CommandResult {
        $context->lock(CorrectionCases::locks($case->id));
        app(CorrectionCases::class)->post($context, $case->id, ['residual_settlement_amount' => '75000.00'], 'Potongan PPh atas invoice yang dikoreksi.');

        return new CommandResult('correction_case', $case->id);
    });

    $fresh = $case->fresh();
    expect($outcome->kind)->toBe(Outcome::COMMITTED)
        ->and($fresh?->state)->toBe('OPEN')
        ->and($fresh?->residual_settlement_amount)->toBe('75000.00')
        ->and($fresh?->reopen_reason)->toBe('Potongan PPh atas invoice yang dikoreksi.')
        ->and($fresh?->reopened_by)->toBe($this->closer->id)
        ->and(DB::table('audit_events')->where('action', 'OPS.CorrectionCaseReopened')->where('entity_id', $case->id)->count())->toBe(1);

    // A posting of zero changes no figure and reopens nothing.
    DB::table('correction_cases')->where('id', $case->id)->update(['residual_settlement_amount' => 0, 'state' => 'CLOSED', 'closure_outcome' => 'Uji', 'closed_by' => $this->closer->id, 'closed_at' => now()]);
    correcting($this->closer->id, $this->company->id, function (CommandContext $context) use ($case): CommandResult {
        $context->lock(CorrectionCases::locks($case->id));
        app(CorrectionCases::class)->post($context, $case->id, ['returned_cost' => '0.00'], 'Tanpa perubahan');

        return new CommandResult;
    });
    expect($case->fresh()?->state)->toBe('CLOSED');
});

it('records a correction with its reason and link, read as "Mengoreksi" and "Dikoreksi oleh" (criterion 4; BR-CR-01; BR-CR-02)', function () {
    $original = (string) Str::uuid7();
    $correction = (string) Str::uuid7();
    correcting($this->closer->id, $this->company->id, function (CommandContext $context) use ($original, $correction): CommandResult {
        $context->lock(new LockSet);
        Corrections::ensureLegal('STOCK_MOVEMENT', 'REVERSAL');
        app(Corrections::class)->record($context, 'INV.MovementReversed', AuthorityClass::AdmPlus, $this->company->id, 'stock_movement', 2, $correction,
            AuditRelation::Reverses, 'stock_movement', $original, 'Salah lot dipindai.', AuditChange::created(['reverses' => $original]));

        return new CommandResult;
    });

    $trail = app(CorrectionTrail::class);
    expect($trail->of('stock_movement', $original)['corrected_by'])->toHaveCount(1)
        ->and($trail->of('stock_movement', $original)['corrected_by'][0])->toMatchArray(['relation' => 'REVERSES', 'type' => 'stock_movement', 'id' => $correction, 'reason' => 'Salah lot dipindai.', 'by' => $this->closer->name])
        ->and($trail->of('stock_movement', $correction)['corrects'][0])->toMatchArray(['relation' => 'REVERSES', 'id' => $original])
        ->and(fn () => Corrections::ensureLegal('MONEY_FACT', 'VOID'))->toThrow(Refused::class)
        ->and(fn () => Corrections::ensureLegal('INVOICE_BILLED', 'VOID'))->toThrow(Refused::class);

    Corrections::ensureLegal('INVOICE_BILLED', 'VOID', withCompanion: true);
});

it('lists a branch\'s cases with residuals by name, amounts by family and another company\'s causal project as the marker (ROUTE-040; PJ-16; XL-3; criterion 8)', function () {
    $foreign = ProjectFactory::new()->createOne(['company_id' => Company::factory()->create()->id]);
    CorrectionCase::factory()->create(['company_id' => $this->company->id, 'project_id' => $this->project->id, 'causal_project_id' => $foreign->id,
        'case_kind' => 'SALES_RETURN', 'residual_settlement_amount' => '5000.00', 'returned_cost' => '9000.00']);
    $viewer = Authorization::admin([$this->company->id], ['projects.view']);

    $this->actingAs($viewer)->get('/dokumen/kasus')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('OPS/CorrectionCases', false)
        ->has('rows', 1)
        ->where('rows.0.residuals', ['unresolved_deduction'])
        ->where('rows.0.counters', [])
        ->where('rows.0.causal', ['foreign' => true])
        ->where('can.close', false));

    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view', 'finance.view']))->get('/dokumen/kasus')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('rows.0.counters', ['unbacked_refund_amount' => '0.00', 'residual_settlement_amount' => '5000.00']));
    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view', 'cost.view']))->get('/dokumen/kasus')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('rows.0.counters', ['returned_cost' => '9000.00', 'refunded_value' => '0.00', 'replaced_value' => '0.00', 'unrecovered_value' => '0.00']));
});
