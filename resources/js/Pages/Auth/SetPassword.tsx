// PG-003 *Atur Kata Sandi* (ROUTE-003, ROUTE-106; NAV-003; SECURITY AU-03, AU-11, AU-12; TASK-017): the token comes
// in the address's fragment, which no request carries; on load the page moves it into memory and takes it out of
// the address and the history entry, keeps it in no page state, and sends it in the request body. A used, expired
// or revoked link answers MSG-28 on the page; the password rules answer at the field. The language switch keeps
// the token in memory (INT-001). An authentication boundary: the history is cleared here (AU-06).
// APPR-042's pilot: the page sits in the shared sign-in frame (CR-002 K-5); its behaviour is unchanged.
import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';

import { PasswordInput } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import AuthLayout from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';

export default function SetPassword() {
    const { locale, t } = useTranslation();
    const id = useId();
    const token = useRef('');
    const [missing, setMissing] = useState(false);
    const form = useForm({ password: '', password_confirmation: '' });
    const tokenError = usePage<{ errors: Record<string, string | undefined> }>().props.errors.token;

    useEffect(() => {
        const fragment = window.location.hash.replace(/^#/, '');

        if (fragment !== '') {
            token.current = fragment;
            router.replace({ url: window.location.pathname + window.location.search, preserveState: true, preserveScroll: true });
        }

        setMissing(token.current === '');
        router.clearHistory();
    }, []);

    // A secret never survives the language switch; the token does, in memory (INT-001).
    useEffect(() => {
        form.setData({ password: '', password_confirmation: '' });
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only the language's change clears it
    }, [locale]);

    const linkError = missing ? t('messages.msg_28') : tokenError;

    return (
        <AuthLayout title={t('pages.pg_003.title')} heading={t('pages.pg_003.heading')} head={<meta name="referrer" content="no-referrer" />}>
            {linkError !== undefined && (
                <p role="alert" className="rounded-md bg-danger-bg px-3 py-2 text-body font-medium text-danger-fg">
                    {linkError}
                </p>
            )}
            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({ ...data, token: token.current }));
                    form.post('/atur-kata-sandi', {
                        onFinish: () => {
                            form.reset();
                        },
                    });
                }}
            >
                <Field>
                    <FieldLabel htmlFor={`${id}-password`}>{t('pages.pg_003.password')}</FieldLabel>
                    <PasswordInput
                        id={`${id}-password`}
                        name="password"
                        autoComplete="new-password"
                        required
                        disabled={missing}
                        value={form.data.password}
                        aria-invalid={form.errors.password !== undefined ? true : undefined}
                        aria-describedby={`${id}-rules ${id}-error`}
                        onChange={(event) => {
                            form.setData('password', event.target.value);
                        }}
                    />
                    <FieldDescription id={`${id}-rules`}>{t('auth_rules.password_profile')}</FieldDescription>
                    <FieldError id={`${id}-error`}>{form.errors.password}</FieldError>
                </Field>
                <Field>
                    <FieldLabel htmlFor={`${id}-confirm`}>{t('pages.pg_003.confirm')}</FieldLabel>
                    <PasswordInput
                        id={`${id}-confirm`}
                        name="password_confirmation"
                        autoComplete="new-password"
                        required
                        disabled={missing}
                        value={form.data.password_confirmation}
                        onChange={(event) => {
                            form.setData('password_confirmation', event.target.value);
                        }}
                    />
                </Field>
                <Button type="submit" variant="primary" size="lg" className="mt-2" disabled={missing} pending={form.processing} pendingLabel={t('pages.pg_003.submit')}>
                    {t('pages.pg_003.submit')}
                </Button>
            </form>
        </AuthLayout>
    );
}
