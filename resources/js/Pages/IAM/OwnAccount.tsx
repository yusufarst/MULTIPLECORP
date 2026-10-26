// PG-065 *Akun Saya* (ROUTE-065; NAV-065; INFORMATION_ARCHITECTURE §14.2; AUTH_JOURNEYS §2.9; TASK-023): the account's
// own page, in sections — its profile, role, capabilities and companies read-only with its display language; the
// password; two-step verification; the recovery codes; Google; its own activity. Every sensitive step goes behind the
// step-up the shell answers (AU-15; IP-10); the QR code, the key and the recovery codes arrive as flash data and are
// shown once, never kept in history (AU-12's rule; AU-16; AU-19; H7-12).
import { router, useForm, usePage } from '@inertiajs/react';
import { type ReactNode, type SyntheticEvent, useEffect } from 'react';

import { FormField, FormSection } from '@/components/form';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { QrCode } from '@/components/qr-code';
import { PasswordInput } from '@/components/session-dialogs';
import { roleLabel } from '@/components/shell/menus';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateTimeText } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { dateLong, dateTime } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { type AccountHints } from '@/lib/navigation';

type Props = {
    account: { name: string; email: string; locale: 'id' | 'en' | null };
    totp: { active: boolean; since: string | null; high_risk: boolean; established: boolean };
    google: { enabled: boolean; email: string | null; since: string | null; pending: boolean; answer: 'ready' | 'failed' | null };
    last_login: string | null;
    events: { kind: string; at: string; by: string | null }[];
};

type Flash = { answer?: { kind: 'COMMITTED' | 'REJECTED'; text: string }; enrolment?: { qr?: unknown; key?: unknown; replacing?: unknown }; recovery_codes?: unknown };

function Facts({ rows }: { rows: [ReactNode, ReactNode][] }) {
    return (
        <dl className="grid min-w-0 grid-cols-1 gap-x-6 gap-y-2 md:grid-cols-3">
            {rows.map(([label, value], index) => (
                <div key={index} className="contents">
                    <dt className="text-body text-text-secondary">{label}</dt>
                    <dd className="min-w-0 text-body break-words text-text-primary md:col-span-2">{value}</dd>
                </div>
            ))}
        </dl>
    );
}

/** A code-taking step's own small form: the verification code and its button (AU-15; AU-19). */
function CodeStep({ label, submit, method, url, extra = {}, tone = 'outline' }: { label: string; submit: string; method: 'post' | 'delete'; url: string; extra?: Record<string, string>; tone?: 'outline' | 'danger' }) {
    const form = useForm({ code: '', ...extra });
    const send = (event: SyntheticEvent) => {
        event.preventDefault();
        form[method](url, { preserveScroll: true, onFinish: () => { form.reset('code'); } });
    };

    return (
        <form className="flex min-w-0 flex-col gap-3 md:flex-row md:items-end" onSubmit={send}>
            <FormField label={label} error={form.errors.code} className="md:max-w-56">
                {(control) => (
                    <Input {...control} autoComplete="one-time-code" inputMode="numeric" maxLength={6} value={form.data.code} onChange={(event) => { form.setData('code', event.target.value); }} />
                )}
            </FormField>
            <Button type="submit" variant={tone} size="md" pending={form.processing} pendingLabel={submit}>
                {submit}
            </Button>
        </form>
    );
}

export default function OwnAccount({ account, totp, google, last_login: lastLogin, events }: Props) {
    const { t } = useTranslation();
    const page = usePage<{ auth: AccountHints; errors: Record<string, string | undefined> }>();
    const flash = page.flash as Flash;
    const hints = page.props.auth;
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const replacement = useForm({ current_code: '' });
    const qr = Array.isArray(flash.enrolment?.qr) ? flash.enrolment.qr.filter((row): row is string => typeof row === 'string') : null;
    const key = typeof flash.enrolment?.key === 'string' ? flash.enrolment.key : null;
    const codes = Array.isArray(flash.recovery_codes) ? flash.recovery_codes.filter((code): code is string => typeof code === 'string') : [];

    // The codes and the key never stay in the browser's history once shown (H7-12).
    useEffect(() => {
        if (codes.length > 0 || key !== null) {
            router.clearHistory();
        }
    }, [codes.length, key]);

    return (
        <AppShell title={t('pages.pg_065.title')}>
            <div className="flex min-w-0 flex-col gap-8">
                <PageHeader title={t('pages.pg_065.title')} />
                {flash.answer !== undefined && <OutcomeMessage answer={flash.answer.kind === 'COMMITTED' ? { kind: 'COMMITTED', text: flash.answer.text } : { kind: 'REJECTED', text: flash.answer.text }} />}
                {google.answer === 'failed' && <OutcomeMessage answer={{ kind: 'REJECTED', text: t('account.google.failed') }} />}

                <FormSection title={t('account.sections.profile')}>
                    <Facts
                        rows={[
                            [t('account.profile.name'), account.name],
                            [t('account.profile.email'), account.email],
                            [t('account.profile.role'), t(roleLabel(hints))],
                            [t('account.profile.capabilities'), hints.capabilities.length === 0 ? t('account.profile.none') : <span className="flex flex-wrap gap-2">{hints.capabilities.map((capability) => <Code key={capability}>{capability}</Code>)}</span>],
                            [t('account.profile.companies'), hints.companies.length === 0 ? t('account.profile.none') : hints.companies.map((company) => company.name).join(', ')],
                        ]}
                    />
                    <FormField label={t('account.locale.label')} className="md:max-w-80">
                        {(control) => (
                            <NativeSelect
                                {...control}
                                value={account.locale ?? 'default'}
                                onChange={(event) => {
                                    router.put('/akun/bahasa', { locale: event.target.value }, { preserveScroll: true });
                                }}
                            >
                                <option value="default">{t('account.locale.default')}</option>
                                <option value="id">{t('account.locale.id')}</option>
                                <option value="en">{t('account.locale.en')}</option>
                            </NativeSelect>
                        )}
                    </FormField>
                </FormSection>

                <FormSection title={t('account.sections.password')}>
                    <form
                        className="flex min-w-0 flex-col gap-4 md:max-w-96"
                        onSubmit={(event) => {
                            event.preventDefault();
                            password.put('/akun/kata-sandi', { preserveScroll: true, onFinish: () => { password.reset(); } });
                        }}
                    >
                        <FormField label={t('account.password.current')} required error={password.errors.current_password}>
                            {(control) => <PasswordInput {...control} autoComplete="current-password" value={password.data.current_password} onChange={(event) => { password.setData('current_password', event.target.value); }} />}
                        </FormField>
                        <FormField label={t('account.password.new')} required error={password.errors.password}>
                            {(control) => <PasswordInput {...control} autoComplete="new-password" value={password.data.password} onChange={(event) => { password.setData('password', event.target.value); }} />}
                        </FormField>
                        <FormField label={t('account.password.repeat')} required>
                            {(control) => <PasswordInput {...control} autoComplete="new-password" value={password.data.password_confirmation} onChange={(event) => { password.setData('password_confirmation', event.target.value); }} />}
                        </FormField>
                        <div>
                            <Button type="submit" variant="primary" size="lg" pending={password.processing} pendingLabel={t('account.password.submit')}>
                                {t('account.password.submit')}
                            </Button>
                        </div>
                    </form>
                </FormSection>

                <FormSection title={t('account.sections.totp')}>
                    <p className="text-body text-text-primary">
                        {totp.active && totp.since !== null ? t('account.totp.on', { tanggal: dateLong(totp.since) }) : t('account.totp.off')}
                    </p>
                    {totp.high_risk && <p className="text-body text-text-secondary">{t('account.totp.required')}</p>}
                    {qr !== null && key !== null ? (
                        <div className="flex min-w-0 flex-col gap-4">
                            <p className="text-body text-text-secondary">{t('account.totp.scan')}</p>
                            <QrCode rows={qr} label={t('account.totp.scan')} />
                            <div className="flex flex-col gap-1">
                                <span className="text-body text-text-secondary">{t('account.totp.key')}</span>
                                <code className="font-mono text-body break-all text-text-primary">{key}</code>
                            </div>
                            <CodeStep label={totp.active ? t('account.totp.new_code') : t('account.totp.code')} submit={t('account.totp.confirm')} method="post" url="/akun/verifikasi-dua-langkah" />
                        </div>
                    ) : totp.active ? (
                        <form
                            className="flex min-w-0 flex-col gap-3 md:flex-row md:items-end"
                            onSubmit={(event) => {
                                event.preventDefault();
                                replacement.post('/akun/verifikasi-dua-langkah', { preserveScroll: true, onFinish: () => { replacement.reset(); } });
                            }}
                        >
                            <FormField label={t('account.totp.current_code')} error={replacement.errors.current_code} className="md:max-w-56">
                                {(control) => <Input {...control} autoComplete="one-time-code" inputMode="numeric" maxLength={6} value={replacement.data.current_code} onChange={(event) => { replacement.setData('current_code', event.target.value); }} />}
                            </FormField>
                            <Button type="submit" variant="outline" size="md" pending={replacement.processing} pendingLabel={t('account.totp.replace')}>
                                {t('account.totp.replace')}
                            </Button>
                        </form>
                    ) : (
                        <div>
                            <Button variant="primary" size="lg" onClick={() => { router.post('/akun/verifikasi-dua-langkah', {}, { preserveScroll: true }); }}>
                                {t('account.totp.enrol')}
                            </Button>
                        </div>
                    )}
                    {totp.active && !totp.high_risk && qr === null && (
                        <div className="flex min-w-0 flex-col gap-2">
                            <p className="text-body text-text-secondary">{t('account.totp.disable_line')}</p>
                            <CodeStep label={t('account.totp.code')} submit={t('account.totp.disable')} method="delete" url="/akun/verifikasi-dua-langkah" tone="danger" />
                        </div>
                    )}
                </FormSection>

                {totp.active && (
                    <FormSection title={t('account.sections.codes')}>
                        {codes.length > 0 && (
                            <div className="flex min-w-0 flex-col gap-2">
                                <p role="status" className="text-body text-text-primary">
                                    {t('account.codes.line')}
                                </p>
                                <ul aria-label={t('account.sections.codes')} className="grid grid-cols-1 gap-2 font-mono text-body text-text-primary sm:grid-cols-2 md:max-w-96">
                                    {codes.map((code) => (
                                        <li key={code} className="rounded-md border border-border px-3 py-2">
                                            {code}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <CodeStep label={t('account.totp.code')} submit={t('account.codes.view')} method="post" url="/akun/kode-pemulihan" />
                        <CodeStep label={t('account.totp.code')} submit={t('account.codes.regenerate')} method="post" url="/akun/kode-pemulihan/baru" />
                    </FormSection>
                )}

                <FormSection title={t('account.sections.google')}>
                    <p className="text-body text-text-primary">
                        {google.email !== null && google.since !== null ? t('account.google.linked_line', { email: google.email, tanggal: dateLong(google.since) }) : t('account.google.none')}
                    </p>
                    {google.email !== null ? (
                        <div>
                            <Button variant="outline" size="md" onClick={() => { router.delete('/akun/google', { preserveScroll: true }); }}>
                                {t('account.google.unlink')}
                            </Button>
                        </div>
                    ) : !google.enabled ? (
                        <p className="text-body text-text-secondary">{t('account.google.off')}</p>
                    ) : !totp.active || !totp.established ? (
                        <p className="text-body text-text-secondary">{t('account.google.needs_totp')}</p>
                    ) : google.pending ? (
                        <div className="flex min-w-0 flex-col gap-2">
                            <p className="text-body text-text-secondary">{t('account.google.pending')}</p>
                            <CodeStep label={t('account.totp.code')} submit={t('account.google.confirm')} method="post" url="/akun/google" />
                        </div>
                    ) : (
                        <div>
                            <Button variant="outline" size="md" render={<a href="/masuk/google?tujuan=tautkan" />}>
                                {t('account.google.link')}
                            </Button>
                        </div>
                    )}
                </FormSection>

                <FormSection title={t('account.sections.activity')}>
                    {lastLogin !== null && (
                        <p className="text-body text-text-primary">
                            {t('account.activity.last_login', { waktu: dateTime(lastLogin) })}
                        </p>
                    )}
                    {events.length === 0 ? (
                        <p className="text-body text-text-secondary">{t('account.activity.empty')}</p>
                    ) : (
                        <ul className="flex min-w-0 flex-col gap-2">
                            {events.map((event, index) => (
                                <li key={index} className="flex min-w-0 flex-wrap items-baseline gap-x-3 text-body">
                                    <span className="text-text-primary">{t(`account.events.${event.kind}` as TranslationKey)}</span>
                                    <DateTimeText value={event.at} className="text-text-secondary" />
                                    {event.by !== null && <span className="text-text-secondary">{t('account.activity.by', { nama: event.by })}</span>}
                                </li>
                            ))}
                        </ul>
                    )}
                </FormSection>
            </div>
        </AppShell>
    );
}
