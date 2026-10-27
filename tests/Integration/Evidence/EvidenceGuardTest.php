<?php

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\ORG\Models\Company;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-030; C-18; C-58; DATABASE §4.10; architecture challenge 6d; criterion 6): an amount-bearing evidence's
// proven amount caps every citation of it — a citation past it refused by its guard's CHECK, nothing moved —, and each
// citing role accepts only its types, so the same document registered again as OTHER gains no capacity in any role.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->actor = Authorization::admin([$this->company->id], ['finance.view', 'evidence.view', 'evidence.upload']);
});

/** Evidence of the type with its guard born at the proven amount. */
function guardedEvidence(object $test, string $type, string $proven): EvidenceDocument
{
    $evidence = EvidenceDocument::factory()->create(['company_id' => $test->company->id, 'evidence_type' => $type, 'proven_amount' => $proven,
        'identity_key' => 'bank contoh|'.$type.'|nb/'.Str::random(6).'|2026-10-01']);
    DB::table('evidence_balances')->insert(['evidence_document_id' => $evidence->id, 'proven_amount' => $proven]);

    return $evidence;
}

/** A command citing the evidence in the role, through its guard at LK-29. */
function citeEvidence(object $test, EvidenceDocument $evidence, string $role, string $amount): Outcome
{
    $request = new CommandRequest((string) Str::uuid(), 'TEST.Cite', $test->actor->id, $test->company->id, 'evidence.upload', Fingerprint::of('TEST.Cite', null, [$evidence->id, $role, $amount]));

    return app(CommandEnvelope::class)->run($request, function (CommandContext $context) use ($evidence, $role, $amount): CommandResult {
        $context->lock(EvidenceGuard::locks($evidence->id));
        app(EvidenceGuard::class)->cite($context, $evidence->id, $role, $amount);

        return new CommandResult;
    });
}

it('caps the citations of a fee advice at its proven amount (C-18)', function () {
    $advice = guardedEvidence($this, 'FEE_ADVICE', '150000.00');

    expect(citeEvidence($this, $advice, 'FEE_SETTLEMENT', '100000.00')->kind)->toBe(Outcome::COMMITTED);
    $over = citeEvidence($this, $advice, 'FEE_SETTLEMENT', '50000.01');

    expect([$over->kind, $over->code])->toBe([Outcome::REJECTED, 'C-18'])
        ->and(citeEvidence($this, $advice, 'FEE_DEDUCTION_EXPENSE', '150000.00')->kind)->toBe(Outcome::COMMITTED)
        ->and(DB::table('evidence_balances')->where('evidence_document_id', $advice->id)->first(['cited_amount', 'expensed_amount']))
        ->cited_amount->toBe('100000.00')
        ->expensed_amount->toBe('150000.00');
});

it('gains no capacity for a document registered again under another type (C-58; 6d)', function () {
    $copy = guardedEvidence($this, 'OTHER', '150000.00');

    foreach (['FEE_SETTLEMENT', 'TAX_SETTLEMENT_WITHHELD', 'CHARGE_SOURCE_BILL', 'TAX_REMITTANCE', 'FEE_DEDUCTION_EXPENSE'] as $role) {
        $outcome = citeEvidence($this, $copy, $role, '1.00');
        expect([$outcome->kind, $outcome->code])->toBe([Outcome::REJECTED, 'C-58']);
    }

    expect(DB::table('evidence_balances')->where('evidence_document_id', $copy->id)->value('cited_amount'))->toBe('0.00');
});
