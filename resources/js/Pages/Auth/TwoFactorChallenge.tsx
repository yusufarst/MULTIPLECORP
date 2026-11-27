// PG-002 *Verifikasi Dua Langkah* (ROUTE-002, ROUTE-104, ROUTE-105; NAV-002; SECURITY AU-16, AU-19; TASK-018): the
// six-digit code of the authenticator app, or — after a password only — one recovery code; one submit, the generic
// code answer for every failing code and MSG-20 in a cooldown, never which part was wrong (AU-04, AU-05; HO-40).
// "Kembali" returns to PG-001 with the e-mail kept, whose view discards the pending attempt (OQ-14). An
// authentication boundary: the history is cleared here, and a success loads the next page as a full document.
// APPR-042's pilot: the page sits in the shared sign-in frame (CR-002 K-5); its behaviour is unchanged.
import { router, useForm } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/AuthLayout';
import { languageQuery, useTranslation } from '@/lib/i18n';

export default function TwoFactorChallenge({ recovery }: { recovery: boolean }) {
    const { t } = useTranslation();
    const id = useId();
    const [useRecovery, setUseRecovery] = useState(false);
    const [noApp, setNoApp] = useState(false);
    const form = useForm({ code: '', recovery_code: '' });

    useEffect(() => {
        router.clearHistory();
    }, []);

    const field = useRecovery ? 'recovery_code' : 'code';
    const error = form.errors[field];

    return (
        <AuthLayout title={t('pages.pg_002.title')} heading={t('pages.pg_002.heading')}>
            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(`${useRecovery ? '/masuk/verifikasi/pemulihan' : '/masuk/verifikasi'}${languageQuery()}`, {
                        onFinish: () => {
                            form.reset(field);
                        },
                    });
                }}
            >
                <Field>
                    <FieldLabel htmlFor={`${id}-${field}`}>{t(useRecovery ? 'pages.pg_002.recovery_label' : 'pages.pg_002.code_label')}</FieldLabel>
                    <Input
                        id={`${id}-${field}`}
                        key={field}
                        name={field}
                        autoComplete={useRecovery ? 'off' : 'one-time-code'}
                        inputMode={useRecovery ? 'text' : 'numeric'}
                        maxLength={useRecovery ? 64 : 6}
                        required
                        data-code
                        value={form.data[field]}
                        aria-invalid={error !== undefined ? true : undefined}
                        aria-describedby={`${id}-advice ${id}-error`}
                        onChange={(event) => {
                            form.setData(field, event.target.value);
                        }}
                    />
                    <FieldDescription id={`${id}-advice`}>{t('pages.pg_002.code_advice')}</FieldDescription>
                    <FieldError id={`${id}-error`}>{error}</FieldError>
                </Field>
                <Button type="submit" variant="primary" size="lg" className="mt-2" pending={form.processing} pendingLabel={t('pages.pg_002.submit')}>
                    {t('pages.pg_002.submit')}
                </Button>
            </form>
            <div className="flex flex-col items-start gap-1 border-t border-border pt-4">
                {recovery ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="-ml-4"
                        onClick={() => {
                            form.clearErrors();
                            setUseRecovery(!useRecovery);
                        }}
                    >
                        {t(useRecovery ? 'pages.pg_002.use_code' : 'pages.pg_002.use_recovery')}
                    </Button>
                ) : (
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="-ml-4"
                            aria-expanded={noApp}
                            onClick={() => {
                                setNoApp(true);
                            }}
                        >
                            {t('pages.pg_002.no_app')}
                        </Button>
                        {noApp && <p className="pb-2 text-body text-text-secondary">{t('pages.pg_002.no_app_answer')}</p>}
                    </>
                )}
                <Button
                    variant="ghost"
                    size="sm"
                    className="-ml-4"
                    onClick={() => {
                        window.location.assign(`/masuk${languageQuery()}`);
                    }}
                >
                    {t('pages.pg_002.back')}
                </Button>
            </div>
        </AuthLayout>
    );
}
