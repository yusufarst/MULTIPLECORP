import { ChevronDown, CircleCheck, Copy, FileUp, Lock, TriangleAlert, X } from 'lucide-react';
import { type ReactNode, useId, useRef, useState } from 'react';

import { DecimalInput } from '@/components/form';
import { type Answer, OutcomeMessage } from '@/components/outcome-message';
import { RelationMarker } from '@/components/company';
import { TextLink } from '@/components/record-blocks';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Icon } from '@/components/ui/icon';
import { Progress } from '@/components/ui/progress';
import { Textarea } from '@/components/ui/textarea';
import type { MenuAction } from '@/components/page-header';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type DuplicateMatch = { id: string; label: ReactNode; href: string | null };

// IP-05's duplicate warning (ADMIN_FLOW §5; HO-05): a warning, never a block — the first submission came back
// REJECTED with the matches the actor may see; the block lists each with a link, a tick per match and the reason,
// and the second submission is a new intent naming the matches it acknowledges. An actor who may not continue
// sees the matches and MSG-15's path, and nothing is recorded. A match the actor may not view never reaches here.
export function DuplicateWarning({
    matches,
    message,
    acknowledged,
    onAcknowledge,
    reason,
    canContinue,
}: {
    matches: DuplicateMatch[];
    /** MSG-15 as the server composed it for this warning. */
    message: ReactNode;
    acknowledged: string[];
    onAcknowledge: (ids: string[]) => void;
    reason: { value: string; onChange: (value: string) => void; error?: string | null };
    /** False where the warning needs `warnings.override` the actor lacks: matches shown, no second submission. */
    canContinue: boolean;
}) {
    const { t } = useTranslation();
    const id = useId();

    return (
        <Alert tone="warning" icon={TriangleAlert} title={t('outcome.rejected')} data-slot="duplicate-warning">
            <div className="flex flex-col gap-3">
                <p>{message}</p>
                <ul role="list" className="flex flex-col gap-2">
                    {matches.map((match) => (
                        <li key={match.id} className="flex items-start gap-3">
                            {canContinue && (
                                <Checkbox
                                    id={`${id}-${match.id}`}
                                    aria-label={t('duplicates.acknowledge')}
                                    checked={acknowledged.includes(match.id)}
                                    onCheckedChange={(checked) => {
                                        onAcknowledge(checked ? [...acknowledged, match.id] : acknowledged.filter((other) => other !== match.id));
                                    }}
                                />
                            )}
                            {match.href === null ? <span>{match.label}</span> : <TextLink href={match.href}>{match.label}</TextLink>}
                        </li>
                    ))}
                </ul>
                {canContinue && (
                    <Field>
                        <FieldLabel htmlFor={`${id}-reason`}>{t('messages.msg_40')}</FieldLabel>
                        <Textarea
                            id={`${id}-reason`}
                            required
                            aria-invalid={reason.error !== undefined && reason.error !== null ? true : undefined}
                            aria-describedby={`${id}-reason-error`}
                            value={reason.value}
                            onChange={(event) => {
                                reason.onChange(event.target.value);
                            }}
                        />
                        <FieldError id={`${id}-reason-error`}>{reason.error}</FieldError>
                    </Field>
                )}
            </div>
        </Alert>
    );
}

export type StaleField = { key: string; label: string; current: ReactNode; mine: ReactNode; differs: boolean; bothChanged?: boolean };

/** HO-04's stale edit (IP-03; MSG-03): the current version beside the user's kept input, the differing fields
    marked; "Terapkan Ulang pada Versi Terbaru" carries only the user's changes onto it — a field changed on both
    sides needs a choice first — or "Buang Isian Saya". The re-apply is submitted as a new intent. */
export function StaleComparison({ fields, onReapply, onDiscard }: { fields: StaleField[]; onReapply: () => void; onDiscard: () => void }) {
    const { t } = useTranslation();
    const unresolved = fields.some((field) => field.bothChanged === true);

    return (
        <div data-slot="stale-comparison" className="flex min-w-0 flex-col gap-3">
            <dl className="flex min-w-0 flex-col divide-y divide-border-subtle rounded-md border border-border">
                {fields.map((field) => (
                    <div key={field.key} data-differs={field.differs || undefined} className={cn('grid min-w-0 gap-1 p-3 md:grid-cols-3 md:gap-4', field.differs && 'bg-tint-peach')}>
                        <dt className="text-label font-medium text-text-primary">
                            {field.label}
                            {field.differs && <Icon icon={TriangleAlert} size="xs" label={t('outcome.conflict_stale')} className="ml-1 inline text-warning-fg" />}
                        </dt>
                        <dd className="min-w-0 text-body text-text-secondary">{field.current}</dd>
                        <dd className="min-w-0 text-body text-text-primary">{field.mine}</dd>
                    </div>
                ))}
            </dl>
            <div className="flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                <Button variant="ghost" size="lg" onClick={onDiscard}>
                    {t('actions.discard_mine')}
                </Button>
                <Button variant="primary" size="lg" disabled={unresolved} onClick={onReapply}>
                    {t('actions.reapply_latest')}
                </Button>
            </div>
        </div>
    );
}

export type Reservation = { id: string; reserved: string; project: { label: string; href: string } | null };

/** IP-20's reservation cut: the shortfall and the product's active reservations — a project named where its company
    is in scope, otherwise the relation marker —; the actor enters each cut; nothing is proposed or cut by the system. */
export function ReservationCut({
    shortfall,
    unit,
    reservations,
    cuts,
    onCut,
    scale,
}: {
    shortfall: ReactNode;
    unit: string;
    reservations: Reservation[];
    cuts: Record<string, string>;
    onCut: (id: string, decimal: string | null) => void;
    scale: number;
}) {
    const { t } = useTranslation();
    const id = useId();

    return (
        <section aria-labelledby={id} data-slot="reservation-cut" className="flex min-w-0 flex-col gap-3 rounded-md border border-border p-3">
            <h3 id={id} className="flex items-center gap-2 text-body font-semibold text-text-primary">
                <Icon icon={TriangleAlert} size="md" className="text-warning-fg" />
                {t('forms.reservation_cut')}
            </h3>
            <p className="text-body text-text-primary">{shortfall}</p>
            <ul role="list" className="flex flex-col divide-y divide-border-subtle">
                {reservations.map((reservation) => (
                    <li key={reservation.id} className="flex min-w-0 flex-wrap items-center justify-between gap-3 py-2">
                        <span className="flex min-w-0 flex-col">
                            {reservation.project === null ? <RelationMarker /> : <TextLink href={reservation.project.href}>{reservation.project.label}</TextLink>}
                            <span className="text-caption text-text-tertiary tabular-nums">
                                {reservation.reserved} {unit}
                            </span>
                        </span>
                        <DecimalInput
                            aria-label={t('actions.reduce_reservation')}
                            className="w-32"
                            scale={scale}
                            unit={unit}
                            value={cuts[reservation.id] ?? ''}
                            onValueChange={(decimal) => {
                                onCut(reservation.id, decimal);
                            }}
                        />
                    </li>
                ))}
            </ul>
        </section>
    );
}

export type UploadState = { phase: 'idle' } | { phase: 'uploading'; progress: number } | { phase: 'done'; name: string } | { phase: 'error'; message: ReactNode };

/** IP-16's upload: the file picker — the camera on a phone —, the purpose's types and limit stated, a refused file
    answered with MSG-31 before anything is sent; the upload runs outside any command with its progress and
    "Batalkan"; drag and drop only as an addition. The page registers the evidence with its own command after. */
export function UploadField({
    label,
    accept,
    limits,
    state,
    onChoose,
    onCancel,
    onRemove,
    onRetry,
}: {
    label: string;
    accept: string;
    /** The purpose's allowed types and limit (FL-02), as the server states them. */
    limits: ReactNode;
    state: UploadState;
    onChoose: (file: File) => void;
    onCancel: () => void;
    onRemove: () => void;
    onRetry: () => void;
}) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const id = useId();

    return (
        <Field data-slot="upload">
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <div
                data-dragging={dragging || undefined}
                className={cn('flex min-w-0 flex-col gap-3 rounded-md border border-dashed border-border-dashed bg-surface p-4', dragging && 'border-accent-olive bg-tint-lime')}
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => {
                    setDragging(false);
                }}
                onDrop={(event) => {
                    event.preventDefault();
                    setDragging(false);
                    const file = event.dataTransfer.files[0];

                    if (file !== undefined && state.phase === 'idle') {
                        onChoose(file);
                    }
                }}
            >
                <input
                    ref={input}
                    id={id}
                    type="file"
                    accept={accept}
                    className="sr-only"
                    aria-describedby={`${id}-limits`}
                    onChange={(event) => {
                        const file = event.target.files?.[0];

                        if (file !== undefined) {
                            onChoose(file);
                        }

                        event.target.value = '';
                    }}
                />
                {state.phase === 'idle' && (
                    <div className="flex flex-wrap items-center gap-3">
                        <Button variant="outline" size="md" onClick={() => input.current?.click()}>
                            <Icon icon={FileUp} size="sm" />
                            {t('files.choose')}
                        </Button>
                    </div>
                )}
                {state.phase === 'uploading' && (
                    <div className="flex flex-col gap-2" aria-live="polite">
                        <Progress label={t('files.uploading')} value={state.progress} valueText={`${String(Math.round(state.progress))}%`} />
                        <div>
                            <Button variant="ghost" size="sm" onClick={onCancel}>
                                {t('files.cancel')}
                            </Button>
                        </div>
                    </div>
                )}
                {state.phase === 'done' && (
                    <p role="status" className="flex min-w-0 items-center gap-2 text-body text-text-primary">
                        <Icon icon={CircleCheck} size="sm" className="text-success-fg" />
                        <span className="min-w-0 truncate">{state.name}</span>
                        <span className="text-text-tertiary">· {t('files.uploaded')}</span>
                        <Button variant="ghost" size="icon-sm" className="ml-auto" aria-label={t('files.remove')} onClick={onRemove}>
                            <Icon icon={X} size="sm" />
                        </Button>
                    </p>
                )}
                {state.phase === 'error' && (
                    <div role="alert" className="flex flex-col gap-2">
                        <FieldError>{state.message}</FieldError>
                        <div>
                            <Button variant="outline" size="sm" onClick={onRetry}>
                                {t('actions.try_again')}
                            </Button>
                        </div>
                    </div>
                )}
                <p id={`${id}-limits`} className="text-caption text-text-tertiary">
                    {limits}
                </p>
            </div>
        </Field>
    );
}

/** The split action (§10): the primary segment and "Aksi lain" opening a menu, the destructive entry last. */
export function SplitAction({ primary, actions, size = 'md' }: { primary: ReactNode; actions: MenuAction[]; size?: 'md' | 'lg' }) {
    const { t } = useTranslation();
    const ordered = [...actions.filter((action) => action.tone !== 'danger'), ...actions.filter((action) => action.tone === 'danger')];

    return (
        <ButtonGroup>
            {primary}
            <DropdownMenu>
                <DropdownMenuTrigger render={<Button variant="primary" size={size === 'lg' ? 'icon-lg' : 'icon-md'} aria-label={t('actions.split_more')} className="border-l-primary-hover" />}>
                    <Icon icon={ChevronDown} size="sm" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {ordered.map((action) => (
                        <DropdownMenuItem key={action.label} tone={action.tone} onClick={action.onSelect}>
                            {action.label}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </ButtonGroup>
    );
}

export type CommandStep = { key: string; label: ReactNode; answer: Answer | null; action?: ReactNode };

/** IP-19's several commands on one screen: each with its own button, `command_id` and answer, listed one below the
    other — never one combined "Tersimpan"; a later failure leaves the earlier ones shown as committed (PX-05). */
export function CommandSteps({ steps }: { steps: CommandStep[] }) {
    return (
        <ol data-slot="command-steps" className="flex min-w-0 flex-col gap-4">
            {steps.map((step) => (
                <li key={step.key} className="flex min-w-0 flex-col gap-2">
                    <div className="flex min-w-0 flex-wrap items-center justify-between gap-3">
                        <span className="text-body font-medium text-text-primary">{step.label}</span>
                        {step.action}
                    </div>
                    <div aria-live={step.answer === null || step.answer.kind === 'COMMITTED' || step.answer.kind === 'PENDING' ? 'polite' : 'assertive'}>
                        {step.answer !== null && <OutcomeMessage answer={step.answer} />}
                    </div>
                </li>
            ))}
        </ol>
    );
}

/** The result of a committed command (§10 "Checks, result, next step and prerequisite"): the number as headline
    with "Salin nomor", the document's rendition state and one next action; no toast for the same event. */
export function CommandResult({ title, number, rendition, next }: { title: ReactNode; number?: string; rendition?: ReactNode; next?: ReactNode }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);

    return (
        <Card data-slot="command-result" className="flex flex-col gap-3 p-4">
            <p className="flex items-center gap-2 text-body font-semibold text-text-primary">
                <Icon icon={CircleCheck} size="md" className="text-success-fg" />
                {title}
            </p>
            {number !== undefined && (
                <div className="flex flex-wrap items-center gap-3">
                    <span data-code className="text-heading font-semibold whitespace-nowrap text-text-primary">
                        {number}
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            void navigator.clipboard.writeText(number).then(() => {
                                setCopied(true);
                            });
                        }}
                    >
                        <Icon icon={Copy} size="sm" />
                        {t('actions.copy_number')}
                    </Button>
                    <span role="status" className="text-caption text-text-secondary">
                        {copied ? t('result.copied') : ''}
                    </span>
                </div>
            )}
            {rendition !== undefined && <div className="text-body text-text-secondary">{rendition}</div>}
            {next !== undefined && <div className="flex">{next}</div>}
        </Card>
    );
}

/** A prerequisite the account lacks: "Perlu kewenangan …" with nothing to press (§10). */
export function Prerequisite({ capability }: { capability: string }) {
    const { t } = useTranslation();

    return (
        <p data-slot="prerequisite" className="flex items-center gap-2 text-body text-text-secondary">
            <Icon icon={Lock} size="sm" className="text-text-tertiary" />
            {t('prerequisite.needs', { nama: capability })}
        </p>
    );
}
