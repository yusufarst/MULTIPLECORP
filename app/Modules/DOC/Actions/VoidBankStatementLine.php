<?php

namespace App\Modules\DOC\Actions;

use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * `DOC VoidBankStatementLine` (ROUTE-262; INT-262; NX-08; C-19) — a mistyped line voided, with MSG-40's reason (IP-09), only while nothing cites it: its guard then
 * proves nothing, so the void cannot commit past a citation, which moves first through its reasoned correction (DATABASE §4.10). A voided line's identity is free for
 * the true line. `cash.correct` (ADM+) of its company.
 */
final class VoidBankStatementLine
{
    public const TYPE = 'DOC.VoidBankStatementLine';

    public const CAPABILITY = 'cash.correct';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly EvidenceGuard $guard,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, int $companyId, int $lineId, string $reason): Outcome
    {
        $handle = RowHandle::of('bank_statement_lines', $lineId);
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, DocumentChain::companyKey($companyId), ['line' => $handle, 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($companyId, $lineId, $reason, $handle): CommandResult {
            $context->lock(EvidenceGuard::lineLocks($lineId));
            $line = DB::table('bank_statement_lines')->where('id', $lineId)->where('company_id', $companyId)->first(['state', 'amount']);

            if ($line === null || Row::text($line, 'state') !== 'ACTIVE') {
                throw Refused::conflict('ST-02', ['state' => 'VOIDED']);
            }

            $cited = $this->guard->lineCited($lineId);

            if (Money::of($cited)->isPositive()) {
                throw Refused::rejected('C-19', ['reason' => 'cited', 'cited' => $cited]);
            }

            $now = $context->now();
            $voided = DB::table('bank_statement_lines')->where('id', $lineId)->where('state', 'ACTIVE')->update(['state' => 'VOIDED', 'voided_by' => $context->request->actorUserId, 'voided_at' => $now, 'voided_reason' => $reason]);

            if ($voided !== 1) {
                throw Refused::conflict('ST-02', ['state' => 'VOIDED']);
            }

            $this->guard->moveLines($context, [$lineId => ['proven_amount' => Money::of(Row::text($line, 'amount'))->negated()->toString()]]);
            $this->audit->record(
                action: 'DOC.BankLineVoided',
                authorityClass: AuthorityClass::AdmPlus,
                actorUserId: $context->request->actorUserId,
                companyId: $companyId,
                entityType: 'bank_statement_line',
                entityId: $lineId,
                entityPublicId: $handle,
                reason: $reason,
                commandId: $context->request->commandId,
                change: AuditChange::updated(['state' => 'ACTIVE'], ['state' => 'VOIDED']),
            );

            return new CommandResult('bank_statement_line', $lineId);
        });
    }
}
