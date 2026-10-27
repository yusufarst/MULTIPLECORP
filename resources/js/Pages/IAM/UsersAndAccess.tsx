// PG-060 *Pengguna & Akses* (ROUTE-060, ROUTE-121–ROUTE-128, ROUTE-130; NAV-060; SCR-57; IA §8.6, §14.5; J-ACC-02;
// TASK-024), the Owner's alone: the directory, then one account's detail by its address key — its profile and state,
// its access with "Pratinjau Akses" before "Simpan Akses" (PT-30; UXS-27), its sessions as device summaries, its
// credential links and its security — TOTP status, the reset and the Google unlink with their reason (H7-17). Each
// command one control, its confirmation listing the change first (IP-08), the step-up when it is sent (IP-10), its
// answer in place (IP-01). A credential link arrives as flash data and is shown once, never kept in history (AU-12).
// The acting Owner's own account offers none of the account screens (OD-02; AUTH_JOURNEYS §2.10).
import { router, usePage } from '@inertiajs/react';
import { ArrowLeft, Copy, Plus } from 'lucide-react';
import { type ReactNode, useEffect, useMemo, useState } from 'react';

import { ActionBar } from '@/components/action-bar';
import { CompanyTag } from '@/components/company';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { DataTable, ListContinuation } from '@/components/data-list';
import { FormField, FormSection } from '@/components/form';
import { type Answer, OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { CapabilityPreview, type CapabilityRow } from '@/components/signals';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { DateTimeText } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Role = 'OWNER' | 'ADMIN_OPERASIONAL';
type Section = 'profil' | 'akses' | 'sesi' | 'tautan' | 'keamanan';
type Company = { id: string; code: string; name: string; active: boolean };
type Directory = { id: string; name: string; email: string; role: Role | null; active: boolean; totp: boolean; google: boolean; last_activity: string | null };
type Session = { handle: string; device: string | null; started_at: string | null; last_activity: string | null; current: boolean };
type Link = { purpose: 'ONBOARDING' | 'RESET' | 'OWNER_RECOVERY'; issuer: string | null; console: boolean; issued_at: string | null; expires_at: string | null; state: 'open' | 'used' | 'revoked' | 'expired'; at: string | null };
type GoogleRow = { email: string | null; linked_at: string | null; unlinked_at: string | null; reason: string | null; by: string | null };
type Account = {
    id: string;
    name: string;
    email: string;
    lock_version: number;
    role: Role | null;
    active: boolean;
    deactivated_at: string | null;
    self: boolean;
    totp: { active: boolean; since: string | null };
    capabilities: string[];
    companies: Company[];
    sessions: Session[];
    links: Link[];
    google: GoogleRow[];
};
type Catalogue = { area: string; capabilities: { code: string; special: boolean; prerequisites: string[]; company: boolean }[] }[];
type Mark = 'kept' | 'added' | 'removed' | 'inactive' | null;
type Preview = {
    owner: boolean;
    companies: string[];
    rows: { code: string; area: string; marks: Record<string, Mark> }[];
    summaries: Record<string, number>;
    changes: { capabilities_added: string[]; capabilities_removed: string[]; companies_added: string[]; companies_removed: string[]; disabled: string[] } | null;
    missing?: string[];
};
type Props = { accounts: { rows: Directory[]; more: boolean }; account: Account | null; section: Section; catalogue: Catalogue; companies: Company[]; preview?: Preview | null };
type Flash = { credential_link?: { url?: unknown; purpose?: unknown; expires_at?: unknown } };

const SECTIONS: Section[] = ['profil', 'akses', 'sesi', 'tautan', 'keamanan'];

function capabilityName(code: string): TranslationKey {
    return `capability.name.${code}` as TranslationKey;
}

function roleName(role: Role | null): TranslationKey {
    return role === 'OWNER' ? 'glossary.gl_031' : 'glossary.gl_032';
}

function visit(account: string | null, section?: Section) {
    router.get('/pengaturan/pengguna', account === null ? {} : { akun: account, bagian: section ?? 'profil' }, { preserveScroll: true });
}

/** A command form's refusal or failure in place, with "Coba Lagi" while the outcome is unknown (IP-01; IP-04); its
    success is the page's (PageAnswer), since the section it came from is drawn again with the change. */
function CommandAnswer({ command }: { command: { answer: Answer | null; unknown: boolean; retry: () => void } }) {
    const { t } = useTranslation();

    if (command.answer === null || command.answer.kind === 'COMMITTED') return null;

    return (
        <OutcomeMessage
            answer={command.answer}
            action={
                command.unknown ? (
                    <Button variant="outline" size="md" onClick={command.retry}>
                        {t('actions.try_again')}
                    </Button>
                ) : undefined
            }
        />
    );
}

/** A command's success on the page the answer opened, once — its flash data (IP-01). */
function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

/** The link the command issued, shown once with its expiry for out-of-band delivery (AU-12), copied but never kept. */
function LinkShownOnce() {
    const { t } = useTranslation();
    const flash = usePage().flash as Flash;
    const url = typeof flash.credential_link?.url === 'string' ? flash.credential_link.url : null;
    const expires = typeof flash.credential_link?.expires_at === 'string' ? flash.credential_link.expires_at : null;
    const [copied, setCopied] = useState(false);

    // The link never stays in the browser's history once shown (AU-12; H7-12).
    useEffect(() => {
        if (url !== null) {
            router.clearHistory();
        }
    }, [url]);

    if (url === null) return null;

    return (
        <section aria-label={t('users.link.shown_title')} role="status" className="flex min-w-0 flex-col gap-3 rounded-md border border-border bg-surface-sunken p-4">
            <p className="text-body font-semibold text-text-primary">{t('users.link.shown_title')}</p>
            <p className="text-body text-text-secondary">{t('users.link.shown_line')}</p>
            <div className="flex min-w-0 flex-col gap-2 md:flex-row">
                <Input readOnly aria-label={t('users.link.shown_title')} value={url} className="min-w-0 font-mono" onFocus={(event) => { event.target.select(); }} />
                <Button
                    variant="outline"
                    size="md"
                    onClick={() => {
                        void navigator.clipboard.writeText(url).then(() => { setCopied(true); });
                    }}
                >
                    <Icon icon={Copy} size="sm" />
                    {copied ? t('users.link.copied') : t('users.link.copy')}
                </Button>
            </div>
            {expires !== null && (
                <p className="text-body text-text-secondary">
                    {t('users.link.expires')} <DateTimeText value={expires} />
                </p>
            )}
        </section>
    );
}

function CreateAccount({ onDone }: { onDone: () => void }) {
    const { t } = useTranslation();
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [confirming, setConfirming] = useState(false);
    const command = useCommand<{ name: string; email: string }>(() => '/pengaturan/pengguna', 'post');

    return (
        <FormSection title={t('users.create.title')}>
            <form
                className="flex min-w-0 flex-col gap-4 md:max-w-120"
                onSubmit={(event) => {
                    event.preventDefault();
                    setConfirming(true);
                }}
            >
                <FormField label={t('users.profile.name')} required error={command.errors.name}>
                    {(control) => <Input {...control} autoComplete="off" value={name} disabled={command.frozen} onChange={(event) => { setName(event.target.value); }} />}
                </FormField>
                <FormField label={t('users.profile.email')} required error={command.errors.email}>
                    {(control) => <Input {...control} type="email" autoComplete="off" value={email} disabled={command.frozen} onChange={(event) => { setEmail(event.target.value); }} />}
                </FormField>
                <CommandAnswer command={command} />
                <div className="flex gap-2">
                    <Button type="submit" variant="primary" size="lg" pending={command.pending} pendingLabel={t('users.create.submit')}>
                        {t('users.create.submit')}
                    </Button>
                    <Button type="button" variant="outline" size="lg" onClick={onDone}>
                        {t('actions.cancel')}
                    </Button>
                </div>
            </form>
            <ConfirmCommand
                open={confirming}
                onOpenChange={setConfirming}
                title={t('users.create.confirm_title')}
                consequence={t('users.create.confirm_line')}
                items={
                    <p className="text-body text-text-primary">
                        {name} — {email}
                    </p>
                }
                confirmLabel={t('users.create.submit')}
                pending={command.pending}
                answer={command.answer}
                onConfirm={() => {
                    command.submit({ name, email }, () => { setConfirming(false); });
                }}
            />
        </FormSection>
    );
}

function Directory({ accounts }: { accounts: { rows: Directory[]; more: boolean } }) {
    const { t } = useTranslation();
    const [creating, setCreating] = useState(false);
    const [loading, setLoading] = useState(false);
    // PT-08: "Muat Lagi" asks for as many rows again on a partial reload, the address unchanged.
    const loadMore = () => {
        setLoading(true);
        router.reload({ only: ['accounts'], data: { batas: accounts.rows.length + 25 }, preserveUrl: true, onFinish: () => { setLoading(false); } });
    };

    return (
        <div className="flex min-w-0 flex-col gap-6">
            <PageHeader
                title={t('pages.pg_060.title')}
                primary={
                    creating ? undefined : (
                        <Button variant="primary" size="lg" onClick={() => { setCreating(true); }}>
                            <Icon icon={Plus} size="sm" />
                            {t('users.create.title')}
                        </Button>
                    )
                }
            />
            <PageAnswer />
            {creating && <CreateAccount onDone={() => { setCreating(false); }} />}
            {/* On the phone the page header's primary action moves to the bottom action bar (PT-03). */}
            {!creating && (
                <ActionBar label={t('pages.pg_060.title')} className="md:hidden">
                    <Button variant="primary" size="lg" onClick={() => { setCreating(true); }}>
                        <Icon icon={Plus} size="sm" />
                        {t('users.create.title')}
                    </Button>
                </ActionBar>
            )}
            <DataTable
                caption={t('pages.pg_060.title')}
                rows={accounts.rows}
                rowKey={(row) => row.id}
                rowHref={(row) => `/pengaturan/pengguna?akun=${row.id}&bagian=profil`}
                columns={[
                    { key: 'name', header: t('users.profile.name'), cell: (row) => row.name },
                    { key: 'email', header: t('users.profile.email'), cell: (row) => <span className="break-all">{row.email}</span> },
                    { key: 'role', header: t('users.profile.role'), cell: (row) => t(roleName(row.role)) },
                    { key: 'state', header: t('users.profile.state'), cell: (row) => <Badge tone={row.active ? 'success' : 'neutral'}>{t(row.active ? 'users.state.active' : 'users.state.inactive')}</Badge> },
                    { key: 'totp', header: t('users.security.totp'), cell: (row) => t(row.totp ? 'users.security.totp_on' : 'users.security.totp_off'), priority: 'low' },
                    { key: 'activity', header: t('users.sessions.last_activity'), cell: (row) => (row.last_activity === null ? '—' : <DateTimeText value={row.last_activity} />), priority: 'low' },
                ]}
            />
            <ListContinuation state={accounts.more ? 'more' : 'end'} loading={loading} onLoadMore={loadMore} onReload={loadMore} />
        </div>
    );
}

function Profile({ account }: { account: Account }) {
    const { t } = useTranslation();
    const [name, setName] = useState(account.name);
    const [email, setEmail] = useState(account.email);
    const [confirm, setConfirm] = useState<'edit' | 'status' | null>(null);
    const edit = useCommand<{ name: string; email: string; lock_version: number }>(() => `/pengaturan/pengguna/${account.id}`, 'put');
    const status = useCommand<{ active: boolean }>(() => `/pengaturan/pengguna/${account.id}/status`, 'post');

    return (
        <div className="flex min-w-0 flex-col gap-6">
            <dl className="grid min-w-0 grid-cols-1 gap-x-6 gap-y-2 md:grid-cols-3">
                <dt className="text-body text-text-secondary">{t('users.profile.role')}</dt>
                <dd className="text-body text-text-primary md:col-span-2">{t(roleName(account.role))}</dd>
                <dt className="text-body text-text-secondary">{t('users.profile.state')}</dt>
                <dd className="text-body text-text-primary md:col-span-2">{t(account.active ? 'users.state.active' : 'users.state.inactive')}</dd>
            </dl>
            <FormSection title={t('users.profile.edit')}>
                <form
                    className="flex min-w-0 flex-col gap-4 md:max-w-120"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setConfirm('edit');
                    }}
                >
                    <FormField label={t('users.profile.name')} required error={edit.errors.name}>
                        {(control) => <Input {...control} value={name} disabled={edit.frozen} onChange={(event) => { setName(event.target.value); }} />}
                    </FormField>
                    <FormField label={t('users.profile.email')} required error={edit.errors.email}>
                        {(control) => <Input {...control} type="email" value={email} disabled={edit.frozen} onChange={(event) => { setEmail(event.target.value); }} />}
                    </FormField>
                    <CommandAnswer command={edit} />
                    <div>
                        <Button type="submit" variant="primary" size="lg" pending={edit.pending} pendingLabel={t('users.profile.save')}>
                            {t('users.profile.save')}
                        </Button>
                    </div>
                </form>
            </FormSection>
            <FormSection title={t('users.profile.state')}>
                <p className="text-body text-text-secondary">{t(account.active ? 'users.state.deactivate_line' : 'users.state.activate_line')}</p>
                <CommandAnswer command={status} />
                <div>
                    <Button variant={account.active ? 'destructive' : 'primary'} size="lg" onClick={() => { setConfirm('status'); }}>
                        {t(account.active ? 'glossary.gl_047' : 'users.state.activate')}
                    </Button>
                </div>
            </FormSection>
            <ConfirmCommand
                open={confirm === 'edit'}
                onOpenChange={(open) => { setConfirm(open ? 'edit' : null); }}
                title={t('users.profile.confirm_title')}
                consequence={t('users.profile.confirm_line')}
                items={<p className="text-body text-text-primary">{name} — {email}</p>}
                confirmLabel={t('users.profile.save')}
                pending={edit.pending}
                answer={edit.answer}
                onConfirm={() => {
                    edit.submit({ name, email, lock_version: account.lock_version }, () => { setConfirm(null); });
                }}
            />
            <ConfirmCommand
                open={confirm === 'status'}
                onOpenChange={(open) => { setConfirm(open ? 'status' : null); }}
                title={t(account.active ? 'users.state.deactivate_title' : 'users.state.activate_title')}
                consequence={t(account.active ? 'users.state.deactivate_consequence' : 'users.state.activate_consequence')}
                items={<p className="text-body text-text-primary">{account.name} — {account.email}</p>}
                confirmLabel={t(account.active ? 'glossary.gl_047' : 'users.state.activate')}
                destructive={account.active}
                pending={status.pending}
                answer={status.answer}
                onConfirm={() => {
                    status.submit({ active: !account.active }, () => { setConfirm(null); });
                }}
            />
        </div>
    );
}

function Access({ account, catalogue, companies, preview }: { account: Account; catalogue: Catalogue; companies: Company[]; preview: Preview | null }) {
    const { t } = useTranslation();
    const [role, setRole] = useState<Role>(account.role ?? 'ADMIN_OPERASIONAL');
    const [chosen, setChosen] = useState<string[]>(account.companies.map((company) => company.id));
    const [capabilities, setCapabilities] = useState<string[]>(account.capabilities);
    const [reason, setReason] = useState('');
    const [previewing, setPreviewing] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const command = useCommand<{ role: Role; companies: string[]; capabilities: string[]; lock_version: number; reason: string }>(() => `/pengaturan/pengguna/${account.id}/akses`, 'put');
    const codeOf = useMemo(() => Object.fromEntries(companies.map((company) => [company.id, company.code])), [companies]);
    const special = useMemo(() => new Set(catalogue.flatMap((area) => area.capabilities.filter((capability) => capability.special).map((capability) => capability.code))), [catalogue]);
    const touchesSpecial = [...capabilities.filter((code) => !account.capabilities.includes(code)), ...account.capabilities.filter((code) => !capabilities.includes(code))].some((code) => special.has(code));
    const toggle = (list: string[], value: string, on: boolean) => (on ? [...list, value] : list.filter((entry) => entry !== value));

    if (account.self) {
        return <p className="text-body text-text-secondary">{t('users.access.self')}</p>;
    }

    const askPreview = () => {
        setPreviewing(true);
        router.reload({
            only: ['preview'],
            data: { akun: account.id, bagian: 'akses', peran: role, perusahaan: chosen, kewenangan: capabilities },
            preserveUrl: true,
            onFinish: () => { setPreviewing(false); },
        });
    };

    const rows: CapabilityRow[] = (preview?.rows ?? []).map((row) => ({
        key: row.code,
        area: t(`capability.area.${row.area}` as TranslationKey),
        label: t(capabilityName(row.code)),
        marks: Object.fromEntries(Object.entries(row.marks).map(([company, mark]) => [codeOf[company] ?? company, mark])),
    }));
    const summaries = Object.fromEntries(Object.entries(preview?.summaries ?? {}).map(([company, count]) => [codeOf[company] ?? company, t('users.access.summary', { jumlah: count })]));

    return (
        <div className="flex min-w-0 flex-col gap-6">
            <FormSection title={t('users.profile.role')}>
                <FormField label={t('users.profile.role')} className="md:max-w-80">
                    {(control) => (
                        <NativeSelect {...control} value={role} disabled={command.frozen} onChange={(event) => { setRole(event.target.value as Role); }}>
                            <option value="ADMIN_OPERASIONAL">{t('glossary.gl_032')}</option>
                            <option value="OWNER">{t('glossary.gl_031')}</option>
                        </NativeSelect>
                    )}
                </FormField>
            </FormSection>
            {role !== 'OWNER' && (
                <>
                    <FormSection title={t('glossary.gl_034')}>
                        {companies.length === 0 ? (
                            <RegionEmpty text={t('users.access.no_company')} />
                        ) : (
                            <ul className="grid min-w-0 grid-cols-1 gap-2 md:grid-cols-2">
                                {companies.map((company) => (
                                    <li key={company.id} className="flex min-w-0 items-center gap-3">
                                        <Checkbox
                                            id={`perusahaan-${company.id}`}
                                            checked={chosen.includes(company.id)}
                                            disabled={command.frozen}
                                            onCheckedChange={(on) => { setChosen(toggle(chosen, company.id, on)); }}
                                        />
                                        <label htmlFor={`perusahaan-${company.id}`} className="flex min-w-0 items-center gap-2 text-body text-text-primary">
                                            <CompanyTag code={company.code} />
                                            <span className="min-w-0 break-words">{company.name}</span>
                                            {!company.active && <span className="text-text-tertiary">{t('users.access.company_inactive')}</span>}
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </FormSection>
                    <FormSection title={t('glossary.gl_035')}>
                        {capabilities.length === 0 && <p className="text-body text-text-secondary">{t('users.access.none')}</p>}
                        {catalogue.map((area) => (
                            <fieldset key={area.area} className="flex min-w-0 flex-col gap-2">
                                <legend className="pb-2 text-body font-semibold text-text-primary">{t(`capability.area.${area.area}` as TranslationKey)}</legend>
                                <ul className="grid min-w-0 grid-cols-1 gap-2 md:grid-cols-2">
                                    {area.capabilities.map((capability) => (
                                        <li key={capability.code} className="flex min-w-0 items-start gap-3">
                                            <Checkbox
                                                id={`kewenangan-${capability.code}`}
                                                checked={capabilities.includes(capability.code)}
                                                disabled={command.frozen}
                                                onCheckedChange={(on) => { setCapabilities(toggle(capabilities, capability.code, on)); }}
                                            />
                                            <label htmlFor={`kewenangan-${capability.code}`} className="flex min-w-0 flex-col text-body text-text-primary">
                                                <span>{t(capabilityName(capability.code))}</span>
                                                {capability.prerequisites.length > 0 && (
                                                    <span className="text-caption text-text-tertiary">
                                                        {t('users.access.needs', { kewenangan: capability.prerequisites.map((code) => t(capabilityName(code))).join(', ') })}
                                                    </span>
                                                )}
                                            </label>
                                        </li>
                                    ))}
                                </ul>
                            </fieldset>
                        ))}
                    </FormSection>
                </>
            )}
            {touchesSpecial && (
                <FormField label={t('messages.msg_40')} required error={command.errors.reason} className="md:max-w-120">
                    {(control) => <Textarea {...control} value={reason} disabled={command.frozen} onChange={(event) => { setReason(event.target.value); }} />}
                </FormField>
            )}
            <div className="flex flex-wrap gap-2">
                <Button variant="outline" size="lg" pending={previewing} pendingLabel={t('glossary.gl_191')} onClick={askPreview}>
                    {t('glossary.gl_191')}
                </Button>
                <Button variant="primary" size="lg" disabled={preview === null} onClick={() => { setConfirming(true); }}>
                    {t('users.access.save')}
                </Button>
            </div>
            {preview?.owner === true && <p className="text-body text-text-secondary">{t('users.access.owner_line')}</p>}
            {preview !== null && !preview.owner && (
                preview.rows.length === 0 ? (
                    <RegionEmpty text={t('users.access.none')} />
                ) : (
                    <CapabilityPreview companies={preview.companies.map((company) => codeOf[company] ?? company)} rows={rows} summaries={summaries} />
                )
            )}
            <CommandAnswer command={command} />
            <ConfirmCommand
                open={confirming}
                onOpenChange={setConfirming}
                title={t('users.access.confirm_title')}
                consequence={t('users.access.confirm_line')}
                items={<AccessChanges preview={preview} role={role} account={account} codeOf={codeOf} />}
                confirmLabel={t('users.access.save')}
                pending={command.pending}
                answer={command.answer}
                onConfirm={() => {
                    command.submit({ role, companies: role === 'OWNER' ? [] : chosen, capabilities: role === 'OWNER' ? [] : capabilities, lock_version: account.lock_version, reason }, () => { setConfirming(false); });
                }}
            />
        </div>
    );
}

/** The confirmation's list: each change and the companies it applies to (PT-30; H7-06). */
function AccessChanges({ preview, role, account, codeOf }: { preview: Preview | null; role: Role; account: Account; codeOf: Record<string, string> }) {
    const { t } = useTranslation();
    const changes = preview?.changes;
    const companies = (ids: string[]) => ids.map((id) => codeOf[id] ?? id).join(', ');
    const lines: ReactNode[] = [];

    if (role !== account.role) lines.push(t('users.access.change_role', { peran: t(roleName(role)) }));
    if (changes) {
        if (changes.companies_added.length > 0) lines.push(t('users.access.change_companies_added', { perusahaan: companies(changes.companies_added) }));
        if (changes.companies_removed.length > 0) lines.push(t('users.access.change_companies_removed', { perusahaan: companies(changes.companies_removed) }));
        for (const code of changes.capabilities_added) lines.push(t('users.access.change_added', { kewenangan: t(capabilityName(code)), perusahaan: companies(preview.companies) }));
        for (const code of changes.capabilities_removed) lines.push(t('users.access.change_removed', { kewenangan: t(capabilityName(code)) }));
        for (const code of changes.disabled) lines.push(t('users.access.change_disabled', { kewenangan: t(capabilityName(code)) }));
    }

    return lines.length === 0 ? <p className="text-body text-text-secondary">{t('users.access.no_change')}</p> : (
        <ul className="flex flex-col gap-1 text-body text-text-primary">
            {lines.map((line, index) => (
                <li key={index}>{line}</li>
            ))}
        </ul>
    );
}

function Sessions({ account }: { account: Account }) {
    const { t } = useTranslation();
    const [ending, setEnding] = useState<Session | null>(null);
    const command = useCommand<Record<string, never>>(() => `/pengaturan/pengguna/${account.id}/sesi/${ending?.handle ?? ''}/akhiri`, 'post');

    return (
        <div className="flex min-w-0 flex-col gap-4">
            <CommandAnswer command={command} />
            {account.sessions.length === 0 ? (
                <RegionEmpty text={t('users.sessions.none')} />
            ) : (
                <ul className="flex min-w-0 flex-col divide-y divide-border-subtle">
                    {account.sessions.map((session) => (
                        <li key={session.handle} className="flex min-w-0 flex-col gap-2 py-3 md:flex-row md:items-center md:justify-between">
                            <div className="flex min-w-0 flex-col gap-1">
                                <span className="text-body text-text-primary">
                                    {session.device ?? t('users.sessions.unknown_device')}
                                    {session.current && <span className="text-text-secondary"> · {t('users.sessions.current')}</span>}
                                </span>
                                <span className="text-body text-text-secondary">
                                    {session.started_at !== null && (
                                        <>
                                            {t('users.sessions.started')} <DateTimeText value={session.started_at} /> ·{' '}
                                        </>
                                    )}
                                    {session.last_activity !== null && (
                                        <>
                                            {t('users.sessions.last_activity')} <DateTimeText value={session.last_activity} />
                                        </>
                                    )}
                                </span>
                            </div>
                            {!session.current && (
                                <Button variant="outline" size="md" onClick={() => { setEnding(session); }}>
                                    {t('actions.end_session')}
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            <ConfirmCommand
                open={ending !== null}
                onOpenChange={(open) => { if (!open) setEnding(null); }}
                title={t('users.sessions.confirm_title')}
                consequence={t('users.sessions.confirm_line')}
                items={<p className="text-body text-text-primary">{ending?.device ?? t('users.sessions.unknown_device')}</p>}
                confirmLabel={t('actions.end_session')}
                destructive
                pending={command.pending}
                answer={command.answer}
                onConfirm={() => {
                    command.submit({}, () => { setEnding(null); });
                }}
            />
        </div>
    );
}

function Links({ account }: { account: Account }) {
    const { t } = useTranslation();
    const [revoking, setRevoking] = useState<Link | null>(null);
    const issue = useCommand<Record<string, never>>(() => `/pengaturan/pengguna/${account.id}/tautan`, 'post');
    const revoke = useCommand<{ purpose: string }>(() => `/pengaturan/pengguna/${account.id}/tautan/cabut`, 'post');

    return (
        <div className="flex min-w-0 flex-col gap-4">
            <LinkShownOnce />
            {!account.self && (
                <div className="flex flex-col gap-2">
                    <p className="text-body text-text-secondary">{t('users.link.issue_line')}</p>
                    <CommandAnswer command={issue} />
                    <div>
                        <Button variant="primary" size="lg" pending={issue.pending} pendingLabel={t('actions.create_credential_link')} onClick={() => { issue.submit({}); }}>
                            {t('actions.create_credential_link')}
                        </Button>
                    </div>
                </div>
            )}
            <CommandAnswer command={revoke} />
            {account.links.length === 0 ? (
                <RegionEmpty text={t('users.link.none')} />
            ) : (
                <Table aria-label={t('glossary.gl_045')}>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('users.link.purpose')}</TableHead>
                            <TableHead>{t('users.link.issuer')}</TableHead>
                            <TableHead>{t('users.link.issued')}</TableHead>
                            <TableHead>{t('users.link.state')}</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {account.links.map((link, index) => (
                            <TableRow key={index}>
                                <TableCell>{t(`users.link.purpose_${link.purpose.toLowerCase()}` as TranslationKey)}</TableCell>
                                <TableCell>{link.console ? t('users.link.console') : (link.issuer ?? '—')}</TableCell>
                                <TableCell>{link.issued_at === null ? '—' : <DateTimeText value={link.issued_at} />}</TableCell>
                                <TableCell>{t(`users.link.state_${link.state}` as TranslationKey)}</TableCell>
                                <TableCell>
                                    {link.state === 'open' && link.purpose !== 'OWNER_RECOVERY' && (
                                        <Button variant="outline" size="md" onClick={() => { setRevoking(link); }}>
                                            {t('users.link.revoke')}
                                        </Button>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
            <ConfirmCommand
                open={revoking !== null}
                onOpenChange={(open) => { if (!open) setRevoking(null); }}
                title={t('users.link.revoke_title')}
                consequence={t('users.link.revoke_line')}
                confirmLabel={t('users.link.revoke')}
                destructive
                pending={revoke.pending}
                answer={revoke.answer}
                onConfirm={() => {
                    if (revoking !== null) revoke.submit({ purpose: revoking.purpose }, () => { setRevoking(null); });
                }}
            />
        </div>
    );
}

function Security({ account }: { account: Account }) {
    const { t } = useTranslation();
    const [asking, setAsking] = useState<'reset' | 'unlink' | null>(null);
    const [reason, setReason] = useState('');
    const reset = useCommand<{ reason: string }>(() => `/pengaturan/pengguna/${account.id}/reset-verifikasi`, 'post');
    const unlink = useCommand<{ reason: string }>(() => `/pengaturan/pengguna/${account.id}/lepas-google`, 'post');
    const linked = account.google.some((row) => row.unlinked_at === null);
    const command = asking === 'unlink' ? unlink : reset;

    return (
        <div className="flex min-w-0 flex-col gap-6">
            <LinkShownOnce />
            <FormSection title={t('users.security.totp')}>
                <p className="text-body text-text-primary">
                    {account.totp.active && account.totp.since !== null ? (
                        <>
                            {t('users.security.totp_on_since')} <DateTimeText value={account.totp.since} />
                        </>
                    ) : (
                        t('users.security.totp_off')
                    )}
                </p>
                <CommandAnswer command={reset} />
                {!account.self && (
                    <div>
                        <Button variant="destructive" size="lg" onClick={() => { setReason(''); setAsking('reset'); }}>
                            {t(account.totp.active ? 'users.security.reset' : 'users.security.reissue')}
                        </Button>
                    </div>
                )}
            </FormSection>
            <FormSection title={t('users.security.google')}>
                <CommandAnswer command={unlink} />
                {account.google.length === 0 ? (
                    <p className="text-body text-text-secondary">{t('users.security.google_none')}</p>
                ) : (
                    <ul className="flex min-w-0 flex-col gap-2">
                        {account.google.map((row, index) => (
                            <li key={index} className="text-body text-text-primary">
                                {row.email ?? t('users.security.google_no_email')}
                                {row.linked_at !== null && (
                                    <span className="text-text-secondary">
                                        {' · '}
                                        {t('users.security.google_linked')} <DateTimeText value={row.linked_at} />
                                    </span>
                                )}
                                {row.unlinked_at !== null && (
                                    <span className="text-text-secondary">
                                        {' · '}
                                        {t('users.security.google_unlinked')} <DateTimeText value={row.unlinked_at} />
                                        {row.reason !== null && ` (${t(`users.security.unlink_reason_${row.reason.toLowerCase()}` as TranslationKey)}${row.by !== null ? `, ${row.by}` : ''})`}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                {linked && !account.self && (
                    <div>
                        <Button variant="destructive" size="lg" onClick={() => { setReason(''); setAsking('unlink'); }}>
                            {t('users.security.unlink')}
                        </Button>
                    </div>
                )}
            </FormSection>
            <ConfirmCommand
                open={asking !== null}
                onOpenChange={(open) => { if (!open) setAsking(null); }}
                title={t(asking === 'unlink' ? 'users.security.unlink_title' : account.totp.active ? 'users.security.reset_title' : 'users.security.reissue_title')}
                consequence={t(asking === 'unlink' ? 'users.security.unlink_line' : 'users.security.reset_line')}
                items={<p className="text-body text-text-primary">{account.name} — {account.email}</p>}
                reason={{ value: reason, onChange: setReason, error: command.errors.reason ?? null }}
                confirmLabel={t(asking === 'unlink' ? 'users.security.unlink' : account.totp.active ? 'users.security.reset' : 'users.security.reissue')}
                destructive
                pending={command.pending}
                answer={command.answer}
                onConfirm={() => {
                    command.submit({ reason }, () => { setAsking(null); });
                }}
            />
        </div>
    );
}

function Detail({ account, section, catalogue, companies, preview }: { account: Account; section: Section; catalogue: Catalogue; companies: Company[]; preview: Preview | null }) {
    const { t } = useTranslation();

    return (
        <div className="flex min-w-0 flex-col gap-4">
            {/* NAV-060: "Kembali" from an account → PG-060's directory, from the page's own data. */}
            <Button variant="ghost" size="md" className="self-start" onClick={() => { visit(null); }}>
                <Icon icon={ArrowLeft} size="sm" />
                {t('shell.back')}
            </Button>
            <PageHeader title={account.name} facts={<span className="break-all">{account.email}</span>} />
            <PageAnswer />
            <Tabs
                value={section}
                onValueChange={(value) => {
                    visit(account.id, String(value) as Section);
                }}
            >
                <TabsList>
                    {SECTIONS.map((key) => (
                        <TabsTab key={key} value={key}>
                            {t(`users.section.${key}` as TranslationKey)}
                        </TabsTab>
                    ))}
                </TabsList>
                <TabsPanel value="profil">
                    <Profile key={`${account.id}-${String(account.lock_version)}`} account={account} />
                </TabsPanel>
                <TabsPanel value="akses">
                    <Access key={`${account.id}-${String(account.lock_version)}`} account={account} catalogue={catalogue} companies={companies} preview={preview} />
                </TabsPanel>
                <TabsPanel value="sesi">
                    <Sessions account={account} />
                </TabsPanel>
                <TabsPanel value="tautan">
                    <Links account={account} />
                </TabsPanel>
                <TabsPanel value="keamanan">
                    <Security account={account} />
                </TabsPanel>
            </Tabs>
        </div>
    );
}

export default function UsersAndAccess({ accounts, account, section, catalogue, companies, preview = null }: Props) {
    const { t } = useTranslation();

    return (
        <AppShell title={t('pages.pg_060.title')}>
            {account === null ? <Directory accounts={accounts} /> : <Detail account={account} section={section} catalogue={catalogue} companies={companies} preview={preview} />}
        </AppShell>
    );
}
