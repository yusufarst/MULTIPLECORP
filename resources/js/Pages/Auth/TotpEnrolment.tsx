// PG-004 *Aktifkan Verifikasi Dua Langkah* (ROUTE-004, ROUTE-107; NAV-004; SECURITY AU-16, AU-18, AU-19; TASK-018):
// the enrolment-only session's single path. The QR code and the text key come as flash data — never a page prop kept
// in history — and only during the enrolment; one code confirms; past 15 minutes the password is confirmed again
// first (AU-15; H7-15); the eight recovery codes are then shown once, as flash data, and "Lanjutkan" loads the
// landing page as a full document. "Keluar" ends the session. The history is cleared here (H7-12). Inside the
// session the account's own language applies, so the page has no language switch (AU-13).
// APPR-042's pilot (CR-002 K-5; DIR-134 E5): the path as three numbered steps in the shared sign-in frame; the text key
// and the codes can be copied on the person's own click; "Lanjutkan" waits for the person's acknowledgement that the
// codes are saved. The codes are only rendered: never logged, stored or sent from here.
import { router, useForm, usePage } from '@inertiajs/react';
import { type ReactNode, useEffect, useId, useRef, useState } from 'react';

import { CopyButton } from '@/components/copy-button';
import { QrCode } from '@/components/qr-code';
import { PasswordInput } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';
import { postAnswer } from '@/lib/session-guard';

type Flash = { qr?: unknown; key?: unknown; recovery_codes?: unknown };

const STEPS = '3';

function Step({ number, title, children }: { number: string; title: string; children: ReactNode }) {
    const { t } = useTranslation();
    const id = useId();

    return (
        <section aria-labelledby={id} className="flex flex-col gap-4">
            <div className="flex flex-col gap-1">
                <p className="text-caption font-medium text-text-tertiary">{t('pages.pg_004.step_of', { nomor: number, jumlah: STEPS })}</p>
                <h2 id={id} className="text-heading-sm font-semibold text-text-primary">
                    {title}
                </h2>
            </div>
            {children}
        </section>
    );
}

export default function TotpEnrolment({ stage, replacing }: { stage: 'enrol' | 'codes'; replacing: boolean }) {
    const { t } = useTranslation();
    const id = useId();
    const page = usePage<{ errors: Record<string, string | undefined>; landing?: string }>();
    const flash = page.flash as Flash;
    const form = useForm({ code: '' });
    const [password, setPassword] = useState('');
    const [confirmError, setConfirmError] = useState<string | null>(null);
    const [confirming, setConfirming] = useState(false);
    const [confirmed, setConfirmed] = useState(false);
    const [saved, setSaved] = useState(false);
    const keyRef = useRef<HTMLElement>(null);
    const codesRef = useRef<HTMLUListElement>(null);

    useEffect(() => {
        router.clearHistory();
    }, []);

    const qr = Array.isArray(flash.qr) ? flash.qr.filter((row): row is string => typeof row === 'string') : null;
    const key = typeof flash.key === 'string' ? flash.key : null;
    const codes = Array.isArray(flash.recovery_codes) ? flash.recovery_codes.filter((code): code is string => typeof code === 'string') : [];
    // MSG-27: the login's password step counted for 15 minutes; ROUTE-109 confirms it again.
    const passwordLine = page.props.errors.password;
    const passwordDue = passwordLine !== undefined && !confirmed;

    if (stage === 'codes') {
        return (
            <AuthLayout title={t('pages.pg_004.title')} caption={t('pages.pg_004.step_of', { nomor: '3', jumlah: STEPS })} heading={t('pages.pg_004.step_save')} languageSwitch={false}>
                <div className="flex flex-col gap-2">
                    <p role="status" className="text-body font-medium text-text-primary">
                        {t('pages.pg_004.codes_line')}
                    </p>
                    <p className="text-body text-text-secondary">{t('pages.pg_004.codes_use')}</p>
                </div>
                <ul ref={codesRef} className="grid grid-cols-1 gap-1 rounded-lg border border-border bg-canvas p-3 font-mono text-body text-text-primary" aria-label={t('pages.pg_004.codes_heading')}>
                    {codes.map((code) => (
                        <li key={code} data-code className="rounded-sm px-2 py-1 break-all">
                            {code}
                        </li>
                    ))}
                </ul>
                <CopyButton
                    label={t('pages.pg_004.copy_codes')}
                    text={() => codes.join('\n')}
                    source={() => codesRef.current}
                />
                <div className="flex items-start gap-3 rounded-lg border border-border p-3">
                    <Checkbox
                        id={`${id}-saved`}
                        checked={saved}
                        onCheckedChange={(checked) => {
                            setSaved(checked);
                        }}
                    />
                    <label htmlFor={`${id}-saved`} className="text-body text-text-primary">
                        {t('pages.pg_004.saved_ack')}
                    </label>
                </div>
                <Button
                    variant="primary"
                    size="lg"
                    disabled={!saved}
                    onClick={() => {
                        router.clearHistory();
                        window.location.assign(page.props.landing ?? '/pekerjaan');
                    }}
                >
                    {t('pages.pg_004.continue')}
                </Button>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title={t('pages.pg_004.title')} heading={t('pages.pg_004.heading')} languageSwitch={false}>
            {replacing && <p className="text-body text-text-primary">{t('pages.pg_004.reset_line')}</p>}
            <Step number="1" title={t('pages.pg_004.step_scan')}>
                <p className="text-body text-text-secondary">{t('pages.pg_004.scan')}</p>
                {qr !== null && (
                    <div className="self-center rounded-lg border border-border bg-surface p-3">
                        <QrCode rows={qr} label={t('pages.pg_004.start')} />
                    </div>
                )}
                {key !== null && (
                    <div className="flex flex-col gap-2">
                        <span className="text-label font-medium text-text-secondary">{t('pages.pg_004.key_label')}</span>
                        <code ref={keyRef} data-code className="rounded-md border border-border bg-canvas px-3 py-2 font-mono text-body break-all text-text-primary">
                            {key}
                        </code>
                        <CopyButton label={t('pages.pg_004.copy_key')} text={() => key} source={() => keyRef.current} />
                    </div>
                )}
            </Step>
            <Step number="2" title={t('pages.pg_004.step_code')}>
                {passwordDue && (
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            setConfirming(true);
                            void postAnswer('/konfirmasi-kata-sandi', { password }).then((answer) => {
                                setConfirming(false);
                                setPassword('');

                                if (answer.outcome === 'ok') {
                                    setConfirmError(null);
                                    setConfirmed(true);
                                } else {
                                    setConfirmError(answer.outcome === 'limited' ? (answer.message ?? t('messages.msg_19')) : t('messages.msg_19'));
                                }
                            });
                        }}
                    >
                        <Field>
                            <FieldLabel htmlFor={`${id}-password`}>{t('pages.pg_001.password')}</FieldLabel>
                            <PasswordInput
                                id={`${id}-password`}
                                name="password"
                                required
                                value={password}
                                aria-invalid={confirmError !== null ? true : undefined}
                                aria-describedby={`${id}-password-line ${id}-password-error`}
                                onChange={(event) => {
                                    setPassword(event.target.value);
                                }}
                            />
                            <FieldDescription id={`${id}-password-line`}>{passwordLine}</FieldDescription>
                            <FieldError id={`${id}-password-error`}>{confirmError ?? undefined}</FieldError>
                        </Field>
                        <Button type="submit" variant="outline" size="lg" pending={confirming} pendingLabel={t('pages.pg_004.confirm_submit')}>
                            {t('pages.pg_004.confirm_submit')}
                        </Button>
                    </form>
                )}
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/verifikasi-dua-langkah', {
                            onFinish: () => {
                                form.reset('code');
                            },
                        });
                    }}
                >
                    <Field>
                        <FieldLabel htmlFor={`${id}-code`}>{t('pages.pg_002.code_label')}</FieldLabel>
                        <Input
                            id={`${id}-code`}
                            name="code"
                            autoComplete="one-time-code"
                            inputMode="numeric"
                            maxLength={6}
                            required
                            data-code
                            value={form.data.code}
                            aria-invalid={form.errors.code !== undefined ? true : undefined}
                            aria-describedby={`${id}-code-error`}
                            onChange={(event) => {
                                form.setData('code', event.target.value);
                            }}
                        />
                        <FieldError id={`${id}-code-error`}>{form.errors.code}</FieldError>
                    </Field>
                    <Button type="submit" variant="primary" size="lg" disabled={passwordDue} pending={form.processing} pendingLabel={t('pages.pg_004.submit')}>
                        {t('pages.pg_004.submit')}
                    </Button>
                </form>
            </Step>
            <div className="flex flex-col items-start gap-1 border-t border-border pt-4">
                <Button
                    variant="ghost"
                    size="sm"
                    className="-ml-4"
                    onClick={() => {
                        router.post('/keluar');
                    }}
                >
                    {t('dialogs.logout.confirm')}
                </Button>
            </div>
        </AuthLayout>
    );
}
