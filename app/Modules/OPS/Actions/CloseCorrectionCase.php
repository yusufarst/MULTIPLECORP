<?php

namespace App\Modules\OPS\Actions;

use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Services\CorrectionCases;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * `OPS CloseCorrectionCase` (ROUTE-271; INT-271; NX-06; SF-CASE; BR-CR-04): "Tutup Kasus" with its outcome as the
 * reason (IP-09; MSG-40) — the case closed at the version the page read, REJECTED with the residual in words while an
 * unbacked refund, an unresolved deduction or a purchase return's unaccounted cost stands (C-46). `cases.close`
 * (ADM_PLUS) with `projects.view` of the case's company; the case project and the causal project at LK-01, then the
 * case at LK-10, so a closure racing a correction of the same case or project serializes (criterion 7). An ADM_PLUS
 * audit event, which reaches the Owner's review queue (AZ-10; QS-14).
 */
final class CloseCorrectionCase
{
    public const TYPE = 'OPS.CloseCorrectionCase';

    public const CAPABILITY = 'cases.close';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly CorrectionCases $cases,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, CorrectionCase $case, int $lockVersion, string $outcome): Outcome
    {
        $company = DB::table('companies')->where('id', $case->company_id)->value('public_id');
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $case->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, is_string($company) ? $company : null, ['case' => $case->public_id, 'lock_version' => $lockVersion, 'outcome' => $outcome]));

        return $this->envelope->run($request, function (CommandContext $context) use ($case, $lockVersion, $outcome): CommandResult {
            $context->lock(CorrectionCases::locks($case->id));

            if ($context->authority()?->allows('projects.view', $case->company_id) !== true) {
                throw new AuthorizationException;
            }

            $this->cases->close($context, $case->id, $lockVersion, $outcome);
            $this->audit->record(
                action: 'OPS.CorrectionCaseClosed',
                authorityClass: AuthorityClass::AdmPlus,
                actorUserId: $context->request->actorUserId,
                companyId: $case->company_id,
                entityType: 'correction_case',
                entityId: $case->id,
                entityPublicId: $case->public_id,
                reason: trim($outcome),
                commandId: $context->request->commandId,
                change: AuditChange::updated(['state' => 'OPEN'], ['state' => 'CLOSED']),
                linkage: CorrectionCases::caseLinkage($case->id),
                metadata: ['case_kind' => $case->case_kind],
            );

            return new CommandResult('correction_case', $case->id);
        }, replayState: fn (Outcome $replayed): ?array => $replayed->code === 'C-46' ? ['residuals' => CorrectionCases::residuals($case->id)] : null);
    }
}
