// PG-004 *Aktifkan Verifikasi Dua Langkah* (ROUTE-004, ROUTE-107; NAV-004; SECURITY AU-16, AU-18, AU-19; TASK-018):
// the enrolment-only session's single path. The QR code and the text key come as flash data — never a page prop kept
// in history — and only during the enrolment; one code confirms; past 15 minutes the password is confirmed again
// first (AU-15; H7-15); the eight recovery codes are then shown once, as flash data, and "Lanjutkan" loads the
// landing page as a full document. "Keluar" ends the session. The history is cleared here (H7-12).
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';

import LanguageSwitch from '@/components/LanguageSwitch';
import { PasswordInput } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { postAnswer } from '@/lib/session-guard';
import { documentTitle } from '@/lib/title';

type Flash = { qr?: unknown; key?: unknown; recovery_codes?: unknown };

/** The enrolment's QR code, encoded by the server and drawn here from its modules — no remote image, no raw markup. */
function QrCode({ rows, label }: { rows: string[]; label: string }) {
    const size = rows.length;
    const quiet = 4;

    return (
        <svg
            role="img"
            aria-label={label}
            viewBox={`${String(-quiet)} ${String(-quiet)} ${String(size + quiet * 2)} ${String(size + quiet * 2)}`}
            className="size-48 self-start bg-white"
            shapeRendering="crispEdges"
        >
            {rows.flatMap((row, y) =>
                Array.from({ length: row.length }, (_, x) =>
                    row.charAt(x) === '1' ? <rect key={`${String(x)}-${String(y)}`} x={x} y={y} width={1} height={1} fill="#111827" /> : null,
                ),
            )}
        </svg>
    );
}

export default function TotpEnrolment({ stage, replacing }: { stage: 'enrol' | 'codes'; replacing: boolean }) {
    const { t } = useTranslation();
    const id = useId();
    const page = usePage<{ errors: Record<string, string | undefined> }>();
    const flash = page.flash as Flash;
    const form = useForm({ code: '' });
    const [password, setPassword] = useState('');
    const [confirmError, setConfirmError] = useState<string | null>(null);
    const [confirming, setConfirming] = useState(false);
    const [confirmed, setConfirmed] = useState(false);

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
            <main id="konten" className="mx-auto flex min-h-dvh w-full max-w-sheet-sm flex-col gap-6 bg-canvas px-m-gutter py-8">
                <Head title={documentTitle(t('pages.pg_004.title'))} />
                <h1 className="text-m-title font-semibold text-text-primary md:text-title">{t('pages.pg_004.codes_heading')}</h1>
                <p role="status" className="text-body text-text-primary">
                    {t('pages.pg_004.codes_line')}
                </p>
                <ul className="grid grid-cols-1 gap-2 font-mono text-body text-text-primary sm:grid-cols-2" aria-label={t('pages.pg_004.codes_heading')}>
                    {codes.map((code) => (
                        <li key={code} className="rounded-md border border-border px-3 py-2">
                            {code}
                        </li>
                    ))}
                </ul>
                <Button
                    variant="primary"
                    size="lg"
                    onClick={() => {
                        router.clearHistory();
                        window.location.assign('/');
                    }}
                >
                    {t('pages.pg_004.continue')}
                </Button>
            </main>
        );
    }

    return (
        <main id="konten" className="mx-auto flex min-h-dvh w-full max-w-sheet-sm flex-col gap-6 bg-canvas px-m-gutter py-8">
            <Head title={documentTitle(t('pages.pg_004.title'))} />
            <div className="flex justify-end">
                <LanguageSwitch />
            </div>
            <h1 className="text-m-title font-semibold text-text-primary md:text-title">{t('pages.pg_004.heading')}</h1>
            {replacing && <p className="text-body text-text-primary">{t('pages.pg_004.reset_line')}</p>}
            <p className="text-body text-text-secondary">{t('pages.pg_004.scan')}</p>
            {qr !== null && <QrCode rows={qr} label={t('pages.pg_004.start')} />}
            {key !== null && (
                <div className="flex flex-col gap-1">
                    <span className="text-body text-text-secondary">{t('pages.pg_004.key_label')}</span>
                    <code className="font-mono text-body break-all text-text-primary">{key}</code>
                </div>
            )}
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
            <div className="flex flex-col items-start gap-1">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        router.post('/keluar');
                    }}
                >
                    {t('dialogs.logout.confirm')}
                </Button>
            </div>
        </main>
    );
}
