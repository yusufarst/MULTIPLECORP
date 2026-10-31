<?php

namespace App\Modules\FIN\Services;

use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/** Each payment command's business audit event, with the individual actor, date, reason and links (BR-XC-01; LG-01; AZ-10). */
final class PaymentRecords
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * An opening credit's application: the credit has no public id, its event its own entity.
     *
     * @param  array<string, mixed>  $after
     */
    public function recordCredit(CommandContext $context, int $creditId, int $companyId, string $businessDate, array $after): void
    {
        $this->audit->record(
            action: 'FIN.PaymentApplied',
            authorityClass: AuthorityClass::Adm,
            actorUserId: $context->request->actorUserId,
            companyId: $companyId,
            entityType: 'opening_customer_credit',
            entityId: $creditId,
            entityPublicId: null,
            reason: null,
            commandId: $context->request->commandId,
            change: AuditChange::created($after),
            businessDate: $businessDate,
        );
    }

    /**
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $metadata
     */
    public function record(CommandContext $context, string $action, AuthorityClass $class, int $paymentId, ?string $reason, ?string $businessDate, array $after, array $before = [], ?AuditLinkage $linkage = null, array $metadata = []): void
    {
        $payment = DB::table('payments')->where('id', $paymentId)->first(['public_id', 'company_id']);
        $this->audit->record(
            action: $action,
            authorityClass: $class,
            actorUserId: $context->request->actorUserId,
            companyId: is_object($payment) ? (int) Row::text($payment, 'company_id') : null,
            entityType: 'payment',
            entityId: $paymentId,
            entityPublicId: is_object($payment) ? Row::text($payment, 'public_id') : null,
            reason: $reason,
            commandId: $context->request->commandId,
            change: $before === [] ? AuditChange::created($after) : AuditChange::updated($before, $after),
            linkage: $linkage,
            metadata: $metadata,
            businessDate: $businessDate,
        );
    }
}
