// PG-059 *Perusahaan* (ROUTE-059, ROUTE-131–ROUTE-135; NAV-059; SCR-54; IA §8.6; J-MD-01; ADMIN_FLOW §12.4; TASK-025), the
// Owner's alone: the companies, then one company by its address key — its readiness list derived from what exists, its
// legal data, identity assets, bank accounts and state, each section saved by its own command and answered in place
// (IP-19). Bank accounts and identity assets ask the step-up when sent, the legal data only when an identity asset
// changes (IP-10). A referenced bank account is never edited: a change adds the new one and deactivates the old. On the
// phone the sections are read-only (`[desk]`).
import { router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { type ReactNode, useRef, useState } from 'react';

import { CompanyTag } from '@/components/company';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { FileThumbnail } from '@/components/file-thumbnail';
import { FormField, FormSection } from '@/components/form';
import { UploadField, type UploadState } from '@/components/notices';
import { type Answer, OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { ReadinessList } from '@/components/signals';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { DateTimeText } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { uploadFile } from '@/lib/file-upload';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Section = 'data' | 'aset' | 'rekening' | 'status';
type Directory = { id: string; code: string; name: string; active: boolean };
type Account = { handle: string; bank_name: string; account_number: string; account_holder: string; branch: string | null; active: boolean; deactivated_at: string | null; lock_version: number };
type Readiness = { key: string; complete: boolean; type?: string };
type Legal = { legal_name: string; legal_form: string | null; npwp: string | null; address_lines: string | null; city: string | null; phone: string | null; email: string | null; director_name: string | null };
type Company = Legal & {
    id: string;
    code: string;
    active: boolean;
    deactivated_at: string | null;
    deactivated_reason: string | null;
    lock_version: number;
    assets: Record<'logo' | 'stamp' | 'signature', string | null>;
    accounts: Account[];
    readiness: Readiness[];
};
type Props = { companies: Directory[]; company: Company | null; section: Section };
type Payload = Record<string, string | number | null>;

const SECTIONS: Section[] = ['data', 'aset', 'rekening', 'status'];
const LEGAL: (keyof Legal)[] = ['legal_name', 'legal_form', 'npwp', 'address_lines', 'city', 'phone', 'email', 'director_name'];
const ASSETS = ['logo', 'stamp', 'signature'] as const;

function visit(company: string | null, section?: Section) {
    const query = new URLSearchParams();

    if (company !== null) {
        query.set('perusahaan', company);

        if (section !== undefined) query.set('bagian', section);
    }

    router.get(`/pengaturan/perusahaan${query.size > 0 ? `?${query.toString()}` : ''}`, {}, { preserveScroll: true });
}

/** A numbered type's name: a document type's, or the project's and the purchase's glossary terms. */
function typeLabel(type: string): TranslationKey {
    return (type === 'PROJECT' ? 'glossary.gl_004' : type === 'PURCHASE' ? 'glossary.gl_006' : `documents.${type.toLowerCase().replace('-', '_')}`) as TranslationKey;
}

/** A command's success on the page its answer opened, once (IP-01). */
function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

/** A form's refusal or failure in place, with "Coba Lagi" while the outcome is unknown (IP-02; IP-04). */
function CommandAnswer({ command }: { command: { answer: Answer | null; unknown: boolean; retry: () => void } }) {
    const { t } = useTranslation();

    if (command.answer === null || command.answer.kind === 'COMMITTED') return null;

    return (
        <OutcomeMessage
            answer={command.answer}
            action={
                command.unknown ? (
                    <Button variant="outline" size="sm" onClick={command.retry}>
                        {t('actions.try_again')}
                    </Button>
                ) : undefined
            }
        />
    );
}

function LegalFields({ values, onChange, errors }: { values: Legal; onChange: (field: keyof Legal, value: string) => void; errors: Record<string, string> }) {
    const { t } = useTranslation();

    return (
        <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2">
            {LEGAL.map((field) => (
                <FormField key={field} label={t(`companies.field.${field}` as TranslationKey)} required={field === 'legal_name'} error={errors[field]} className={field === 'address_lines' ? 'md:col-span-2' : undefined}>
                    {(control) =>
                        field === 'address_lines' ? (
                            <Textarea {...control} value={values[field] ?? ''} maxLength={500} onChange={(event) => { onChange(field, event.target.value); }} />
                        ) : (
                            <Input {...control} value={values[field] ?? ''} type={field === 'email' ? 'email' : 'text'} onChange={(event) => { onChange(field, event.target.value); }} />
                        )
                    }
                </FormField>
            ))}
        </div>
    );
}

function CreateCompany({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
    const { t } = useTranslation();
    const [code, setCode] = useState('');
    const [values, setValues] = useState<Legal>({ legal_name: '', legal_form: null, npwp: null, address_lines: null, city: null, phone: null, email: null, director_name: null });
    const create = useCommand<Payload>(() => '/pengaturan/perusahaan', 'post');

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t('companies.create')}</SheetTitle>
                </SheetHeader>
                <form
                    className="flex min-w-0 flex-col gap-6"
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        create.submit({ code, ...values }, () => { onOpenChange(false); });
                    }}
                >
                    <FormField label={t('companies.field.code')} required help={t('companies.field.code_help')} error={create.errors.code}>
                        {(control) => <Input {...control} value={code} maxLength={12} onChange={(event) => { setCode(event.target.value.toUpperCase()); }} />}
                    </FormField>
                    <LegalFields values={values} errors={create.errors} onChange={(field, value) => { setValues((current) => ({ ...current, [field]: value })); }} />
                    <CommandAnswer command={create} />
                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="ghost" onClick={() => { onOpenChange(false); }}>
                            {t('actions.cancel')}
                        </Button>
                        <Button type="submit" pending={create.pending} disabled={create.frozen}>
                            {t('companies.create')}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}

function LegalData({ company }: { company: Company }) {
    const { t } = useTranslation();
    const [values, setValues] = useState<Legal>({ ...company });
    const update = useCommand<Payload>(() => `/pengaturan/perusahaan/${company.id}`, 'put');

    return (
        <>
            <dl className="grid min-w-0 grid-cols-1 gap-3 md:hidden">
                {LEGAL.map((field) => (
                    <div key={field} className="flex min-w-0 flex-col gap-1">
                        <dt className="text-caption text-text-tertiary">{t(`companies.field.${field}` as TranslationKey)}</dt>
                        <dd className="min-w-0 break-words text-body text-text-primary">{company[field] ?? '—'}</dd>
                    </div>
                ))}
            </dl>
        <form
            className="flex min-w-0 flex-col gap-6 max-md:hidden"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                update.submit({ ...values, lock_version: company.lock_version });
            }}
        >
            <p className="text-body text-text-secondary">{t('companies.issued_keep_identity')}</p>
            <LegalFields values={values} errors={update.errors} onChange={(field, value) => { setValues((current) => ({ ...current, [field]: value })); }} />
            <CommandAnswer command={update} />
            <div className="flex justify-end">
                <Button type="submit" pending={update.pending} disabled={update.frozen}>
                    {t('glossary.gl_157')}
                </Button>
            </div>
        </form>
        </>
    );
}

function AssetRow({ company, asset }: { company: Company; asset: (typeof ASSETS)[number] }) {
    const { t } = useTranslation();
    const [state, setState] = useState<UploadState>({ phase: 'idle' });
    const [file, setFile] = useState<string | null>(null);
    const abort = useRef<AbortController | null>(null);
    const last = useRef<File | null>(null);
    const replace = useCommand<Payload>(() => `/pengaturan/perusahaan/${company.id}/aset-identitas`, 'post');
    const current = company.assets[asset];
    const choose = (chosen: File) => {
        last.current = chosen;
        abort.current = new AbortController();
        setState({ phase: 'uploading', progress: 0 });
        void uploadFile('/berkas', chosen, 'file', (progress) => { setState({ phase: 'uploading', progress }); }, abort.current.signal, { purpose: 'aset-identitas', company: company.id }).then((answer) => {
            if (answer.kind === 'stored') {
                setFile(answer.id);
                setState({ phase: 'done', name: answer.name });
            } else if (answer.kind === 'refused') {
                setState({ phase: 'error', message: answer.message });
            } else {
                setState(answer.kind === 'cancelled' ? { phase: 'idle' } : { phase: 'error', message: t('messages.msg_07') });
            }
        });
    };

    return (
        <FormSection title={t(`companies.asset.${asset}` as TranslationKey)}>
            <div className="flex min-w-0 items-center gap-3">
                {current !== null ? <FileThumbnail fileId={current} alt={t(`companies.asset.${asset}` as TranslationKey)} /> : <span className="text-body text-text-secondary">{t('companies.asset.none')}</span>}
            </div>
            <div className="flex min-w-0 flex-col gap-3 max-md:hidden">
                <UploadField
                    label={t('companies.asset.replace')}
                    accept=".png,.jpg,.jpeg,image/png,image/jpeg"
                    limits={t('companies.asset.limits')}
                    state={state}
                    onChoose={choose}
                    onCancel={() => abort.current?.abort()}
                    onRemove={() => {
                        setFile(null);
                        setState({ phase: 'idle' });
                    }}
                    onRetry={() => { if (last.current !== null) choose(last.current); }}
                />
                <CommandAnswer command={replace} />
                <div>
                    <Button variant="outline" disabled={file === null || replace.frozen} pending={replace.pending} onClick={() => { if (file !== null) replace.submit({ asset, file, lock_version: company.lock_version }); }}>
                        {t('companies.asset.set')}
                    </Button>
                </div>
            </div>
        </FormSection>
    );
}

function AccountForm({ company, account, onDone }: { company: Company; account: Account | null; onDone: () => void }) {
    const { t } = useTranslation();
    const initial = account === null
        ? { bank_name: '', account_number: '', account_holder: company.legal_name, branch: '' }
        : { bank_name: account.bank_name, account_number: account.account_number, account_holder: account.account_holder, branch: account.branch ?? '' };
    const [details, setDetails] = useState(initial);
    const [leaving, setLeaving] = useState(false);
    const [reason, setReason] = useState('');
    const [confirming, setConfirming] = useState(false);
    const command = useCommand<Payload>(() => `/pengaturan/perusahaan/${company.id}/rekening`, 'post');
    const field = (name: keyof typeof details, label: TranslationKey, required = true) => (
        <FormField label={t(label)} required={required} error={command.errors[name]}>
            {(control) => <Input {...control} value={details[name]} onChange={(event) => { setDetails((current) => ({ ...current, [name]: event.target.value })); }} />}
        </FormField>
    );

    return (
        <form
            className="flex min-w-0 flex-col gap-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                if (account === null) {
                    command.submit({ action: 'add', ...details }, onDone);
                } else {
                    setConfirming(true);
                }
            }}
        >
            {account !== null && <p className="text-body text-text-secondary">{t('companies.account.change_line')}</p>}
            <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2">
                {field('bank_name', 'companies.account.bank_name')}
                {field('account_number', 'companies.account.account_number')}
                {field('account_holder', 'companies.account.account_holder')}
                {field('branch', 'companies.account.branch', false)}
            </div>
            {account !== null && (
                <ConfirmCommand
                    open={confirming}
                    onOpenChange={setConfirming}
                    title={t('companies.account.change')}
                    consequence={t('companies.account.change_line')}
                    reason={{ value: reason, onChange: setReason, error: command.errors.reason }}
                    confirmLabel={t('companies.account.change')}
                    pending={command.pending}
                    answer={command.answer}
                    onConfirm={() => {
                        command.submit({ action: 'change', account: account.handle, lock_version: account.lock_version, reason, ...details }, () => {
                            setConfirming(false);
                            onDone();
                        });
                    }}
                />
            )}
            <CommandAnswer command={command} />
            <div className="flex justify-end gap-3">
                <Button type="button" variant="ghost" onClick={() => { if (JSON.stringify(details) === JSON.stringify(initial)) onDone(); else setLeaving(true); }}>
                    {t('actions.cancel')}
                </Button>
                <Button type="submit" pending={command.pending} disabled={command.frozen}>
                    {account === null ? t('companies.account.add') : t('companies.account.change')}
                </Button>
            </div>
            <UnsentInputDialog open={leaving} kind="leave" onStay={() => { setLeaving(false); }} onLeave={onDone} />
        </form>
    );
}

function Accounts({ company }: { company: Company }) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<Account | 'new' | null>(null);
    const [closing, setClosing] = useState<Account | null>(null);
    const [reason, setReason] = useState('');
    const deactivate = useCommand<Payload>(() => `/pengaturan/perusahaan/${company.id}/rekening`, 'post');

    return (
        <div className="flex min-w-0 flex-col gap-4">
            {company.accounts.length === 0 ? (
                <RegionEmpty text={t('companies.account.empty')} />
            ) : (
                <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                    {company.accounts.map((account) => (
                        <li key={account.handle} className="flex min-w-0 flex-wrap items-center gap-3 py-3">
                            <span className="flex min-w-0 flex-1 flex-col">
                                <span className="text-body font-medium text-text-primary">
                                    {account.bank_name} · {account.account_number}
                                </span>
                                <span className="text-caption text-text-tertiary">{[account.account_holder, account.branch].filter((part) => part !== null).join(' · ')}</span>
                            </span>
                            {account.active ? <Badge tone="success">{t('companies.account.active')}</Badge> : <Badge>{t('companies.account.inactive')}</Badge>}
                            {account.active && (
                                <span className="flex gap-2 max-md:hidden">
                                    <Button variant="outline" size="sm" onClick={() => { setEditing(account); }}>
                                        {t('companies.account.change')}
                                    </Button>
                                    <Button variant="ghost" size="sm" onClick={() => { setClosing(account); }}>
                                        {t('companies.account.deactivate')}
                                    </Button>
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {editing === null ? (
                <div className="max-md:hidden">
                    <Button variant="outline" onClick={() => { setEditing('new'); }}>
                        <Icon icon={Plus} size="sm" />
                        {t('companies.account.add')}
                    </Button>
                </div>
            ) : (
                <AccountForm key={editing === 'new' ? 'new' : editing.handle} company={company} account={editing === 'new' ? null : editing} onDone={() => { setEditing(null); }} />
            )}
            <ConfirmCommand
                open={closing !== null}
                onOpenChange={(open) => { if (!open) setClosing(null); }}
                title={t('companies.account.deactivate')}
                consequence={t('companies.account.deactivate_line')}
                reason={{ value: reason, onChange: setReason, error: deactivate.errors.reason }}
                confirmLabel={t('companies.account.deactivate')}
                destructive
                pending={deactivate.pending}
                answer={deactivate.answer}
                onConfirm={() => {
                    if (closing !== null) deactivate.submit({ action: 'deactivate', account: closing.handle, lock_version: closing.lock_version, reason }, () => { setClosing(null); });
                }}
            />
        </div>
    );
}

function Status({ company }: { company: Company }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const deactivate = useCommand<Payload>(() => `/pengaturan/perusahaan/${company.id}/nonaktifkan`, 'post');

    if (!company.active) {
        return (
            <div className="flex min-w-0 flex-col gap-2">
                <p className="text-body text-text-primary">{t('companies.status.inactive')}</p>
                {company.deactivated_at !== null && <DateTimeText value={company.deactivated_at} className="text-caption text-text-tertiary" />}
                {company.deactivated_reason !== null && <p className="text-body text-text-secondary">{company.deactivated_reason}</p>}
            </div>
        );
    }

    return (
        <div className="flex min-w-0 flex-col gap-4">
            <p className="text-body text-text-secondary">{t('companies.status.deactivate_line')}</p>
            <div className="max-md:hidden">
                <Button variant="destructive" onClick={() => { setOpen(true); }}>
                    {t('companies.status.deactivate')}
                </Button>
            </div>
            <ConfirmCommand
                open={open}
                onOpenChange={setOpen}
                title={t('companies.status.deactivate')}
                consequence={t('companies.status.deactivate_line')}
                reason={{ value: reason, onChange: setReason, error: deactivate.errors.reason }}
                confirmLabel={t('companies.status.deactivate')}
                destructive
                pending={deactivate.pending}
                answer={deactivate.answer}
                onConfirm={() => { deactivate.submit({ reason, lock_version: company.lock_version }, () => { setOpen(false); }); }}
            />
        </div>
    );
}

function Detail({ company, section }: { company: Company; section: Section }) {
    const { t } = useTranslation();
    const link = (key: string): { href: string; linkLabel: string } | Record<string, never> =>
        key === 'tax' ? { href: '/pengaturan/pajak', linkLabel: t('companies.readiness.open_tax') }
        : key === 'number_format' || key === 'numbering' ? { href: `/pengaturan/penomoran?perusahaan=${company.id}`, linkLabel: t('companies.readiness.open_numbering') }
        : key === 'admin_access' ? { href: '/pengaturan/pengguna', linkLabel: t('companies.readiness.open_access') }
        : {};
    const items = company.readiness.map((line, index) => ({
        key: `${line.key}-${String(index)}`,
        label: line.type !== undefined
            ? t(line.complete ? 'companies.readiness.numbering' : 'companies.readiness.numbering_missing', { jenis: t(typeLabel(line.type)) })
            : t(`companies.readiness.${line.key}` as TranslationKey),
        complete: line.complete,
        ...link(line.key),
    }));
    const body: Record<Section, ReactNode> = {
        data: <LegalData key={company.id} company={company} />,
        aset: (
            <div className="flex min-w-0 flex-col gap-6">
                {ASSETS.map((asset) => (
                    <AssetRow key={`${asset}-${String(company.lock_version)}`} company={company} asset={asset} />
                ))}
            </div>
        ),
        rekening: <Accounts key={company.accounts.map((account) => `${account.handle}${String(account.lock_version)}`).join('|')} company={company} />,
        status: <Status company={company} />,
    };

    return (
        <div className="flex min-w-0 flex-col gap-6">
            <PageHeader title={company.legal_name} facts={<CompanyTag code={company.code} />} />
            <PageAnswer />
            <FormSection title={t('companies.readiness.title')}>
                <ReadinessList label={t('companies.readiness.title')} items={items} />
            </FormSection>
            <Tabs value={section} onValueChange={(value) => { visit(company.id, String(value) as Section); }}>
                <TabsList>
                    {SECTIONS.map((key) => (
                        <TabsTab key={key} value={key}>
                            {t(`companies.section.${key}` as TranslationKey)}
                        </TabsTab>
                    ))}
                </TabsList>
                {SECTIONS.map((key) => (
                    <TabsPanel key={key} value={key}>
                        {body[key]}
                    </TabsPanel>
                ))}
            </Tabs>
        </div>
    );
}

export default function Companies({ companies, company, section }: Props) {
    const { t } = useTranslation();
    const [creating, setCreating] = useState(false);
    const createAction = (
        <Button className="max-md:hidden" onClick={() => { setCreating(true); }}>
            <Icon icon={Plus} size="sm" />
            {t('companies.create')}
        </Button>
    );

    return (
        <AppShell title={t('pages.pg_059.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                {company === null ? (
                    <>
                        <PageHeader title={t('pages.pg_059.title')} primary={createAction} />
                        <PageAnswer />
                        {companies.length === 0 ? (
                            <RegionEmpty text={t('companies.empty')} action={createAction} />
                        ) : (
                            <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                {companies.map((item) => (
                                    <li key={item.id}>
                                        <button type="button" className="flex min-h-row w-full min-w-0 items-center gap-3 py-3 text-left hover:bg-row-hover" onClick={() => { visit(item.id, 'data'); }}>
                                            <CompanyTag code={item.code} />
                                            <span className="min-w-0 flex-1 truncate text-body text-text-primary">{item.name}</span>
                                            {!item.active && <Badge>{t('companies.status.inactive_badge')}</Badge>}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </>
                ) : (
                    <>
                        <div>
                            <Button variant="ghost" size="sm" onClick={() => { visit(null); }}>
                                {t('companies.back')}
                            </Button>
                        </div>
                        <Detail company={company} section={section} />
                    </>
                )}
            </div>
            <CreateCompany open={creating} onOpenChange={setCreating} />
        </AppShell>
    );
}
