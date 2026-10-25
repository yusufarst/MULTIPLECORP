import { Eye, EyeOff } from 'lucide-react';
import { type ComponentProps, type ReactNode, useId, useRef, useState } from 'react';

import { AlertDialog, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { InputGroup, InputGroupInput } from '@/components/ui/input-group';
import { useTranslation } from '@/lib/i18n';

// The session's dialogs (DESIGN_SYSTEM PT-16, PT-28; ADMIN_FLOW IP-10, IP-11): each opens above any dialog or
// sheet, is never covered and returns focus to the element focused before it. The form behind keeps its input,
// its `lock_version` and a pending `command_id` in memory; nothing here writes to browser storage (WS-07, WS-08).
// The password fields allow paste, autocomplete and password managers (WCAG 3.3.8; H7-04).
export function PasswordInput({ autoComplete = 'current-password', ...props }: Omit<ComponentProps<'input'>, 'type'>) {
    const { t } = useTranslation();
    const [shown, setShown] = useState(false);

    return (
        <InputGroup>
            <InputGroupInput {...props} type={shown ? 'text' : 'password'} autoComplete={autoComplete} autoCapitalize="off" spellCheck={false} />
            <Button
                variant="ghost"
                size="icon-sm"
                className="mr-1"
                aria-label={t('pages.pg_001.show')}
                aria-pressed={shown}
                onClick={() => {
                    setShown((value) => !value);
                }}
            >
                <Icon icon={shown ? EyeOff : Eye} size="sm" />
            </Button>
        </InputGroup>
    );
}

/** IP-10's step-up: "Konfirmasi Kata Sandi" naming the account, one password field, MSG-27; "Batal" and "Lanjutkan". */
export function StepUpDialog({
    open,
    account,
    error,
    pending = false,
    onConfirm,
    onCancel,
}: {
    open: boolean;
    account: string;
    error?: string | null;
    pending?: boolean;
    onConfirm: (password: string) => void;
    onCancel: () => void;
}) {
    const { t } = useTranslation();
    const [password, setPassword] = useState('');
    const field = useRef<HTMLInputElement>(null);
    const id = useId();

    return (
        <AlertDialog
            open={open}
            onOpenChange={(next) => {
                if (!next && !pending) {
                    onCancel();
                }
            }}
        >
            <AlertDialogContent initialFocus={field} className="z-(--z-modal)">
                <form
                    className="flex flex-1 flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        onConfirm(password);
                    }}
                >
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('dialogs.step_up_title')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            <span className="font-medium text-text-primary">{account}</span> — {t('messages.msg_27')}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <Field>
                        <FieldLabel htmlFor={id}>{t('pages.pg_001.password')}</FieldLabel>
                        <PasswordInput
                            ref={field}
                            id={id}
                            value={password}
                            aria-invalid={error !== undefined && error !== null ? true : undefined}
                            aria-describedby={`${id}-error`}
                            onChange={(event) => {
                                setPassword(event.target.value);
                            }}
                        />
                        <FieldError id={`${id}-error`}>{error}</FieldError>
                    </Field>
                    <AlertDialogFooter>
                        <Button variant="outline" size="lg" disabled={pending} onClick={onCancel}>
                            {t('actions.cancel')}
                        </Button>
                        <Button type="submit" variant="primary" size="lg" pending={pending} pendingLabel={t('actions.continue')}>
                            {t('actions.continue')}
                        </Button>
                    </AlertDialogFooter>
                </form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/** IP-11's in-place re-authentication: an opaque layer, the account's e-mail and password (MSG-11), the second
    factor where the account has one; only "Keluar" ends it — Esc never does (KB-04). */
export function ReauthDialog({
    open,
    email: initialEmail,
    error,
    pending = false,
    secondFactor,
    onSubmit,
    onSignOut,
}: {
    open: boolean;
    email: string;
    error?: string | null;
    pending?: boolean;
    secondFactor?: ReactNode;
    onSubmit: (credentials: { email: string; password: string }) => void;
    onSignOut: () => void;
}) {
    const { t } = useTranslation();
    const [email, setEmail] = useState(initialEmail);
    const [password, setPassword] = useState('');
    const first = useRef<HTMLInputElement>(null);
    const id = useId();

    return (
        <AlertDialog open={open}>
            <AlertDialogContent opaqueBackdrop initialFocus={first} className="z-(--z-modal)">
                <form
                    className="flex flex-1 flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        onSubmit({ email, password });
                    }}
                >
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('dialogs.reauth_title')}</AlertDialogTitle>
                        <AlertDialogDescription>{t('messages.msg_11')}</AlertDialogDescription>
                    </AlertDialogHeader>
                    {secondFactor ?? (
                        <>
                            <Field>
                                <FieldLabel htmlFor={`${id}-email`}>{t('pages.pg_001.email')}</FieldLabel>
                                <Input
                                    ref={first}
                                    id={`${id}-email`}
                                    type="email"
                                    autoComplete="username"
                                    value={email}
                                    onChange={(event) => {
                                        setEmail(event.target.value);
                                    }}
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor={`${id}-password`}>{t('pages.pg_001.password')}</FieldLabel>
                                <PasswordInput
                                    id={`${id}-password`}
                                    value={password}
                                    aria-invalid={error !== undefined && error !== null ? true : undefined}
                                    aria-describedby={`${id}-error`}
                                    onChange={(event) => {
                                        setPassword(event.target.value);
                                    }}
                                />
                                <FieldError id={`${id}-error`}>{error}</FieldError>
                            </Field>
                        </>
                    )}
                    <AlertDialogFooter>
                        <Button variant="outline" size="lg" disabled={pending} onClick={onSignOut}>
                            {t('dialogs.logout.confirm')}
                        </Button>
                        <Button type="submit" variant="primary" size="lg" pending={pending} pendingLabel={t('actions.continue')}>
                            {t('actions.continue')}
                        </Button>
                    </AlertDialogFooter>
                </form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/** IP-11's idle warning: MSG-33 with the minutes left and "Tetap Masuk", usable any number of times (WCAG 2.2.1). */
export function IdleWarning({ open, minutes, pending = false, onStay }: { open: boolean; minutes: number; pending?: boolean; onStay: () => void }) {
    const { t, tc } = useTranslation();
    const stay = useRef<HTMLButtonElement>(null);

    return (
        <AlertDialog open={open}>
            <AlertDialogContent initialFocus={stay} className="z-(--z-modal) max-md:top-auto">
                <AlertDialogHeader>
                    <AlertDialogTitle>{t('dialogs.idle_title')}</AlertDialogTitle>
                    <AlertDialogDescription aria-live="polite">{tc('messages.msg_33', minutes)}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <Button ref={stay} variant="primary" size="lg" pending={pending} pendingLabel={t('actions.stay_signed_in')} onClick={onStay}>
                        {t('actions.stay_signed_in')}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/** MSG-35 before leaving or signing out with unsent input — a bottom sheet on the phone, focus on "Kembali". */
export function UnsentInputDialog({ open, kind, onStay, onLeave }: { open: boolean; kind: 'leave' | 'logout'; onStay: () => void; onLeave: () => void }) {
    const { t } = useTranslation();
    const back = useRef<HTMLButtonElement>(null);

    return (
        <AlertDialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    onStay();
                }
            }}
        >
            <AlertDialogContent initialFocus={back} className="max-md:top-auto">
                <AlertDialogHeader>
                    <AlertDialogTitle>{t('dialogs.unsent_input')}</AlertDialogTitle>
                    <AlertDialogDescription>{t(kind === 'leave' ? 'messages.msg_35_leave' : 'messages.msg_35_logout')}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <Button ref={back} variant="outline" size="lg" onClick={onStay}>
                        {t('dialogs.back')}
                    </Button>
                    <Button variant="danger" size="lg" onClick={onLeave}>
                        {t(kind === 'leave' ? 'dialogs.leave.confirm' : 'dialogs.logout.confirm')}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
