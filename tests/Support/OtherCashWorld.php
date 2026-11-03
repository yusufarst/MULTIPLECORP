<?php

namespace Tests\Support;

use App\Modules\CST\Actions\RecordExpense;
use App\Modules\FIN\Actions\RecordDisbursement;
use App\Modules\FIN\Actions\RecordOtherCashIn;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * TASK-054's world (TL-04; TL-05; TL-08): an actor of PG-049's forms, and ROUTE-257, ROUTE-258 and ROUTE-259's actions as their own commands — what earlier Tasks' tests stood in for with
 * inserted rows. Static, so the closures of the two-connection harness can call them (Sessions::run).
 */
final class OtherCashWorld
{
    public const CAPABILITIES = ['finance.view', 'cost.view', 'evidence.view', 'expense.record', 'disbursement.record', 'cashin.record'];

    /** @param  list<string>  $more */
    public static function actor(int $companyId, array $more = []): int
    {
        return Authorization::admin([$companyId], [...self::CAPABILITIES, ...$more])->id;
    }

    /**
     * ROUTE-257's action; without a bill or evidence named, a proof of the company stands as its evidence (L-45).
     *
     * @param  list<int>|null  $evidence
     */
    public static function expense(int $actorId, int $companyId, ?int $projectId, string $amount, ?int $billId = null, ?array $evidence = null, string $category = 'OPERASIONAL', ?string $commandId = null): Outcome
    {
        $evidence ??= $billId === null ? [PaymentWorld::proof($companyId, 'PAYMENT_PROOF')->id] : [];

        return app(RecordExpense::class)->run($commandId ?? (string) Str::uuid(), $actorId, $companyId, $projectId, $category, $amount, BusinessDate::today(), false, $billId, $evidence,
            ['company' => $companyId, 'project' => $projectId, 'category' => $category, 'amount' => $amount, 'bill' => $billId, 'evidence' => $evidence]);
    }

    /**
     * ROUTE-258's action; without evidence named, a transfer proof of the company, except for a remittance, whose tax payment proof is its own.
     *
     * @param  array{purchase?: ?int, expense?: ?int, tax_evidence?: ?int, tax_type?: ?int}  $reference
     * @param  list<int>|null  $evidence
     */
    public static function disburse(int $actorId, int $companyId, string $kind, string $amount, int $accountId, array $reference = [], ?array $evidence = null, ?string $commandId = null): Outcome
    {
        $reference = ['purchase' => $reference['purchase'] ?? null, 'expense' => $reference['expense'] ?? null, 'tax_evidence' => $reference['tax_evidence'] ?? null, 'tax_type' => $reference['tax_type'] ?? null];
        $evidence ??= $kind === 'TAX_REMITTANCE' ? [] : [PaymentWorld::proof($companyId, 'TRANSFER_PROOF')->id];

        return app(RecordDisbursement::class)->run($commandId ?? (string) Str::uuid(), $actorId, $companyId, $kind, $amount, $accountId, $reference, $evidence, BusinessDate::today(), false,
            ['company' => $companyId, 'kind' => $kind, 'amount' => $amount, 'account' => $accountId, ...$reference, 'evidence' => $evidence]);
    }

    /**
     * ROUTE-259's action; without evidence or a line named, a transfer proof of the company.
     *
     * @param  array{purchase?: ?int, case?: ?int, transfer?: ?int, line?: ?int}  $reference
     * @param  list<int>|null  $evidence
     */
    public static function cashIn(int $actorId, int $companyId, string $category, string $amount, int $accountId, array $reference = [], ?array $evidence = null, ?string $commandId = null): Outcome
    {
        $reference = ['purchase' => $reference['purchase'] ?? null, 'case' => $reference['case'] ?? null, 'transfer' => $reference['transfer'] ?? null, 'line' => $reference['line'] ?? null];
        $evidence ??= $reference['line'] === null ? [PaymentWorld::proof($companyId, 'TRANSFER_PROOF')->id] : [];

        return app(RecordOtherCashIn::class)->run($commandId ?? (string) Str::uuid(), $actorId, $companyId, $category, $amount, $accountId, $reference, $evidence, null, BusinessDate::today(), false,
            ['company' => $companyId, 'category' => $category, 'amount' => $amount, 'account' => $accountId, ...$reference, 'evidence' => $evidence]);
    }

    /** The committed fact's id, or the test fails with the outcome. */
    public static function committed(Outcome $outcome): int
    {
        if ($outcome->kind !== Outcome::COMMITTED || $outcome->resultId === null) {
            throw new RuntimeException("The command was {$outcome->kind} {$outcome->code}: ".json_encode($outcome->state));
        }

        return $outcome->resultId;
    }

    /** A transfer out of the company, recorded by its Owner; its id. */
    public static function transferOut(int $companyId, string $amount): int
    {
        return self::committed(self::disburse(Authorization::owner()->id, $companyId, 'INTERCOMPANY_TRANSFER_OUT', $amount, PaymentWorld::account($companyId)));
    }
}
