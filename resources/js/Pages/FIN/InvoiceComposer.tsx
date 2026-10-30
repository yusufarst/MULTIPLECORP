// PG-042 *Buat Invoice* (ROUTE-042, ROUTE-241, ROUTE-242; NAV-042; WF-FIN-01; J-FIN-01; D-DS-23; INT-012; TASK-049): "Diambil dari proyek" and "Diisi sekarang" on the left and the live preview "DRAF — belum terbit" on the
// right from 1,025 px, Edit → Preview → Finalize on the phone (DC-11): a draft from confirmed lines or a value-only DP or termin, its output tax apart from the sales value; "Simpan Draf" at the root's version, a CONFLICT with the
// input kept (ST-01; M-38); "Terbitkan Invoice", or its layout *Nota*, after its confirmation (IP-08; MSG-39), its result in place saying that no receivable is active and no cash is recorded, then "Buka Invoice" (NAV-042).
import { router } from '@inertiajs/react';
import { Plus, Send, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { BusinessDateControl, type BusinessDate } from '@/components/date-field';
import { DecimalInput, FormField, FormSection } from '@/components/form';
import { OutcomeMessage, type Answer } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Money } from '@/components/values';
import { DocumentComposer } from '@/features/doc/composer';
import { type Paper } from '@/features/doc/paper';
import { type InvoiceTax, liveInvoice, proposedTax } from '@/features/fin/invoice';
import AppShell from '@/layouts/AppShell';
import { add, multiply, round } from '@/lib/decimal';
import { money } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { isBackground } from '@/lib/session-core';
import { useCommand } from '@/lib/use-command';

type ProjectLine = { handle: string; no: number; description: string; quantity: string; unit: string; scale: number; price: string; value: string };
type Draft = { id: string; lock_version: number; kind: 'ITEMIZED' | 'VALUE_ONLY'; business_date: string; sales: string; tax: string; lines: { handle: string | null; description: string; quantity: string | null; value: string }[]; taxes: Record<string, string>; facts: string };
type Props = {
    project: { id: string; number: string; title: string; company: string; client: string; state: string } | null;
    lines: ProjectLine[];
    confirmed: string;
    remaining: string;
    taxes: InvoiceTax[];
    paper: Paper;
    draft: Draft | null;
    issued: { id: string; number: string | null } | null;
    today: string;
    can: { issue: boolean };
};
type ValueLine = { description: string; value: string };
type Payload = Record<string, string | number | boolean | { jenis: string; tanggal: string; baris: Record<string, string>[]; pajak: Record<string, string> }>;

function Answered({ command }: { command: { answer: Answer | null; unknown: boolean; retry: () => void } }) {
    const { t } = useTranslation();

    return command.answer === null ? null : (
        <OutcomeMessage answer={command.answer} action={command.unknown ? <Button variant="outline" size="sm" onClick={command.retry}>{t('actions.try_again')}</Button> : undefined} />
    );
}

export default function InvoiceComposer({ project, lines, confirmed, remaining, taxes, paper, draft, issued, today, can }: Props) {
    const { t } = useTranslation();
    const [kind, setKind] = useState<'ITEMIZED' | 'VALUE_ONLY'>(draft?.kind ?? 'VALUE_ONLY');
    const [date, setDate] = useState<BusinessDate>({ date: draft?.business_date ?? today, chosen: draft !== null && draft.business_date !== today });
    const [quantities, setQuantities] = useState<Record<string, string>>(() => Object.fromEntries((draft?.lines ?? []).filter((line) => line.handle !== null && line.quantity !== null).map((line) => [line.handle ?? '', line.quantity ?? ''])));
    const [values, setValues] = useState<ValueLine[]>(() => (draft?.kind === 'VALUE_ONLY' ? draft.lines.map((line) => ({ description: line.description, value: line.value })) : [{ description: '', value: '' }]));
    const [stated, setStated] = useState<Record<string, string>>({});
    const [layout, setLayout] = useState<'DOC-06' | 'DOC-04'>('DOC-06');
    const [dirty, setDirty] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [leaving, setLeaving] = useState<(() => void) | null>(null);
    const departing = useRef(false);
    const save = useCommand<Payload>(() => '/keuangan/invoice', 'post');
    const issue = useCommand<Payload>(() => `/keuangan/invoice/${draft?.id ?? ''}/terbitkan`, 'post');
    const origin = `/proyek/${project?.id ?? ''}`;

    // A visit away with unsent input asks first (MSG-35; NV-02); background reads do not.
    useEffect(() => {
        const off = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (!dirty || departing.current || visit.method !== 'get' || visit.only.length > 0 || isBackground(visit)) {
                return;
            }

            const href = visit.url.href;
            setLeaving(() => () => { router.visit(href); });

            return false;
        });
        const unload = (event: BeforeUnloadEvent) => {
            if (dirty && !departing.current) event.preventDefault();
        };
        window.addEventListener('beforeunload', unload);

        return () => {
            off();
            window.removeEventListener('beforeunload', unload);
        };
    }, [dirty]);

    const chosen = lines.filter((line) => (quantities[line.handle] ?? '') !== '');
    const sales = kind === 'ITEMIZED'
        ? add('0', ...chosen.map((line) => round(multiply(quantities[line.handle] ?? '0', line.price), 2)))
        : add('0', ...values.map((line) => (line.value === '' ? '0' : line.value)));
    // What the actor typed, else what the draft stores for this same value (DATABASE §12), else the proposal.
    const saved = draft !== null && round(sales, 2) === round(draft.sales, 2) ? draft.taxes : {};
    const taxRows = taxes.map((tax) => ({ code: tax.code, rate: tax.rate, amount: stated[tax.code] ?? saved[tax.code] ?? proposedTax(sales, tax) }));
    const live = useMemo<Paper>(() => liveInvoice(paper, kind === 'ITEMIZED'
        ? chosen.map((line) => ({ description: line.description, quantity: quantities[line.handle] ?? null, unit: line.unit, price: line.price, value: round(multiply(quantities[line.handle] ?? '0', line.price), 2) }))
        : values.filter((line) => line.description !== '' || line.value !== '').map((line) => ({ description: line.description, quantity: null, unit: null, price: null, value: line.value === '' ? '0' : line.value })),
    taxRows.map((tax) => ({ ...tax, amount: tax.amount === '' ? '0' : tax.amount })), date.date), [paper, kind, chosen, quantities, values, taxRows, date.date]);
    const content = {
        jenis: kind,
        tanggal: date.date,
        baris: kind === 'ITEMIZED' ? chosen.map((line) => ({ baris: line.handle, jumlah: quantities[line.handle] ?? '' })) : values.map((line) => ({ keterangan: line.description, nilai: line.value })),
        pajak: Object.fromEntries(taxRows.filter((tax) => stated[tax.code] !== undefined || saved[tax.code] !== undefined).map((tax) => [tax.code, tax.amount])),
    };
    const submit = () => {
        save.submit({ ...(draft === null ? { proyek: project?.id ?? '' } : { invoice: draft.id, lock_version: draft.lock_version }), isi: content }, () => { setDirty(false); });
    };
    const touch = () => { setDirty(true); };
    const lineError = (index: number, field: string): string | undefined => save.errors[`isi.baris.${String(index)}.${field}`];

    if (project === null) {
        return null;
    }

    if (issued !== null) {
        return (
            <AppShell title={t('pages.pg_042.title')} back={{ href: origin, label: t('pages.pg_011.title') }}>
                <div className="flex min-w-0 flex-col gap-6">
                    <PageHeader title={t('invoices.title_draft', { nomor: project.number })} back={{ label: t('dialogs.back'), href: origin }} />
                    <OutcomeMessage answer={{ kind: 'COMMITTED', text: t('invoices.composer.issued', { nomor: issued.number ?? '' }) }} />
                    <div><Button render={<a href={`/keuangan/invoice/${issued.id}`} />}>{t('invoices.action.open')}</Button></div>
                </div>
            </AppShell>
        );
    }

    const form = (
        <div className="flex min-w-0 flex-col gap-6">
            <FormSection title={t('invoices.composer.from_project')}>
                <p className="text-body text-text-primary">{`${project.title} · ${project.client}`}</p>
                <p className="text-body text-text-secondary">{t('invoices.composer.confirmed', { nilai: money(confirmed), sisa: money(remaining) })}</p>
            </FormSection>
            <FormSection title={t('invoices.composer.entered')}>
                <BusinessDateControl today={today} value={date} onChange={(next) => { setDate(next); touch(); }} />
                {(save.errors.tanggal ?? save.errors['isi.tanggal']) !== undefined && <p className="text-body text-danger">{save.errors.tanggal ?? save.errors['isi.tanggal']}</p>}
                <FormField label={t('invoices.composer.kind')}>
                    {() => (
                        <RadioGroup value={kind} onValueChange={(value) => { setKind(value as typeof kind); touch(); }} className="flex flex-wrap gap-4">
                            <label className="flex items-center gap-2 text-body"><RadioGroupItem value="VALUE_ONLY" />{t('invoices.composer.value_only')}</label>
                            <label className="flex items-center gap-2 text-body"><RadioGroupItem value="ITEMIZED" />{t('invoices.composer.itemized')}</label>
                        </RadioGroup>
                    )}
                </FormField>
                {kind === 'ITEMIZED' ? (
                    lines.length === 0 ? <RegionEmpty text={t('invoices.refusal.unconfirmed')} /> : (
                        <ol aria-label={t('invoices.detail.lines')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {lines.map((line) => (
                                <li key={line.handle} data-testid="invoice-line" className="grid min-w-0 gap-2 py-3 sm:grid-cols-2 sm:items-end">
                                    <div className="flex min-w-0 flex-col">
                                        <span className="font-semibold text-text-primary">{`${String(line.no)}. ${line.description}`}</span>
                                        <span className="text-body text-text-secondary">{`${line.quantity} ${line.unit} · `}<Money value={line.value} /></span>
                                    </div>
                                    <FormField label={t('invoices.composer.line_quantity', { satuan: line.unit })} error={lineError(chosen.findIndex((each) => each.handle === line.handle), 'jumlah')}>
                                        {(control) => <DecimalInput {...control} value={quantities[line.handle] ?? ''} scale={line.scale} unit={line.unit} onValueChange={(value) => { setQuantities({ ...quantities, [line.handle]: value ?? '' }); touch(); }} />}
                                    </FormField>
                                </li>
                            ))}
                        </ol>
                    )
                ) : (
                    <div className="flex min-w-0 flex-col gap-3">
                        {values.map((line, index) => (
                            <div key={index} data-testid="invoice-value-line" className="flex min-w-0 flex-wrap items-end gap-2">
                                <FormField label={t('invoices.composer.line_description')} required error={lineError(index, 'keterangan')}>
                                    {(control) => <Input {...control} value={line.description} onChange={(event) => { setValues(values.map((each, at) => (at === index ? { ...each, description: event.target.value } : each))); touch(); }} />}
                                </FormField>
                                <FormField label={t('invoices.composer.line_value')} required error={lineError(index, 'nilai')}>
                                    {(control) => <DecimalInput {...control} value={line.value} scale={2} kind="money" onValueChange={(value) => { setValues(values.map((each, at) => (at === index ? { ...each, value: value ?? '' } : each))); touch(); }} />}
                                </FormField>
                                <Button variant="ghost" size="sm" disabled={values.length === 1} aria-label={t('invoices.composer.remove_line', { n: index + 1 })} onClick={() => { setValues(values.filter((_, at) => at !== index)); touch(); }}>
                                    <Icon icon={Trash2} size="sm" />
                                </Button>
                            </div>
                        ))}
                        <div>
                            <Button variant="outline" size="sm" onClick={() => { setValues([...values, { description: '', value: '' }]); touch(); }}>
                                <Icon icon={Plus} size="sm" />
                                {t('invoices.composer.add_line')}
                            </Button>
                        </div>
                    </div>
                )}
                {save.errors['isi.baris'] !== undefined && <p className="text-body text-danger">{save.errors['isi.baris']}</p>}
                {taxes.length === 0 ? <p className="text-body text-text-secondary">{t('invoices.composer.no_tax')}</p> : taxRows.map((tax, index) => (
                    <FormField key={tax.code} label={t('invoices.composer.tax', { kode: tax.code })} help={taxes[index]?.evidence_required === true ? t('invoices.composer.tax_evidence') : t('invoices.composer.tax_help')}>
                        {(control) => <DecimalInput {...control} value={tax.amount} scale={2} kind="money" onValueChange={(value) => { setStated({ ...stated, [tax.code]: value ?? '' }); touch(); }} />}
                    </FormField>
                ))}
            </FormSection>
        </div>
    );
    const finalize = (
        <div className="flex min-w-0 flex-col gap-3">
            <Answered command={save} />
            <Answered command={issue} />
            <div className="flex flex-wrap gap-2">
                <Button variant="ghost" onClick={() => { router.visit(origin); }}>{t('actions.cancel')}</Button>
                <Button variant="outline" pending={save.pending} disabled={save.frozen} onClick={submit}>{t('composer.save_draft')}</Button>
                {can.issue && (
                    <Button disabled={draft === null || dirty} onClick={() => { issue.clear(); setConfirming(true); }}>
                        <Icon icon={Send} size="sm" />
                        {t('invoices.action.issue')}
                    </Button>
                )}
            </div>
            {(draft === null || dirty) && <p className="text-caption text-text-secondary">{t('invoices.composer.save_first')}</p>}
        </div>
    );

    return (
        <AppShell title={t('pages.pg_042.title')} back={{ href: origin, label: t('pages.pg_011.title') }} composer>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('invoices.title_draft', { nomor: project.number })} crumbs={[{ label: t('pages.pg_041.title'), href: '/keuangan/invoice' }]} back={{ label: t('dialogs.back'), href: origin }}
                    facts={<span className="text-body text-text-secondary">{project.company}</span>} />
                <DocumentComposer name={t('documents.doc_06')} paper={live} form={form} finalize={finalize} />
            </div>
            <ConfirmCommand
                open={confirming}
                onOpenChange={setConfirming}
                title={t('invoices.confirm.issue_title')}
                consequence={t('invoices.confirm.issue_consequence')}
                items={
                    <div className="flex min-w-0 flex-col gap-3">
                        <BusinessDateControl today={today} value={date} onChange={setDate} />
                        {issue.errors.tanggal !== undefined && <p className="text-danger">{issue.errors.tanggal}</p>}
                        <FormField label={t('invoices.confirm.layout')}>
                            {() => (
                                <RadioGroup value={layout} onValueChange={(value) => { setLayout(value as typeof layout); }} className="flex flex-wrap gap-4">
                                    <label className="flex items-center gap-2 text-body"><RadioGroupItem value="DOC-06" />{t('documents.doc_06')}</label>
                                    <label className="flex items-center gap-2 text-body"><RadioGroupItem value="DOC-04" />{t('documents.doc_04')}</label>
                                </RadioGroup>
                            )}
                        </FormField>
                    </div>
                }
                confirmLabel={t('invoices.action.issue')}
                pending={issue.pending}
                answer={issue.answer}
                onConfirm={() => {
                    if (draft === null) return;
                    issue.submit({ lock_version: draft.lock_version, tata: layout, tanggal: date.date, tanggal_otomatis: !date.chosen, isi: draft.facts }, () => { setConfirming(false); });
                }}
            />
            <UnsentInputDialog open={leaving !== null} kind="leave" onStay={() => { setLeaving(null); }} onLeave={() => { const go = leaving; departing.current = true; setLeaving(null); go?.(); }} />
        </AppShell>
    );
}
