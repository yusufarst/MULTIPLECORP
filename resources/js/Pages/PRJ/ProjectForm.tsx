// PG-013 *Formulir Proyek* (ROUTE-013, ROUTE-151, ROUTE-152; NAV-013; SCR-11; IA §8.2; WF-PRJ-01; J-PRJ-01; TASK-032):
// "Buat Proyek" and "Ubah proyek" — the company as an explicit field (CS-03), the client with its unit, address and PIC,
// created inline where the account may (J-MD-02; IP-19), the channel with its SIPLAH fields only for that channel as
// plain entered values (CAP-12; BR-FIN-10), the title, the one deadline (`DIR-077` CA-10), a linked project of another
// company with its reason (XL-4), and the demand lines in PT-10's line editor (IP-13). Selling prices, the output taxes
// in force and SIPLAH amounts only with `finance.view` of the company (PJ-12): a project line stores no tax, its
// components pinned at confirmation, so the column states the configured ones. After confirmation the lines are read
// only and the form says why (L-20); a locked channel says so (BR-PRJ-05). "Tanggal transaksi" gives the number's
// period (IP-07; NM-08). The answer in place, the input kept (IP-01; IP-03; IP-14); leaving with unsent input asks first
// (MSG-35; NV-02).
import { router, usePage } from '@inertiajs/react';
import { type ReactNode, useEffect, useState } from 'react';

import { ActionBar } from '@/components/action-bar';
import { type BusinessDate, BusinessDateControl, DateField } from '@/components/date-field';
import { DecimalInput, FormField, FormSection, ValidationSummary } from '@/components/form';
import { LineEditor } from '@/components/line-editor';
import { Lookup, type LookupResult } from '@/components/lookup';
import { PageHeader } from '@/components/page-header';
import { ClientCreateSheet, PartyLookup, usePartySearch } from '@/components/parties';
import { Disclosure } from '@/components/record-blocks';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { Money } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import * as decimal from '@/lib/decimal';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import type { AccountHints } from '@/lib/navigation';
import { isBackground } from '@/lib/session-core';
import { useCommand } from '@/lib/use-command';

type Company = { id: string; code: string; name: string; money: boolean; taxes: string[] };
type Product = { id: string; name: string; sku: string | null; kind: 'GOODS' | 'SERVICE'; scale: number; units: { code: string; name: string; ratio: number }[]; price?: string | null };
type Unit = {
    handle: string;
    name: string;
    active: boolean;
    addresses: { handle: string; label: string | null; address_lines: string | null; city: string | null; active: boolean }[];
    contacts: { handle: string; name: string | null; position: string | null; phone: string | null; active: boolean }[];
};
type Client = { id: string; name: string; active: boolean; units?: Unit[] };
type Existing = {
    id: string;
    number: string;
    company: string;
    company_code: string;
    state: 'DRAFT' | 'ACTIVE' | 'CANCELLED' | 'COMPLETED_NORMAL' | 'COMPLETED_FORCED';
    lock_version: number;
    client: string;
    unit: string | null;
    address: string | null;
    contact: string | null;
    channel: 'REGULAR' | 'SIPLAH';
    title: string;
    deadline: string | null;
    link: { id: string | null; number: string | null; title: string | null; visible: boolean; reason: string | null } | null;
    siplah: Record<string, string | null> | null;
    lines: { handle: string; product: Product | null; description: string; quantity: string; unit: string; mode: Mode; price?: string | null }[];
    confirmed: boolean;
    channel_locked: boolean;
};
type Props = { companies: Company[]; project: Existing | null; client: Client | null; product: Product | null; today: string };
type Mode = 'WAREHOUSE' | 'DROPSHIP' | 'SERVICE';
type Line = { key: string; handle: string | null; term: string; product: Product | null; quantity: string | null; unit: string; mode: Mode; price: string | null; description: string };
type Payload = Record<string, string | number | boolean | null | Record<string, string | null> | Record<string, string | null>[]>;

const SIPLAH_TEXTS = ['external_reference', 'program_code', 'account_code', 'notes'] as const;
const SIPLAH_AMOUNTS = ['siplah_value', 'real_purchase_value', 'fee_amount', 'va_fee_amount', 'cashback_amount', 'refund_amount'] as const;
const siplahName = (field: string): TranslationKey => `projects.siplah.${field}` as TranslationKey;
const modeName = (mode: string): TranslationKey => `projects.mode.${mode.toLowerCase()}` as TranslationKey;

const blank = (key: string): Line => ({ key, handle: null, term: '', product: null, quantity: null, unit: '', mode: 'WAREHOUSE', price: null, description: '' });

/** The quantity as entered, without the trailing zeros the database's scale adds. */
const entered = (quantity: string): string => (quantity.includes('.') ? quantity.replace(/\.?0+$/, '') : quantity);

export default function ProjectForm({ companies, project, client, today }: Props) {
    const { t } = useTranslation();
    const page = usePage<{ auth: AccountHints }>();
    const flash = page.flash as { answer?: { link?: string | null } };
    const canCreateClient = page.props.auth.capabilities.includes('parties.manage');
    const amend = project !== null;
    const command = useCommand<Payload>(() => (project === null ? '/proyek' : `/proyek/${project.id}`), project === null ? 'post' : 'put');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [company, setCompany] = useState(project?.company ?? (companies.length === 1 ? (companies[0]?.id ?? '') : ''));
    const [chosenClient, setChosenClient] = useState<LookupResult | null>(project !== null && client !== null ? { id: client.id, code: '', label: client.name } : null);
    const [unit, setUnit] = useState(project?.unit ?? '');
    const [address, setAddress] = useState(project?.address ?? '');
    const [contact, setContact] = useState(project?.contact ?? '');
    const [channel, setChannel] = useState<'REGULAR' | 'SIPLAH'>(project?.channel ?? 'REGULAR');
    const [siplah, setSiplah] = useState<Record<string, string>>(() => Object.fromEntries([...SIPLAH_TEXTS, ...SIPLAH_AMOUNTS].map((field) => [field, project?.siplah?.[field] ?? ''])));
    const [title, setTitle] = useState(project?.title ?? '');
    const [deadline, setDeadline] = useState(project?.deadline ?? '');
    const [link, setLink] = useState<LookupResult | null>(project?.link?.id != null ? { id: project.link.id, code: project.link.number ?? '', label: project.link.title ?? '' } : null);
    const [linkReason, setLinkReason] = useState(project?.link?.reason ?? '');
    const [lines, setLines] = useState<Line[]>(() => (project?.lines ?? []).map((line) => ({
        key: line.handle,
        handle: line.handle,
        term: '',
        product: line.product,
        quantity: entered(line.quantity),
        unit: line.unit,
        mode: line.mode,
        price: line.price ?? null,
        description: line.product !== null && line.description === line.product.name ? '' : line.description,
    })));
    const [creating, setCreating] = useState<{ name: string; key: number } | null>(null);
    const [dirty, setDirty] = useState(false);
    const [leaving, setLeaving] = useState<(() => void) | null>(null);
    const linked = usePartySearch('project');
    const chosen = companies.find((option) => option.id === company) ?? null;
    const money = chosen?.money ?? false;
    const units = client !== null && chosenClient !== null && client.id === chosenClient.id ? (client.units ?? []) : [];
    const chosenUnit = units.find((option) => option.handle === unit) ?? null;
    const confirmed = project?.confirmed ?? false;
    const channelLocked = project?.channel_locked ?? false;
    const companyFixed = project !== null && project.state !== 'DRAFT';
    const touch = () => { setDirty(true); };

    // A visit away with unsent input asks first (MSG-35; NV-02); the form's own partial reloads and background reads do not.
    useEffect(() => {
        const off = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (!dirty || visit.method !== 'get' || visit.only.length > 0 || isBackground(visit)) {
                return;
            }

            const href = visit.url.href;
            setLeaving(() => () => { router.visit(href); });

            return false;
        });
        const unload = (event: BeforeUnloadEvent) => {
            if (dirty) event.preventDefault();
        };
        window.addEventListener('beforeunload', unload);

        return () => {
            off();
            window.removeEventListener('beforeunload', unload);
        };
    }, [dirty]);

    const chooseClient = (next: LookupResult | null) => {
        setChosenClient(next);
        setUnit('');
        setAddress('');
        setContact('');
        touch();

        if (next !== null) {
            router.reload({ only: ['client'], data: { klien: next.id }, preserveUrl: true });
        }
    };
    const chooseProduct = (key: string, next: LookupResult | null) => {
        if (next === null) {
            setLines(lines.map((line) => (line.key === key ? { ...line, product: null, unit: '' } : line)));

            return;
        }

        // The product arrives by a partial reload; the line that named it takes its units, mode and price.
        router.reload({
            only: ['product'],
            data: { produk: next.id },
            preserveUrl: true,
            onSuccess: (visited) => {
                const found = (visited.props as unknown as Props).product;

                if (found === null) return;
                setLines((current) => current.map((line) => (line.key === key ? {
                    ...line,
                    product: found,
                    unit: found.units[0]?.code ?? '',
                    mode: found.kind === 'SERVICE' ? 'SERVICE' : line.mode === 'SERVICE' ? 'WAREHOUSE' : line.mode,
                    price: money ? (found.price ?? null) : null,
                } : line)));
            },
        });
    };
    const subtotal = (line: Line): string | null => (line.quantity === null || line.price === null ? null : decimal.round(decimal.multiply(line.quantity, line.price), 2));
    const total = lines.reduce((sum, line) => decimal.add(sum, subtotal(line) ?? '0'), '0');
    const error = (field: string): string | undefined => command.errors[field];
    const lineError = (index: number, field: string): string | undefined => command.errors[`baris.${String(index)}.${field}`];
    const fieldLabels: Record<string, string> = {
        perusahaan: t('projects.field.company'),
        klien: t('projects.field.client'),
        unit: t('projects.field.unit'),
        alamat: t('projects.field.address'),
        pic: t('projects.field.contact'),
        kanal: t('projects.field.channel'),
        judul: t('projects.field.title'),
        tenggat: t('projects.field.deadline'),
        tautan: t('projects.field.link'),
        alasan_tautan: t('projects.field.link_reason'),
        tanggal: t('pt25.label'),
    };
    const summary = Object.entries(command.errors).map(([field, message]) => ({
        id: field.startsWith('baris.') ? `line-${field.split('.')[1] ?? ''}` : `field-${field}`,
        label: field.startsWith('baris.') ? t('forms.line_number', { n: Number(field.split('.')[1] ?? 0) + 1 }) : (fieldLabels[field] ?? field),
        message,
    }));

    const submit = () => {
        command.submit({
            perusahaan: company,
            klien: chosenClient?.id ?? '',
            unit: unit === '' ? null : unit,
            alamat: address === '' ? null : address,
            pic: contact === '' ? null : contact,
            kanal: channel,
            judul: title,
            tenggat: deadline === '' ? null : deadline,
            tautan: link?.id ?? null,
            alasan_tautan: link === null ? null : linkReason,
            // The project's link as it was — also one this account may not see — stays; only a new or changed one is checked (XL-4).
            tautan_tetap: linkKept,
            tanggal: date.date,
            tanggal_otomatis: !date.chosen,
            siplah: channel === 'SIPLAH' ? Object.fromEntries(Object.entries(siplah).map(([field, value]) => [field, value === '' ? null : value])) : null,
            baris: lines.map((line) => ({
                baris: line.handle,
                produk: line.product?.id ?? '',
                satuan: line.unit,
                jumlah: line.quantity ?? '',
                harga: money ? line.price : null,
                mode: line.mode,
                keterangan: line.description === '' ? null : line.description,
            })),
            ...(project === null ? {} : { lock_version: project.lock_version }),
        }, () => { setDirty(false); });
    };
    const linkKept = project?.link != null && (!project.link.visible || (link?.id === project.link.id && linkReason === (project.link.reason ?? '')));
    const origin = project === null ? '/proyek' : `/proyek/${project.id}`;
    const pageTitle = project === null ? t('projects.form.create_title') : t('projects.form.edit_title', { nomor: project.number });

    return (
        <AppShell title={t('pages.pg_013.title')}>
            <div className="flex min-w-0 flex-col gap-6 pb-18 md:max-w-page-form">
                <PageHeader
                    title={pageTitle}
                    back={{ label: t('dialogs.back'), href: origin }}
                    date={project === null || chosen?.id !== project.company ? <BusinessDateControl today={today} value={date} onChange={(next) => { setDate(next); touch(); }} /> : undefined}
                />
                {summary.length > 0 && <ValidationSummary fields={summary} />}
                <FormSection title={t('projects.form.parties')}>
                    <FormField label={t('projects.field.company')} required id="field-perusahaan" error={error('perusahaan')}
                        help={amend && !companyFixed ? t('projects.form.company_change') : undefined}>
                        {(control) => (companyFixed ? (
                            <Input {...control} value={project.company_code} readOnly />
                        ) : (
                            <NativeSelect {...control} value={company} onChange={(event) => { setCompany(event.target.value); touch(); }}>
                                <option value="">{t('forms.choose')}</option>
                                {companies.map((option) => (
                                    <option key={option.id} value={option.id}>{`${option.code} · ${option.name}`}</option>
                                ))}
                            </NativeSelect>
                        ))}
                    </FormField>
                    <div id="field-klien" className="flex min-w-0 flex-col gap-1">
                        {confirmed ? (
                            <FormField label={t('projects.field.client')}>
                                {(control) => <Input {...control} value={chosenClient?.label ?? ''} readOnly />}
                            </FormField>
                        ) : (
                            <PartyLookup type="client" label={t('projects.field.client')} value={chosenClient} onChoose={chooseClient} canCreate={canCreateClient}
                                onCreate={(name) => { setCreating({ name, key: Date.now() }); }} />
                        )}
                        {error('klien') !== undefined && <p className="text-caption text-danger-fg">{error('klien')}</p>}
                    </div>
                    <div className="grid min-w-0 gap-4 md:grid-cols-3">
                        <FormField label={t('projects.field.unit')} id="field-unit" error={error('unit')} dependsOn={chosenClient === null ? t('projects.field.client') : undefined}
                            help={t('projects.form.activation')}>
                            {(control) => (
                                <NativeSelect {...control} value={unit} onChange={(event) => { setUnit(event.target.value); setAddress(''); setContact(''); touch(); }}>
                                    <option value="">{t('forms.choose')}</option>
                                    {units.filter((option) => option.active || option.handle === unit).map((option) => (
                                        <option key={option.handle} value={option.handle}>{option.name}</option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>
                        <FormField label={t('projects.field.address')} id="field-alamat" error={error('alamat')} dependsOn={chosenUnit === null ? t('projects.field.unit') : undefined}>
                            {(control) => (
                                <NativeSelect {...control} value={address} onChange={(event) => { setAddress(event.target.value); touch(); }}>
                                    <option value="">{t('forms.choose')}</option>
                                    {(chosenUnit?.addresses ?? []).filter((option) => option.active || option.handle === address).map((option) => (
                                        <option key={option.handle} value={option.handle}>{[option.label, option.address_lines, option.city].filter((part) => part !== null && part !== '').join(', ')}</option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>
                        <FormField label={t('projects.field.contact')} id="field-pic" error={error('pic')} dependsOn={chosenUnit === null ? t('projects.field.unit') : undefined}>
                            {(control) => (
                                <NativeSelect {...control} value={contact} onChange={(event) => { setContact(event.target.value); touch(); }}>
                                    <option value="">{t('forms.choose')}</option>
                                    {(chosenUnit?.contacts ?? []).filter((option) => option.active || option.handle === contact).map((option) => (
                                        <option key={option.handle} value={option.handle}>{[option.name, option.position].filter((part) => part !== null && part !== '').join(' · ')}</option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>
                    </div>
                </FormSection>
                <FormSection title={t('projects.form.project')}>
                    <FormField label={t('projects.field.title')} required id="field-judul" error={error('judul')}>
                        {(control) => <Input {...control} value={title} maxLength={200} onChange={(event) => { setTitle(event.target.value); touch(); }} />}
                    </FormField>
                    <FormField label={t('projects.field.deadline')} id="field-tenggat" error={error('tenggat')} help={t('projects.form.deadline_help')}>
                        {(control) => <DateField {...control} value={deadline === '' ? null : deadline} onValueChange={(value) => { setDeadline(value ?? ''); touch(); }} />}
                    </FormField>
                    <fieldset id="field-kanal" className="flex min-w-0 flex-col gap-2" disabled={channelLocked}>
                        <legend className="text-label font-medium text-text-primary">{t('projects.field.channel')}</legend>
                        <RadioGroup value={channel} onValueChange={(value) => { setChannel(value === 'SIPLAH' ? 'SIPLAH' : 'REGULAR'); touch(); }} aria-label={t('projects.field.channel')} className="flex flex-wrap gap-4">
                            {(['REGULAR', 'SIPLAH'] as const).map((option) => (
                                <label key={option} className="flex min-h-touch items-center gap-3 text-body">
                                    <RadioGroupItem value={option} disabled={channelLocked} />
                                    {t(option === 'SIPLAH' ? 'projects.channel.siplah' : 'projects.channel.regular')}
                                </label>
                            ))}
                        </RadioGroup>
                        {channelLocked && <p className="text-caption text-text-secondary">{t('projects.form.channel_locked')}</p>}
                        {error('kanal') !== undefined && <p className="text-caption text-danger-fg">{error('kanal')}</p>}
                    </fieldset>
                    {channel === 'SIPLAH' && (
                        <div className="flex min-w-0 flex-col gap-3 rounded-md border border-border-subtle p-4">
                            <p className="text-caption text-text-secondary">{t('projects.siplah.manual')}</p>
                            <div className="grid min-w-0 gap-4 md:grid-cols-2">
                                {SIPLAH_TEXTS.filter((field) => field !== 'notes').map((field) => (
                                    <FormField key={field} label={t(siplahName(field))} id={`field-siplah-${field}`} error={error(`siplah.${field}`)}>
                                        {(control) => <Input {...control} value={siplah[field] ?? ''} readOnly={channelLocked} onChange={(event) => { setSiplah({ ...siplah, [field]: event.target.value }); touch(); }} />}
                                    </FormField>
                                ))}
                                {money && SIPLAH_AMOUNTS.map((field) => (
                                    <FormField key={field} label={t(siplahName(field))} id={`field-siplah-${field}`} error={error(`siplah.${field}`)}>
                                        {(control) => <DecimalInput {...control} kind="money" scale={2} value={siplah[field] ?? ''} readOnly={channelLocked} onValueChange={(value) => { setSiplah({ ...siplah, [field]: value ?? '' }); touch(); }} />}
                                    </FormField>
                                ))}
                            </div>
                            <FormField label={t(siplahName('notes'))} id="field-siplah-notes" error={error('siplah.notes')}>
                                {(control) => <Textarea {...control} value={siplah.notes ?? ''} readOnly={channelLocked} onChange={(event) => { setSiplah({ ...siplah, notes: event.target.value }); touch(); }} />}
                            </FormField>
                        </div>
                    )}
                    <Disclosure title={t('projects.form.link')} defaultOpen={link !== null || project?.link != null} invalid={error('tautan') !== undefined || error('alasan_tautan') !== undefined}>
                        <div className="flex min-w-0 flex-col gap-3">
                            {project?.link != null && !project.link.visible ? (
                                <p className="text-body text-text-secondary">{t('projects.link.other_company')}</p>
                            ) : (
                                <div id="field-tautan" className="flex min-w-0 flex-col gap-1">
                                    <Lookup label={t('projects.field.link')} term={linked.term} onTermChange={linked.find} loading={linked.loading} cut={linked.cut}
                                        groups={linked.rows.length === 0 ? [] : [{ value: 'project', label: t('glossary.gl_004'), items: linked.rows.map((row) => ({ id: row.id, code: row.code ?? '', label: row.label, ...(row.company !== undefined ? { detail: row.company } : {}) })) }]}
                                        value={link} onChoose={(next) => { setLink(next); touch(); }} />
                                    {error('tautan') !== undefined && <p className="text-caption text-danger-fg">{error('tautan')}</p>}
                                </div>
                            )}
                            {(link !== null || project?.link != null) && (
                                <FormField label={t('projects.field.link_reason')} required id="field-alasan_tautan" error={error('alasan_tautan')}>
                                    {(control) => <Textarea {...control} value={linkReason} onChange={(event) => { setLinkReason(event.target.value); touch(); }} />}
                                </FormField>
                            )}
                        </div>
                    </Disclosure>
                </FormSection>
                <FormSection title={t('projects.form.lines')}>
                    {confirmed && <p className="text-body text-text-secondary">{t('projects.form.confirmed_lines')}</p>}
                    {money && chosen !== null && (
                        <p className="text-caption text-text-secondary">
                            {chosen.taxes.length === 0 ? t('projects.form.no_tax') : t('projects.form.taxes', { pajak: chosen.taxes.join(', ') })}
                        </p>
                    )}
                    {confirmed ? (
                        <ul role="list" className="flex flex-col divide-y divide-border-subtle">
                            {lines.map((line) => (
                                <li key={line.key} className="flex min-w-0 flex-wrap justify-between gap-2 py-2 text-body">
                                    <span className="min-w-0">{line.product?.name ?? ''}</span>
                                    <span className="tabular-nums">{`${line.quantity ?? ''} ${line.unit} · ${t(modeName(line.mode))}`}</span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <LineEditor<Line>
                            label={t('projects.form.lines')}
                            lines={lines}
                            onChange={(next) => { setLines(next); touch(); }}
                            blank={blank}
                            isBlank={(line) => line.product === null && line.quantity === null && line.term === ''}
                            columns={[
                                {
                                    key: 'product',
                                    header: t('pt10.columns.product'),
                                    className: 'min-w-64',
                                    cell: (line, update, index) => (
                                        <ProductCell line={line} error={lineError(index, 'produk')} onTerm={(term) => { update({ term }); }} onChoose={(next) => { chooseProduct(line.key, next); }} />
                                    ),
                                },
                                {
                                    key: 'quantity',
                                    header: t('pt10.columns.quantity'),
                                    numeric: true,
                                    className: 'w-32',
                                    cell: (line, update, index) => (
                                        <Cell error={lineError(index, 'jumlah')}>
                                            <DecimalInput aria-label={t('pt10.columns.quantity')} scale={3} value={line.quantity ?? ''} aria-invalid={lineError(index, 'jumlah') !== undefined ? true : undefined}
                                                onValueChange={(value) => { update({ quantity: value }); }} />
                                        </Cell>
                                    ),
                                },
                                {
                                    key: 'unit',
                                    header: t('pt10.columns.unit'),
                                    className: 'w-28',
                                    cell: (line, update, index) => (
                                        <Cell error={lineError(index, 'satuan')}>
                                            <NativeSelect aria-label={t('pt10.columns.unit')} value={line.unit} disabled={line.product === null} onChange={(event) => { update({ unit: event.target.value }); }}>
                                                {(line.product?.units ?? []).map((option) => (
                                                    <option key={option.code} value={option.code}>{option.code}</option>
                                                ))}
                                            </NativeSelect>
                                        </Cell>
                                    ),
                                },
                                ...(money ? [{
                                    key: 'price',
                                    header: t('pt10.columns.price'),
                                    numeric: true,
                                    className: 'w-40',
                                    cell: (line: Line, update: (patch: Partial<Line>) => void, index: number) => (
                                        <Cell error={lineError(index, 'harga')}>
                                            <DecimalInput aria-label={t('pt10.columns.price')} kind="money" scale={2} value={line.price ?? ''} onValueChange={(value) => { update({ price: value }); }} />
                                        </Cell>
                                    ),
                                }, {
                                    key: 'tax',
                                    header: t('pt10.columns.tax'),
                                    cell: () => <span className="text-caption text-text-secondary">{chosen === null || chosen.taxes.length === 0 ? '—' : chosen.taxes.join(', ')}</span>,
                                }] : []),
                                {
                                    key: 'mode',
                                    header: t('projects.line.mode'),
                                    className: 'w-44',
                                    cell: (line, update, index) => (
                                        <Cell error={lineError(index, 'mode')}>
                                            <NativeSelect aria-label={t('projects.line.mode')} value={line.mode} disabled={line.product?.kind === 'SERVICE'} onChange={(event) => { update({ mode: event.target.value as Mode }); }}>
                                                {(line.product?.kind === 'SERVICE' ? ['SERVICE'] : ['WAREHOUSE', 'DROPSHIP']).map((option) => (
                                                    <option key={option} value={option}>{t(modeName(option))}</option>
                                                ))}
                                            </NativeSelect>
                                        </Cell>
                                    ),
                                },
                                ...(money ? [{
                                    key: 'subtotal',
                                    header: t('pt10.columns.subtotal'),
                                    numeric: true,
                                    cell: (line: Line) => {
                                        const value = subtotal(line);

                                        return value === null ? '' : <Money value={value} />;
                                    },
                                }] : []),
                            ]}
                            summary={(line) => ({
                                title: line.product?.name ?? t('projects.form.new_line'),
                                secondary: line.quantity === null ? undefined : `${line.quantity} ${line.unit} · ${t(modeName(line.mode))}`,
                                ...(money && subtotal(line) !== null ? { figure: <Money value={subtotal(line) ?? '0'} /> } : {}),
                            })}
                            details={(line, update) => (
                                <FormField label={t('projects.line.description')} help={t('projects.line.description_help')}>
                                    {(control) => <Input {...control} value={line.description} maxLength={500} onChange={(event) => { update({ description: event.target.value }); }} />}
                                </FormField>
                            )}
                        />
                    )}
                    {lines.length === 0 && <p className="text-body text-text-secondary">{t('projects.form.no_lines')}</p>}
                    {money && lines.length > 0 && (
                        <p className="flex items-baseline justify-end gap-3 text-body">
                            <span className="text-text-secondary">{t('projects.form.total')}</span>
                            <Money value={decimal.round(total, 2)} className="font-semibold" />
                        </p>
                    )}
                </FormSection>
            </div>
            <ActionBar label={pageTitle} answer={command.answer} answerAction={flash.answer?.link != null && command.answer?.kind === 'REJECTED' ? (
                <Button variant="outline" size="sm" render={<a href={flash.answer.link} />}>{t('projects.action.start_numbering')}</Button>
            ) : undefined}>
                <Button variant="outline" size="lg" onClick={() => { router.visit(origin); }}>{t('actions.cancel')}</Button>
                <Button size="lg" disabled={command.pending} onClick={submit}>{project === null ? t('projects.action.save_new') : t('projects.action.save')}</Button>
            </ActionBar>
            {creating !== null && (
                <ClientCreateSheet key={creating.key} open inline name={creating.name}
                    onOpenChange={(open) => { if (!open) setCreating(null); }}
                    onCreated={(value) => {
                        if (value === null) return;
                        chooseClient({ id: value.organization.id, code: '', label: value.organization.name });
                        setUnit(value.unit?.handle ?? '');
                        setAddress(value.address?.handle ?? '');
                        setContact(value.contact?.handle ?? '');
                    }} />
            )}
            <UnsentInputDialog open={leaving !== null} kind="leave" onStay={() => { setLeaving(null); }}
                onLeave={() => { const go = leaving; setDirty(false); setLeaving(null); go?.(); }} />
        </AppShell>
    );
}

/** A line's field with its error under it (IP-14). */
function Cell({ error, children }: { error: string | undefined; children: ReactNode }) {
    return (
        <div className="flex min-w-0 flex-col gap-1">
            {children}
            {error !== undefined && <p className="text-caption text-danger-fg">{error}</p>}
        </div>
    );
}

/** A line's product: the catalogue's lookup, the chosen product named. */
function ProductCell({ line, error, onTerm, onChoose }: { line: Line; error: string | undefined; onTerm: (term: string) => void; onChoose: (next: LookupResult | null) => void }) {
    const { t } = useTranslation();
    const found = usePartySearch('product');

    return (
        <Cell error={error}>
            <Lookup
                label={t('pt10.columns.product')}
                term={found.term === '' ? line.term : found.term}
                onTermChange={(term) => { found.find(term); onTerm(term); }}
                loading={found.loading}
                cut={found.cut}
                groups={found.rows.length === 0 ? [] : [{ value: 'product', label: t('pt10.columns.product'), items: found.rows.map((row) => ({ id: row.id, code: row.code ?? '', label: row.label })) }]}
                value={line.product === null ? null : { id: line.product.id, code: line.product.sku ?? '', label: line.product.name }}
                onChoose={onChoose}
            />
        </Cell>
    );
}
