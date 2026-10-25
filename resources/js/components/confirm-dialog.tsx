import { type ReactNode, type RefObject, useId, useRef } from 'react';

import { ActionBar } from '@/components/action-bar';
import type { Answer } from '@/components/outcome-message';
import { AlertDialog, AlertDialogContent, AlertDialogDescription, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/lib/i18n';
import { useShortcut } from '@/lib/shortcuts';

export type ConfirmTick = { id: string; label: ReactNode; checked: boolean };

// PT-26's confirmation of an irreversible or correcting command (DESIGN_SYSTEM §9.4; ADMIN_FLOW IP-08, IP-09;
// MSG-39, MSG-40): an AlertDialog titled "{Kata kerja} {objek}?", the consequence and how a mistake is corrected,
// the affected items, the reason where required, the numbering statements with their required tick; the
// confirming button carries the action's verb, "Kembali" beside it — never "Ya" or "OK". It opens with focus on
// its first input or, when there is none, on "Kembali", never on the confirming button, so a scanner's Enter
// cannot record a fact (§13.2). The dialog's button alone sends the command; its answer appears in the dialog's
// action region (D-DS-06). On the phone it is a full-height sheet with the button at the bottom.
export function ConfirmCommand({
    open,
    onOpenChange,
    title,
    consequence,
    items,
    reason,
    ticks = [],
    onTick,
    confirmLabel,
    destructive = false,
    pending = false,
    answer,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    /** MSG-39's consequence and correction path, the page's catalogue string. */
    consequence: ReactNode;
    items?: ReactNode;
    reason?: { value: string; onChange: (value: string) => void; error?: string | null };
    ticks?: ConfirmTick[];
    onTick?: (id: string, checked: boolean) => void;
    confirmLabel: string;
    /** Ends or reverses something — the destructive fill on the confirming button only (PT-11). */
    destructive?: boolean;
    pending?: boolean;
    answer?: Answer | null;
    onConfirm: () => void;
}) {
    const { t } = useTranslation();
    const back = useRef<HTMLButtonElement>(null);
    const first = useRef<HTMLTextAreaElement>(null);
    const reasonId = useId();
    const ready = (reason === undefined || reason.value.trim() !== '') && ticks.every((tick) => tick.checked);

    return (
        <AlertDialog
            open={open}
            onOpenChange={(next) => {
                if (!pending) {
                    onOpenChange(next);
                }
            }}
        >
            <AlertDialogContent initialFocus={reason !== undefined ? first : ticks.length > 0 ? undefined : back} className="md:w-sheet-sm">
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>{consequence}</AlertDialogDescription>
                </AlertDialogHeader>
                {items !== undefined && <div className="min-w-0 rounded-md border border-border-subtle bg-surface-sunken p-3">{items}</div>}
                {ticks.length > 0 && (
                    <ul role="list" className="flex flex-col gap-3">
                        {ticks.map((tick) => (
                            <li key={tick.id} className="flex items-start gap-3">
                                <Checkbox
                                    id={`${reasonId}-${tick.id}`}
                                    checked={tick.checked}
                                    required
                                    onCheckedChange={(checked) => {
                                        onTick?.(tick.id, checked);
                                    }}
                                />
                                <label htmlFor={`${reasonId}-${tick.id}`} className="text-body text-text-primary">
                                    {tick.label}
                                </label>
                            </li>
                        ))}
                    </ul>
                )}
                {reason !== undefined && (
                    <Field>
                        <FieldLabel htmlFor={reasonId}>{t('messages.msg_40')}</FieldLabel>
                        <Textarea
                            ref={first}
                            id={reasonId}
                            required
                            aria-required
                            aria-invalid={reason.error !== undefined && reason.error !== null ? true : undefined}
                            aria-describedby={`${reasonId}-error`}
                            value={reason.value}
                            onChange={(event) => {
                                reason.onChange(event.target.value);
                            }}
                        />
                        <FieldError id={`${reasonId}-error`}>{reason.error}</FieldError>
                    </Field>
                )}
                <ActionBar inline answer={answer} label={title}>
                    <Button
                        ref={back}
                        variant="outline"
                        size="lg"
                        disabled={pending}
                        onClick={() => {
                            onOpenChange(false);
                        }}
                    >
                        {t('dialogs.back')}
                    </Button>
                    <Button variant={destructive ? 'destructive' : 'primary'} size="lg" pending={pending} pendingLabel={confirmLabel} disabled={!ready} onClick={onConfirm}>
                        {confirmLabel}
                    </Button>
                </ActionBar>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/** KB-10: Ctrl+Enter inside a command form opens its confirming step; only the dialog's button sends (§13.1). */
export function useConfirmKey(form: RefObject<HTMLElement | null>, openConfirmation: () => void, enabled = true): void {
    useShortcut({ key: 'Enter', ctrl: true }, openConfirmation, form, enabled);
}
