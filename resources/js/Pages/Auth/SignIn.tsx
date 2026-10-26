// PG-001 *Masuk* (ROUTE-001, ROUTE-101; NAV-001; SECURITY AU-04, AU-05; TASK-017): the e-mail and the password, one
// submit, AU-04's one generic answer and AU-05's throttled one, never which part was wrong. The language switch keeps
// the typed e-mail and never the password (INT-001). An authentication boundary: the history is cleared here, and a
// success loads the next page as a full document (AU-06; H7-12). "Lupa kata sandi?" answers that the Owner issues a
// reset link (DEP-08) — no reset request exists.
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';

import LanguageSwitch from '@/components/LanguageSwitch';
import { PasswordInput } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { languageQuery, useTranslation } from '@/lib/i18n';
import { documentTitle } from '@/lib/title';

export default function SignIn({ notice, email = null }: { notice: string | null; email?: string | null }) {
    const { locale, t } = useTranslation();
    const id = useId();
    // The e-mail of a second-factor attempt left with "Kembali", or expired, is kept (NAV-002).
    const form = useForm({ email: email ?? '', password: '' });
    const [forgot, setForgot] = useState(false);

    useEffect(() => {
        router.clearHistory();
    }, []);

    // A secret never survives the language switch (INT-001).
    useEffect(() => {
        form.setData('password', '');
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only the language's change clears it
    }, [locale]);

    const error = form.errors.email ?? form.errors.password;

    return (
        <main id="konten" className="mx-auto flex min-h-dvh w-full max-w-sheet-sm flex-col gap-6 bg-canvas px-m-gutter py-8">
            <Head title={documentTitle(t('pages.pg_001.title'))} />
            <div className="flex justify-end">
                <LanguageSwitch />
            </div>
            <h1 className="text-m-title font-semibold text-text-primary md:text-title">{t('pages.pg_001.heading')}</h1>
            {notice !== null && (
                <p role="status" className="text-body text-text-primary">
                    {notice}
                </p>
            )}
            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    // The language before sign-in travels as `bahasa` to the next sign-in page (NV-07).
                    form.post(`/masuk${languageQuery()}`, {
                        onFinish: () => {
                            form.reset('password');
                        },
                    });
                }}
            >
                <Field>
                    <FieldLabel htmlFor={`${id}-email`}>{t('pages.pg_001.email')}</FieldLabel>
                    <Input
                        id={`${id}-email`}
                        type="email"
                        name="email"
                        autoComplete="username"
                        required
                        value={form.data.email}
                        aria-invalid={error !== undefined ? true : undefined}
                        aria-describedby={`${id}-error`}
                        onChange={(event) => {
                            form.setData('email', event.target.value);
                        }}
                    />
                </Field>
                <Field>
                    <FieldLabel htmlFor={`${id}-password`}>{t('pages.pg_001.password')}</FieldLabel>
                    <PasswordInput
                        id={`${id}-password`}
                        name="password"
                        required
                        value={form.data.password}
                        aria-invalid={error !== undefined ? true : undefined}
                        aria-describedby={`${id}-error`}
                        onChange={(event) => {
                            form.setData('password', event.target.value);
                        }}
                    />
                    <FieldError id={`${id}-error`}>{error}</FieldError>
                </Field>
                <Button type="submit" variant="primary" size="lg" pending={form.processing} pendingLabel={t('pages.pg_001.submit')}>
                    {t('pages.pg_001.submit')}
                </Button>
            </form>
            <div className="flex flex-col items-start gap-1">
                <Button
                    variant="ghost"
                    size="sm"
                    aria-expanded={forgot}
                    onClick={() => {
                        setForgot(true);
                    }}
                >
                    {t('pages.pg_001.forgot')}
                </Button>
                {forgot && <p className="text-body text-text-secondary">{t('pages.pg_001.forgot_answer')}</p>}
            </div>
        </main>
    );
}
