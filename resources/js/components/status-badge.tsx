import {
    Archive,
    BadgeAlert,
    BadgeCheck,
    Ban,
    CalendarClock,
    Check,
    CheckCheck,
    Circle,
    CircleCheck,
    CircleDashed,
    CircleDot,
    CircleMinus,
    CircleOff,
    CirclePause,
    CircleX,
    Clock,
    ClockAlert,
    Contrast,
    Download,
    Ellipsis,
    Eraser,
    EyeOff,
    FileCheck,
    FileClock,
    FileExclamationPoint,
    FileQuestionMark,
    FileText,
    FileX,
    Flag,
    FolderCheck,
    FolderOpen,
    Hourglass,
    Link,
    LockOpen,
    type LucideIcon,
    Pencil,
    Scale,
    ScanBarcode,
    Send,
    Square,
    TriangleAlert,
    Undo2,
    UserRoundSearch,
} from 'lucide-react';

import { Badge, type BadgeTone } from '@/components/ui/badge';
import { Icon } from '@/components/ui/icon';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

// PT-13's status badge (DESIGN_SYSTEM §12.1): every lifecycle or derived state a screen shows, in the
// reference's pill — its label from the catalogue, one of five tones and a 14 px Lucide shape that keeps two
// states of a family apart in greyscale (WCAG 1.4.1; UXS-42). The label alone is sufficient; the tone only
// reinforces; the state arrives from the server, the badge never derives it. Two names follow Lucide 1.56.0:
// `file-warning` is `file-exclamation-point` and `file-question` is `file-question-mark` (§12.1 "Shapes").
type Face = { label: TranslationKey; tone: Exclude<BadgeTone, 'outline' | 'pending'>; icon: LucideIcon };

const f = (label: TranslationKey, tone: Face['tone'], icon: LucideIcon): Face => ({ label, tone, icon });

export const STATUS = {
    project: {
        DRAFT: f('status.project.draft', 'neutral', CircleDashed),
        ACTIVE: f('status.project.active', 'info', CircleDot),
        COMPLETED_NORMAL: f('status.project.completed_normal', 'success', Check),
        COMPLETED_FORCED: f('status.project.completed_forced', 'warning', BadgeAlert),
        CANCELLED: f('status.project.cancelled', 'neutral', Ban),
    },
    quotation_revision: {
        DRAFT: f('status.quotation_revision.draft', 'neutral', CircleDashed),
        SENT: f('status.quotation_revision.sent', 'info', Send),
        APPROVED: f('status.quotation_revision.approved', 'success', CircleCheck),
        REJECTED: f('status.quotation_revision.rejected', 'danger', CircleX),
        SENT_PAST_VALIDITY: f('status.quotation_revision.sent_past_validity', 'neutral', Clock),
    },
    document_version: {
        DRAFT: f('status.document_version.draft', 'neutral', CircleDashed),
        ISSUED: f('status.document_version.issued', 'info', FileText),
        SUPERSEDED: f('status.document_version.superseded', 'neutral', FileClock),
        VOIDED: f('status.document_version.voided', 'neutral', FileX),
    },
    rendition: {
        PENDING: f('status.rendition.pending_rendering', 'info', Ellipsis),
        RENDERING: f('status.rendition.pending_rendering', 'info', Ellipsis),
        READY: f('status.rendition.ready', 'success', FileCheck),
        FAILED: f('status.rendition.failed', 'danger', FileExclamationPoint),
    },
    pre_payment_kuitansi: {
        OPEN_NOT_LINKED: f('status.pre_payment_kuitansi.open_not_linked', 'warning', Hourglass),
        LINKED: f('status.pre_payment_kuitansi.linked', 'success', Link),
    },
    invoice_and_receivable: {
        DRAFT: f('status.invoice_and_receivable.draft', 'neutral', Pencil),
        VOIDED: f('status.invoice_and_receivable.voided', 'neutral', Ban),
        ISSUED_NOT_BILLED: f('status.invoice_and_receivable.issued_not_billed', 'neutral', Square),
        BILLED_OUTSTANDING: f('status.invoice_and_receivable.billed_outstanding', 'info', Contrast),
        PAST_DUE_DATE: f('status.invoice_and_receivable.past_due_date', 'warning', TriangleAlert),
        OUTSTANDING_ZERO: f('status.invoice_and_receivable.outstanding_zero', 'success', CheckCheck),
        DISPUTE_HOLD: f('status.invoice_and_receivable.dispute_hold', 'warning', Flag),
        WRITTEN_OFF: f('status.invoice_and_receivable.written_off', 'neutral', Eraser),
    },
    payment: {
        WITHOUT_PROOF: f('status.payment.without_proof', 'warning', FileQuestionMark),
        CONTRA_D: f('status.payment.contra_d', 'neutral', Undo2),
    },
    reservation: {
        ACTIVE: f('status.reservation.active', 'info', CircleDot),
        RELEASED: f('status.reservation.released', 'neutral', LockOpen),
        CONSUMED: f('status.reservation.consumed', 'success', Check),
    },
    purchase: {
        OPEN: f('status.purchase.open', 'info', CircleDot),
        CLOSED: f('status.purchase.closed', 'neutral', Archive),
        CANCELLED: f('status.purchase.cancelled', 'neutral', Ban),
    },
    unit_condition: {
        USABLE: f('status.unit_condition.usable', 'success', Check),
        UNUSABLE: f('status.unit_condition.unusable', 'warning', TriangleAlert),
    },
    count: {
        OPEN: f('status.count.open', 'info', CircleDot),
        STALE: f('status.count.stale', 'warning', ClockAlert),
        APPLIED: f('status.count.applied', 'success', Check),
    },
    count_finding: {
        OPEN_OWNER_TO_ATTRIBUTE: f('status.count_finding.open_owner_to_attribute', 'warning', UserRoundSearch),
    },
    pending_unexplained_loss_case: {
        PENDING: f('status.pending_unexplained_loss_case.pending', 'warning', Scale),
        RESOLVED: f('status.pending_unexplained_loss_case.resolved', 'success', Check),
    },
    correction_case: {
        OPEN: f('status.correction_case.open', 'warning', FolderOpen),
        CLOSED: f('status.correction_case.closed', 'neutral', FolderCheck),
    },
    delivery: {
        PARTLY_DELIVERED: f('status.delivery.partly_delivered', 'info', Contrast),
        FULLY_DELIVERED: f('status.delivery.fully_delivered', 'success', Check),
        WITH_A_DISCREPANCY: f('status.delivery.with_a_discrepancy', 'warning', TriangleAlert),
        CLOSED: f('status.delivery.closed', 'neutral', Archive),
    },
    administrative_requirement: {
        MISSING: f('status.administrative_requirement.missing', 'danger', Circle),
        DRAFT_ONLY: f('status.administrative_requirement.draft_only', 'info', Pencil),
        SATISFIED: f('status.administrative_requirement.satisfied', 'success', CircleCheck),
        WAIVED: f('status.administrative_requirement.waived', 'neutral', CircleMinus),
    },
    export: {
        REQUESTED: f('status.export.requested', 'neutral', Hourglass),
        RUNNING: f('status.export.running', 'info', Ellipsis),
        READY: f('status.export.ready', 'success', Download),
        FAILED: f('status.export.failed', 'danger', CircleX),
        WITHHELD: f('status.export.withheld', 'neutral', EyeOff),
    },
    import_batch: {
        PREPARED: f('status.import_batch.prepared', 'neutral', CircleDashed),
        DRY_RUN_PASSED: f('status.import_batch.dry_run_passed', 'info', CircleCheck),
        SIGNED_OFF: f('status.import_batch.signed_off', 'info', BadgeCheck),
        COMMITTING: f('status.import_batch.committing', 'info', Ellipsis),
        COMMITTED: f('status.import_batch.committed', 'success', Check),
        STOPPED: f('status.import_batch.stopped', 'warning', CirclePause),
        ABORTED: f('status.import_batch.aborted', 'neutral', Ban),
    },
    account_and_company: {
        ACTIVE: f('status.account_and_company.active', 'info', CircleDot),
        INACTIVE: f('status.account_and_company.inactive', 'neutral', CircleOff),
    },
    product_stock: {
        BELOW_ITS_MINIMUM: f('status.product_stock.below_its_minimum', 'warning', TriangleAlert),
    },
    part: {
        NOT_STARTED: f('status.part.not_started', 'neutral', CircleDashed),
        IN_PROGRESS: f('status.part.in_progress', 'info', Contrast),
        DONE: f('status.part.done', 'success', Check),
        NOT_NEEDED: f('status.part.not_needed', 'neutral', CircleMinus),
    },
    readiness_item: {
        COMPLETE: f('status.readiness_item.complete', 'success', Check),
        NOT_COMPLETE: f('status.readiness_item.not_complete', 'neutral', CircleDashed),
    },
    task_line: {
        NOT_RECORDED: f('status.task_line.not_recorded', 'neutral', CircleDashed),
        RECORDED: f('status.task_line.recorded', 'success', Check),
    },
} as const satisfies Record<string, Record<string, Face>>;

export type StatusFamily = keyof typeof STATUS;
export type StatusState<F extends StatusFamily> = keyof (typeof STATUS)[F] & string;

export function statusFace<F extends StatusFamily>(family: F, state: StatusState<F>): Face {
    return (STATUS[family] as Record<string, Face>)[state] as Face;
}

/** The pill for the record in view is `lg`; a list row's is `sm` (§12.1). */
export function StatusBadge<F extends StatusFamily>({ family, state, size = 'md', className }: { family: F; state: StatusState<F>; size?: 'sm' | 'md' | 'lg'; className?: string }) {
    const { t } = useTranslation();
    const face = statusFace(family, state);

    return (
        <Badge tone={face.tone} size={size} data-status={`${family}.${state}`} className={className}>
            <Icon icon={face.icon} size="status" />
            <span>{t(face.label)}</span>
        </Badge>
    );
}

/** The row density: the 13 px label with its shape before it, no pill (§12.1). */
export function StatusText<F extends StatusFamily>({ family, state, className }: { family: F; state: StatusState<F>; className?: string }) {
    const { t } = useTranslation();
    const face = statusFace(family, state);

    return (
        <span data-status={`${family}.${state}`} className={cn('inline-flex items-center gap-1 text-label text-text-strong', className)}>
            <Icon icon={face.icon} size="status" />
            {t(face.label)}
        </span>
    );
}

/** Marks are not states (§12.1): "Tanggal Mundur" and "Kode Cocok", the label style in ink after their icon. */
export function Mark({ kind, className }: { kind: 'backdated' | 'code_match'; className?: string }) {
    const { t } = useTranslation();

    return (
        <span data-mark={kind} className={cn('inline-flex items-center gap-1 text-label text-text-primary', className)}>
            <Icon icon={kind === 'backdated' ? CalendarClock : ScanBarcode} size="status" />
            {t(kind === 'backdated' ? 'date.backdated' : 'search.code_match')}
        </span>
    );
}
