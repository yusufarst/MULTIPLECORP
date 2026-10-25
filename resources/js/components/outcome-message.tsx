import { CircleAlert, CircleCheck, CircleHelp, CirclePause, CircleX, CopyCheck, Ellipsis, type LucideIcon, RefreshCcw, WifiOff } from 'lucide-react';
import { type ReactNode, useEffect, useRef } from 'react';

import { Alert, type AlertTone } from '@/components/ui/alert';
import { type TranslationKey, useTranslation } from '@/lib/i18n';

// The face of each answer of a command (DESIGN_SYSTEM §12.2; ADMIN_FLOW IP-01, IP-03, IP-04, IP-06): PT-14's
// inline message with the answer's label, tone and icon, the server's message as its text and at most one
// action. The server's answer decides what the screen says — never an optimistic result. Announced through
// the action region's live regions: polite for COMMITTED and PENDING, assertive for the others; a CONFLICT or
// FAILED answer also takes focus (§15).
export type Answer =
    | { kind: 'COMMITTED'; label?: TranslationKey; text?: ReactNode }
    | { kind: 'REJECTED'; text: ReactNode }
    | { kind: 'CONFLICT'; cause: 'stale' | 'recorded' | 'import_outside_scope'; text: ReactNode }
    | { kind: 'PENDING'; text: ReactNode }
    | { kind: 'FAILED'; cause: 'not_processed' | 'unknown' | 'defect'; text: ReactNode; reference?: string }
    | { kind: 'VALIDATION'; text: ReactNode }
    | { kind: 'OFFLINE'; text: ReactNode };

type Face = { label: TranslationKey; tone: AlertTone; icon: LucideIcon };

export function answerFace(answer: Answer): Face {
    switch (answer.kind) {
        case 'COMMITTED':
            return { label: answer.label ?? 'outcome.committed', tone: 'success', icon: CircleCheck };
        case 'REJECTED':
            return { label: 'outcome.rejected', tone: 'danger', icon: CircleX };
        case 'CONFLICT':
            // DP-18: an import identity outside scope takes the REJECTED face with MSG-18 alone.
            if (answer.cause === 'import_outside_scope') {
                return { label: 'outcome.rejected', tone: 'danger', icon: CircleX };
            }

            return answer.cause === 'stale'
                ? { label: 'outcome.conflict_stale', tone: 'warning', icon: RefreshCcw }
                : { label: 'outcome.conflict_recorded', tone: 'info', icon: CopyCheck };
        case 'PENDING':
            return { label: 'outcome.pending', tone: 'info', icon: Ellipsis };
        case 'FAILED':
            return answer.cause === 'unknown'
                ? { label: 'outcome.failed_unknown', tone: 'warning', icon: CircleHelp }
                : { label: 'outcome.failed_not_processed', tone: 'warning', icon: CirclePause };
        case 'VALIDATION':
            return { label: 'outcome.validation', tone: 'danger', icon: CircleAlert };
        case 'OFFLINE':
            return { label: 'outcome.offline', tone: 'neutral', icon: WifiOff };
    }
}

/** Polite for COMMITTED and PENDING; assertive for REJECTED, CONFLICT and FAILED (§15). */
export function isAssertive(answer: Answer): boolean {
    return answer.kind !== 'COMMITTED' && answer.kind !== 'PENDING';
}

export function OutcomeMessage({ answer, action }: { answer: Answer; action?: ReactNode }) {
    const { t } = useTranslation();
    const face = answerFace(answer);
    const ref = useRef<HTMLDivElement>(null);
    const takesFocus = answer.kind === 'CONFLICT' || answer.kind === 'FAILED';
    // Focus moves when an answer arrives, never again on a re-render of the same answer — typing elsewhere on the
    // page must keep its focus (§15).
    const arrival = `${answer.kind}:${'cause' in answer ? answer.cause : ''}`;

    useEffect(() => {
        if (takesFocus) {
            ref.current?.focus();
        }
    }, [arrival, takesFocus]);

    return (
        <div ref={ref} tabIndex={takesFocus ? -1 : undefined} data-answer={answer.kind} className="outline-none">
            <Alert tone={face.tone} icon={face.icon} title={t(face.label)} action={action}>
                {answer.text}
                {answer.kind === 'FAILED' && answer.reference !== undefined && (
                    <span data-code className="mt-1 block text-caption text-text-tertiary">
                        {t('outcome.reference', { kode: answer.reference })}
                    </span>
                )}
            </Alert>
        </div>
    );
}
