<?php

namespace App\Modules\PRJ\Services;

use App\Support\Command\CommandContext;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * The closed residual-command list of WF-PRJ-03 and L-11 as PRJ's state precondition (UXI-40; TASK-055): every project-bound command reads it under the
 * project's LK-01. On a DRAFT or ACTIVE project anything passes to its own checks; on a COMPLETED_* or CANCELLED one only the listed commands that reduce
 * residual obligations are accepted, without reopening; a correction — anything that would raise the project's outstanding or change a completed result —
 * runs and revalidates a completed project (SF-REVAL), a CANCELLED one staying CANCELLED; anything else is REJECTED. Each module's command applies it in its
 * own Task.
 */
final class ResidualCommands
{
    public const ACCEPT = 'accept';

    public const REVALIDATE = 'revalidate';

    public const REJECT = 'reject';

    public const CLOSED = ['COMPLETED_NORMAL', 'COMPLETED_FORCED', 'CANCELLED'];

    /**
     * WF-PRJ-03's residual commands: payments and applications to residual receivables, fee and tax settlements, refunds and holds of project credit, the
     * Owner's write-off and its supersession; billing an issued-unbilled invoice; collection documents for residual receivables and a pre-payment Kuitansi's
     * link; the release of residual reservations; receipts on still-open project purchases; drop-ship confirmations and delivery records for shipments made
     * before completion, a delivery's closure (CM-07); the resolution and closure of listed cases and of unusable units, loss recognition and a pending
     * unexplained-loss case's resolution; uploads of late client originals.
     */
    public const RESIDUAL = [
        'FIN.RecordPayment', 'FIN.ApplyPayment', 'FIN.RecordDeductionSettlement', 'FIN.ResolveResidualSettlement', 'FIN.RefundCredit', 'FIN.HoldCredit',
        'FIN.WriteOffReceivable', 'FIN.SupersedeWriteOff', 'FIN.BillInvoice', 'FIN.IssuePrepaymentKuitansi', 'FIN.LinkPrepaymentKuitansi',
        'DOC.IssueDocument', 'DOC.RegisterEvidence', 'DOC.AddEvidenceVersion', 'DOC.LinkEvidence',
        'INV.ReleaseReservation', 'INV.ReceiveGoods', 'INV.ChangeCondition', 'INV.RecognizeLoss', 'INV.ResolveUnattributedLoss', 'INV.ResolveUnattributedLossWithEvidence',
        'FUL.ConfirmDropShip', 'FUL.RecordDelivery', 'FUL.CloseDelivery',
        'OPS.CloseCorrectionCase',
    ];

    public function __construct(private readonly Revalidation $revalidation) {}

    /** What the command meets on a project in the state (L-11; SF-REVAL). Pure. */
    public static function decision(string $state, string $commandType, bool $correction): string
    {
        if (! in_array($state, self::CLOSED, true)) {
            return self::ACCEPT;
        }

        if (in_array($commandType, self::RESIDUAL, true)) {
            return self::ACCEPT;
        }

        if ($correction) {
            // A CANCELLED project has no reactivation: the correction changes only the facts it corrects (WF-PRJ-04).
            return $state === 'CANCELLED' ? self::ACCEPT : self::REVALIDATE;
        }

        return self::REJECT;
    }

    /**
     * The precondition under the project's LK-01, the command's own: REJECTED with L-11 where the list does not admit it; a correction on a completed project
     * revalidates it in the same transaction.
     */
    public function ensure(CommandContext $context, int $projectId, bool $correction, string $correctionReference = ''): void
    {
        $context->require(Revalidation::locks($projectId));
        $state = DB::table('projects')->where('id', $projectId)->value('state');
        $this->apply($context, $projectId, is_string($state) ? $state : '', $correction, $correctionReference);
    }

    /**
     * The same precondition on the project's state as the command already read it under the project's LK-01, with no statement of its own (QB-13).
     */
    public function apply(CommandContext $context, int $projectId, string $state, bool $correction, string $correctionReference = ''): void
    {
        $context->require(Revalidation::locks($projectId));
        $decision = self::decision($state, $context->request->commandType, $correction);

        if ($decision === self::REJECT) {
            throw Refused::rejected('L-11', ['state' => $state]);
        }

        if ($decision === self::REVALIDATE) {
            $this->revalidation->revalidate($context, $projectId, $correctionReference === '' ? $context->request->commandType : $correctionReference);
        }
    }
}
