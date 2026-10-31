// PG-017 *Buat Pembelian* (ROUTE-017, ROUTE-171; NAV-017; SCR-17; J-PUR-01; J-PUR-02; J-MD-03; J-MD-05; SH-01; TASK-037): opened empty, prefilled with a project's shortage, a product from QS-17's advice, or from PG-016's "Tambahkan Pembelian Susulan". A project purchase takes the project's company, shown and not chosen (L-05); a replenishment chooses it (WF-PUR-02). "Simpan Pembelian" stays disabled with its reason beside it until a supplier and each line's quantity and price are given. The saved purchase records no cash or stock (BR-PUR-03).
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { ActionBar } from '@/components/action-bar';
import { type BusinessDate, BusinessDateControl } from '@/components/date-field';
import { FormField, FormSection, ValidationSummary } from '@/components/form';
import { type LookupResult } from '@/components/lookup';
import { PageHeader } from '@/components/page-header';
import { PartyLookup, SupplierCreateSheet } from '@/components/parties';
import { Disclosure, FactsList } from '@/components/record-blocks';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, Money } from '@/components/values';
import { ProofSheet } from '@/features/fin/proof-sheet';
import { type Bill, type Charge, blankCharge, chargePayload, ChargeFields } from '@/features/pur/charges';
import { blankLine, estimatedTotal, type Demand, type Line, linePayload, type Previous, type Product, PurchaseLines, type Tax, useLineProducts } from '@/features/pur/purchase-lines';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';
import { isBackground } from '@/lib/session-core';
import { useCommand } from '@/lib/use-command';

type Company = { id: string; code: string; name: string };
type Project = { id: string; number: string; title: string; state: string; company: string; company_code: string; company_name: string; demand: Demand[] };
type Prefill = { demand: string; product: Product; quantity: string; mode: 'WAREHOUSE' | 'DROPSHIP'; label: string };
type Props = {
    companies: Company[];
    company: string | null;
    project: Project | null;
    supplement: { number: string; supplier: { id: string; name: string } } | null;
    prefill: Prefill[];
    product: Product | null;
    taxes: Tax[];
    bills: Bill[];
    previous: Previous;
    today: string;
    can: { new_supplier: boolean; bill: boolean };
};
type Payload = Record<string, never>;

export default function RecordPurchase({ companies, company, project, supplement, prefill, product, taxes, bills, previous, today, can }: Props) {
    const { t } = useTranslation();
    const command = useCommand<Payload>(() => '/pembelian', 'post');
    const [chosenCompany, setChosenCompany] = useState(company ?? '');
    const [supplier, setSupplier] = useState<LookupResult | null>(supplement === null ? null : { id: supplement.supplier.id, code: '', label: supplement.supplier.name });
    const [creating, setCreating] = useState<{ key: number; name: string } | null>(null);
    const [reference, setReference] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [lines, setLines] = useState<Line[]>(() => [
        ...prefill.map((row, index): Line => ({ ...blankLine(`p-${String(index)}`), product: row.product, unit: row.product.units[0]?.code ?? '', quantity: row.quantity, demand: row.demand, direct: row.mode === 'DROPSHIP', description: row.label })),
        ...(product === null || prefill.length > 0 ? [] : [{ ...blankLine('q-0'), product, unit: product.units[0]?.code ?? '', description: product.name }]),
    ]);
    const [charges, setCharges] = useState<Charge[]>([]);
    const [uploading, setUploading] = useState<string | null>(null);
    const [dirty, setDirty] = useState(false);
    const [leaving, setLeaving] = useState<(() => void) | null>(null);
    const touch = () => { setDirty(true); };
    const origin = project !== null ? `/proyek/${project.id}` : '/pembelian';
    const chooseProduct = useLineProducts((update) => { setLines(update); touch(); });
    const errors = command.errors;
    const lineError = (index: number, field: string) => errors[`baris.${String(index)}.${field}`];
    const chargeLabels = lines.map((line, index) => ({ key: line.key, label: `${String(index + 1)}. ${line.product?.name ?? ''}` }));

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

    // SH-01: the previous prices of the supplier and the lines' products, read again when either changes.
    const priced = lines.flatMap((line) => (line.product === null ? [] : [line.product.id])).sort().join(',');
    useEffect(() => {
        if (supplier === null || priced === '') return;
        router.reload({ only: ['previous'], data: { pemasok: supplier.id, harga: priced, ...(project === null ? { perusahaan: chosenCompany } : {}) }, preserveUrl: true });
    }, [supplier?.id, priced, chosenCompany]); // eslint-disable-line react-hooks/exhaustive-deps

    const chooseCompany = (next: string) => {
        setChosenCompany(next);
        setCharges([]);
        router.reload({ only: ['company', 'taxes', 'bills', 'can'], data: { perusahaan: next }, preserveUrl: true });
    };
    const ready = supplier !== null && chosenCompany !== '' && lines.length > 0 && lines.every((line) => line.product !== null && line.quantity !== null && line.quantity !== '' && line.price !== null && line.price !== '');
    const summary = Object.entries(errors).map(([field, message]) => ({ id: `field-${field}`, label: field.startsWith('baris') ? t('purchase.lines') : field.startsWith('biaya') ? t('pages.pg_017.charge') : t('purchases.field.form'), message }));
    const submit = () => {
        const payload = {
            ...(project === null ? { perusahaan: chosenCompany } : { proyek: project.id }),
            pemasok: supplier?.id ?? '',
            referensi: reference === '' ? null : reference,
            tanggal: date.date,
            tanggal_otomatis: !date.chosen,
            baris: linePayload(lines),
            biaya: charges.map(chargePayload),
        };
        setDirty(false);
        (command.submit as unknown as (value: typeof payload) => void)(payload);
    };

    return (
        <AppShell title={t('pages.pg_017.title')} back={{ href: origin, label: project !== null ? project.number : t('pages.pg_015.title') }}>
            <div className="flex min-w-0 flex-col gap-6 pb-16">
                <PageHeader title={t('pages.pg_017.title')} crumbs={[{ label: t('pages.pg_015.title'), href: '/pembelian' }]} date={<BusinessDateControl today={today} value={date} onChange={(value) => { setDate(value); touch(); }} />} />
                {summary.length > 0 && <ValidationSummary fields={summary} />}
                <FormSection title={t('purchases.form.source')}>
                    {supplement !== null && <p className="text-body text-text-secondary">{t('purchases.form.supplement', { nomor: supplement.number })}</p>}
                    {project !== null ? (
                        <FactsList columns={2} facts={[
                            { label: t('purchases.field.project'), value: <span className="flex min-w-0 flex-wrap gap-2"><Code>{project.number}</Code>{project.title}</span> },
                            { label: t('purchases.field.company'), value: `${project.company_code} · ${project.company_name}` },
                        ]} />
                    ) : (
                        <FormField label={t('purchases.field.company')} required error={errors.perusahaan} help={t('purchases.form.replenishment')} className="min-w-0 sm:w-96">
                            {(control) => (
                                <NativeSelect {...control} value={chosenCompany} onChange={(event) => { chooseCompany(event.target.value); touch(); }}>
                                    {companies.map((option) => <option key={option.id} value={option.id}>{`${option.code} · ${option.name}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}
                    <div className="flex min-w-0 flex-wrap items-start gap-4">
                        <FormField label={t('purchases.field.supplier')} required error={errors.pemasok} className="min-w-0 sm:w-96">
                            {() => (
                                <PartyLookup type="supplier" label={t('purchases.field.supplier')} value={supplier} canCreate={can.new_supplier}
                                    onChoose={(next) => { setSupplier(next); touch(); }}
                                    onCreate={(name) => { setCreating({ key: Date.now(), name }); }} />
                            )}
                        </FormField>
                        <FormField label={t('pages.pg_017.supplier_quote')} error={errors.referensi} className="min-w-0 sm:w-72">
                            {(control) => <Input {...control} value={reference} maxLength={120} onChange={(event) => { setReference(event.target.value); touch(); }} />}
                        </FormField>
                    </div>
                </FormSection>
                <FormSection title={t('purchase.lines')}>
                    <PurchaseLines lines={lines} setLines={setLines} onTouch={touch} chooseProduct={chooseProduct} error={lineError} taxes={taxes} demand={project?.demand ?? []} previous={previous} nonStock={project !== null} />
                    {lines.length > 0 && (
                        <p className="flex items-baseline justify-end gap-3 text-body">
                            <span className="text-text-secondary">{t('purchases.form.estimate')}</span>
                            <Money value={estimatedTotal(lines)} className="font-semibold" />
                        </p>
                    )}
                </FormSection>
                <FormSection title={t('purchases.form.other')}>
                    <Disclosure title={t('pages.pg_017.charge')} defaultOpen={charges.length > 0}>
                        <div className="flex min-w-0 flex-col gap-3">
                            {charges.map((charge, index) => (
                                <ChargeFields key={charge.key} charge={charge} bills={bills} lines={chargeLabels}
                                    error={(field) => errors[`biaya.${String(index)}.${field}`]}
                                    onChange={(patch) => { setCharges(charges.map((other) => (other.key === charge.key ? { ...other, ...patch } : other))); touch(); }}
                                    onRemove={() => { setCharges(charges.filter((other) => other.key !== charge.key)); }}
                                    {...(can.bill ? { onUpload: () => { setUploading(charge.key); } } : {})} />
                            ))}
                            <Button variant="outline" className="self-start" onClick={() => { setCharges([...charges, blankCharge(`c-${String(Date.now())}`, '')]); touch(); }}>{t('purchases.charge.add')}</Button>
                        </div>
                    </Disclosure>
                </FormSection>
            </div>
            <ActionBar label={t('pages.pg_017.title')} answer={command.answer}>
                {!ready && <p className="min-w-0 text-caption text-text-secondary" id="save-reason">{t('pages.pg_017.save_reason')}</p>}
                <Button variant="outline" size="lg" onClick={() => { router.visit(origin); }}>{t('pages.pg_017.cancel')}</Button>
                <Button size="lg" disabled={!ready} pending={command.pending} pendingLabel={t('pages.pg_017.save')} aria-describedby={ready ? undefined : 'save-reason'} onClick={submit}>{t('pages.pg_017.save')}</Button>
            </ActionBar>
            {creating !== null && (
                <SupplierCreateSheet key={creating.key} open inline name={creating.name}
                    onOpenChange={(open) => { if (!open) setCreating(null); }}
                    onCreated={(value) => {
                        setCreating(null);

                        if (value !== null) {
                            setSupplier({ id: value.id, code: '', label: value.name });
                            touch();
                        }
                    }} />
            )}
            <ProofSheet open={uploading !== null} onOpenChange={(open) => { if (!open) setUploading(null); }} company={project?.company ?? chosenCompany} types={['SUPPLIER_BILL']} proven today={today} back={window.location.pathname + window.location.search}
                onRegistered={(id) => {
                    const key = uploading;
                    setUploading(null);
                    router.reload({ only: ['bills'], preserveUrl: true, onSuccess: () => { setCharges((current) => current.map((charge) => (charge.key === key ? { ...charge, bill: id } : charge))); } });
                }} />
            <UnsentInputDialog open={leaving !== null} kind="leave" onStay={() => { setLeaving(null); }}
                onLeave={() => { const go = leaving; setDirty(false); setLeaving(null); go?.(); }} />
        </AppShell>
    );
}
