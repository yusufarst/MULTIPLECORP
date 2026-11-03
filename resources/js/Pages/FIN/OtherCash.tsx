// PG-049 *Pengeluaran & Kas Lain* (ROUTE-049; NAV-049; SCR-45; WF-FIN-07; J-FIN-07; TASK-054): by branch, the disbursements — the only Cash-Out —, the expenses — cost, with `cost.view` — and the other Cash-In, newest first, never a total across companies (CS-08).
// "Catat Pengeluaran Kas" (INT-258), "Catat Beban" (INT-257) and "Catat Kas Masuk Lain" (INT-259), each present only with its capability (AZ-01); "Batalkan Catatan (Kontra)" through ROUTE-260 (INT-260; CM-38). No control edits or deletes a fact (BR-CR-02).
import { router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { DataList, ListContinuation } from '@/components/data-list';
import { BusinessDateControl, type BusinessDate } from '@/components/date-field';
import { DecimalInput, FormField, FormSection } from '@/components/form';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader, type MenuAction } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tabs, TabsList, TabsTab } from '@/components/ui/tabs';
import { Code, DateText, Money } from '@/components/values';
import { FinanceNav } from '@/features/fin/finance-nav';
import { Answered, type Payload } from '@/features/fin/invoice-receivable';
import { ProofSheet } from '@/features/fin/proof-sheet';
import AppShell from '@/layouts/AppShell';
import { money } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Tab = 'pengeluaran' | 'beban' | 'kas-masuk';
type Kind = 'disbursement' | 'expense' | 'cashin';
type Branch = { id: string; code: string; name: string; can: Record<Kind, boolean> };
type Row = {
    handle: string; company: string; amount: string; net: string; date: string; can: { contra: boolean };
    id?: string; kind?: string; account?: string; purchase?: string | null; purchase_number?: string | null; client?: string | null; expense?: string | null; cited?: string | null; cited_by?: string[]; proof?: string | null;
    category?: string; project?: string | null; project_number?: string | null; title?: string | null; bill?: string | null; bill_issuer?: string | null; paid?: boolean;
    income?: boolean; transfer_from?: string | null; cited_line?: boolean;
};
type Evidence = { id: string; type: string; label: string; uncited: string | null };
type Form = {
    accounts: { id: string; label: string }[]; proofs: Evidence[]; bills: Evidence[]; expense_proofs: Evidence[]; projects: { id: string; number: string; title: string }[]; purchases: { id: string; number: string; supplier: string; cancelled: boolean }[];
    expenses: { id: string; category: string; date: string; net: string }[]; tax_types: { code: string; name: string }[]; tax_proofs: Evidence[]; cases: { id: string; open: string }[];
    transfers: { id: string; company: string; date: string; left: string }[]; lines: { id: string; account: string; date: string; reference: string; uncited: string }[]; can: Record<Kind, boolean> & { upload: boolean };
};
type Props = { branches: Branch[]; branch: string | null; tabs: Tab[]; tab: Tab; rows: Row[]; more: string | null; after: string | null; cursor_failed: boolean; recorded: string | null; today: string; form?: Form | null };

const KINDS = ['PURCHASE_PAYMENT', 'EXPENSE_PAYMENT', 'TAX_REMITTANCE', 'INTERCOMPANY_TRANSFER_OUT'];
const CATEGORIES = ['CASHBACK_INCOME', 'OTHER_INCOME', 'SUPPLIER_REFUND', 'INTERCOMPANY_TRANSFER_IN', 'OTHER_NON_INCOME'];
const PROOF_TYPES = ['TRANSFER_PROOF', 'PAYMENT_PROOF', 'BANK_STATEMENT'];
const URLS: Record<Kind, string> = { disbursement: '/keuangan/kas-lain/pengeluaran', expense: '/keuangan/kas-lain/beban', cashin: '/keuangan/kas-lain/kas-masuk' };
const TITLES: Record<Kind, TranslationKey> = { disbursement: 'actions.record_cash_disbursement', expense: 'actions.record_expense', cashin: 'actions.record_other_cash_in' };

/** The chosen company's options, loaded once it is chosen and held across an answer: a redirect's page carries none (NV-04). */
function useForm(company: string, form: Form | null | undefined): Form | null {
    const [held, setHeld] = useState<Form | null>(null);

    useEffect(() => {
        if (company !== '') router.reload({ only: ['form'], data: { untuk: company } });
    }, [company]);

    if (form !== null && form !== undefined && form !== held) setHeld(form);

    return company === '' ? null : (form ?? held);
}

function Choice({ label, value, onChange, options, error, required = false, help }: { label: string; value: string; onChange: (value: string) => void; options: { value: string; label: string }[]; error?: string; required?: boolean; help?: string }) {
    const { t } = useTranslation();

    return (
        <FormField label={label} required={required} error={error} help={help}>
            {(control) => (
                <NativeSelect {...control} value={value} onChange={(event) => { onChange(event.target.value); }}>
                    <option value="">{t('deductions.form.choose')}</option>
                    {options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </NativeSelect>
            )}
        </FormField>
    );
}

function RecordSheet({ kind, branches, form: loaded, today, back, onClose }: { kind: Kind; branches: Branch[]; form: Form | null | undefined; today: string; back: string; onClose: () => void }) {
    const { t } = useTranslation();
    const allowed = branches.filter((each) => each.can[kind]);
    const [company, setCompany] = useState(allowed.length === 1 ? (allowed[0]?.id ?? '') : '');
    const form = useForm(company, loaded);
    const [type, setType] = useState(kind === 'disbursement' ? 'PURCHASE_PAYMENT' : kind === 'cashin' ? 'CASHBACK_INCOME' : '');
    const [amount, setAmount] = useState('');
    const [account, setAccount] = useState('');
    const [reference, setReference] = useState('');
    const [extra, setExtra] = useState('');
    const [line, setLine] = useState('');
    const [proof, setProof] = useState('');
    const [note, setNote] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [confirming, setConfirming] = useState(false);
    const [uploading, setUploading] = useState<{ types: string[]; target: 'proof' | 'reference' | 'extra' } | null>(null);
    const record = useCommand<Payload>(() => URLS[kind], 'post');
    const proofs = kind === 'expense' ? (form?.expense_proofs ?? []) : (form?.proofs ?? []);
    const evidence = (rows: Evidence[]) => rows.map((row) => ({ value: row.id, label: `${t(`evidence.type.${row.type}` as TranslationKey)} · ${row.label}${row.uncited === null ? '' : ` · ${t('deductions.form.room', { sisa: money(row.uncited) })}`}` }));
    const reset = (next: string) => { setType(next); setReference(''); setExtra(''); };
    const needsReference = (kind === 'disbursement' && type !== 'INTERCOMPANY_TRANSFER_OUT') || (kind === 'cashin' && (type === 'SUPPLIER_REFUND' || type === 'INTERCOMPANY_TRANSFER_IN'));
    const proven = proof !== '' || line !== '' || (kind === 'disbursement' && type === 'TAX_REMITTANCE') || (kind === 'expense' && extra !== '');
    const ready = company !== '' && amount !== '' && (kind === 'expense' ? type.trim().length >= 2 : account !== '') && (!needsReference || reference !== '' || extra !== '') && proven;
    const body = (): Payload => {
        const common = { perusahaan: company, jumlah: amount, bukti: proof === '' ? [] : [proof], tanggal: date.date, tanggal_otomatis: !date.chosen, kembali: back };

        if (kind === 'expense') return { ...common, kategori: type.trim(), proyek: reference === '' ? null : reference, tagihan: extra === '' ? null : extra };
        if (kind === 'disbursement') return { ...common, jenis: type, rekening: account, pembelian: type === 'PURCHASE_PAYMENT' ? reference : null, beban: type === 'EXPENSE_PAYMENT' ? reference : null, bukti_pajak: type === 'TAX_REMITTANCE' ? reference : null, jenis_pajak: type === 'TAX_REMITTANCE' ? extra : null };

        return { ...common, kategori: type, rekening: account, pembelian: type === 'SUPPLIER_REFUND' && reference !== '' ? reference : null, retur: type === 'SUPPLIER_REFUND' && extra !== '' ? extra : null, transfer: type === 'INTERCOMPANY_TRANSFER_IN' ? reference : null, mutasi: line === '' ? null : line, catatan: note === '' ? null : note };
    };

    return (
        <Sheet open onOpenChange={(open) => { if (!open) onClose(); }}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t(TITLES[kind])}</SheetTitle>
                    <SheetDescription>{t(`other_cash.form.${kind}_line` as TranslationKey)}</SheetDescription>
                </SheetHeader>
                <form className="flex min-w-0 flex-col gap-6" noValidate onSubmit={(event) => { event.preventDefault(); setConfirming(true); }}>
                    {allowed.length > 1 && <Choice label={t('receivables.field.branch')} required value={company} onChange={(value) => { setCompany(value); setAccount(''); setReference(''); setExtra(''); setLine(''); setProof(''); }} options={allowed.map((each) => ({ value: each.id, label: `${each.code} · ${each.name}` }))} error={record.errors.perusahaan} />}
                    <FormSection title={t('other_cash.form.what')}>
                        {kind === 'expense' ? (
                            <FormField label={t('other_cash.field.category')} required error={record.errors.kategori} help={t('other_cash.form.category_help')}>
                                {(control) => <Input {...control} value={type} maxLength={100} onChange={(event) => { setType(event.target.value); }} />}
                            </FormField>
                        ) : (
                            <Choice label={t(kind === 'disbursement' ? 'other_cash.field.kind' : 'other_cash.field.category')} required value={type} onChange={reset} options={(kind === 'disbursement' ? KINDS : CATEGORIES).map((each) => ({ value: each, label: t(`other_cash.${kind === 'disbursement' ? 'kind' : 'category'}.${each}` as TranslationKey) }))} error={record.errors[kind === 'disbursement' ? 'jenis' : 'kategori']} help={type === '' ? undefined : t(`other_cash.${kind === 'disbursement' ? 'kind' : 'category'}_help.${type}` as TranslationKey)} />
                        )}
                        <FormField label={t('pages.pg_046.amount')} required error={record.errors.jumlah}>
                            {(control) => <DecimalInput {...control} scale={2} value={amount} onValueChange={(value) => { setAmount(value ?? ''); }} />}
                        </FormField>
                        {kind !== 'expense' && <Choice label={t(kind === 'disbursement' ? 'other_cash.field.from_account' : 'other_cash.field.to_account')} required value={account} onChange={setAccount} options={(form?.accounts ?? []).map((each) => ({ value: each.id, label: each.label }))} error={record.errors.rekening} />}
                    </FormSection>
                    <FormSection title={t('other_cash.form.reference')}>
                        {kind === 'expense' && (
                            <>
                                <Choice label={t('other_cash.field.project')} value={reference} onChange={setReference} options={(form?.projects ?? []).map((each) => ({ value: each.id, label: `${each.number} · ${each.title}` }))} error={record.errors.proyek} help={t('other_cash.form.project_help')} />
                                <Choice label={t('other_cash.field.bill')} value={extra} onChange={setExtra} options={evidence(form?.bills ?? [])} error={record.errors.tagihan} help={t('other_cash.form.bill_help')} />
                                {form?.can.upload === true && <Button variant="outline" size="sm" className="self-start" onClick={() => { setUploading({ types: ['SUPPLIER_BILL'], target: 'extra' }); }}>{t('other_cash.action.upload_bill')}</Button>}
                            </>
                        )}
                        {kind === 'disbursement' && type === 'PURCHASE_PAYMENT' && <Choice label={t('other_cash.field.purchase')} required value={reference} onChange={setReference} options={(form?.purchases ?? []).filter((each) => !each.cancelled).map((each) => ({ value: each.id, label: each.supplier === '' ? each.number : `${each.number} · ${each.supplier}` }))} error={record.errors.pembelian} />}
                        {kind === 'disbursement' && type === 'EXPENSE_PAYMENT' && <Choice label={t('other_cash.field.expense')} required value={reference} onChange={setReference} options={(form?.expenses ?? []).map((each) => ({ value: each.id, label: `${each.category} · ${each.date} · ${money(each.net)}` }))} error={record.errors.beban} />}
                        {kind === 'disbursement' && type === 'TAX_REMITTANCE' && (
                            <>
                                <Choice label={t('other_cash.field.tax_type')} required value={extra} onChange={setExtra} options={(form?.tax_types ?? []).map((each) => ({ value: each.code, label: each.name }))} error={record.errors.jenis_pajak} />
                                <Choice label={t('evidence.type.TAX_PAYMENT_PROOF')} required value={reference} onChange={setReference} options={evidence(form?.tax_proofs ?? [])} error={record.errors.bukti_pajak} />
                                {form?.can.upload === true && <Button variant="outline" size="sm" className="self-start" onClick={() => { setUploading({ types: ['TAX_PAYMENT_PROOF'], target: 'reference' }); }}>{t('payments.action.upload_proof')}</Button>}
                            </>
                        )}
                        {kind === 'cashin' && type === 'SUPPLIER_REFUND' && (
                            <>
                                <Choice label={t('other_cash.field.purchase')} value={reference} onChange={setReference} options={(form?.purchases ?? []).map((each) => ({ value: each.id, label: each.supplier === '' ? each.number : `${each.number} · ${each.supplier}` }))} error={record.errors.pembelian} />
                                <Choice label={t('other_cash.field.return_case')} value={extra} onChange={setExtra} options={(form?.cases ?? []).map((each) => ({ value: each.id, label: t('other_cash.form.case_open', { sisa: money(each.open) }) }))} error={record.errors.retur} help={t('other_cash.form.refund_help')} />
                            </>
                        )}
                        {kind === 'cashin' && type === 'INTERCOMPANY_TRANSFER_IN' && <Choice label={t('other_cash.field.transfer')} required value={reference} onChange={setReference} options={(form?.transfers ?? []).map((each) => ({ value: each.id, label: `${each.company} · ${each.date} · ${t('other_cash.form.transfer_left', { sisa: money(each.left) })}` }))} error={record.errors.transfer} help={t('other_cash.form.transfer_help')} />}
                        {kind === 'disbursement' && type === 'INTERCOMPANY_TRANSFER_OUT' && <p className="text-body text-text-secondary">{t('other_cash.form.transfer_out_help')}</p>}
                    </FormSection>
                    <FormSection title={t('pages.pg_046.proof')}>
                        <Choice label={t('other_cash.field.proof')} required={kind !== 'expense' && type !== 'TAX_REMITTANCE' && line === ''} value={proof} onChange={setProof} options={evidence(proofs)} error={record.errors.bukti} />
                        {form?.can.upload === true && <Button variant="outline" size="sm" className="self-start" onClick={() => { setUploading({ types: kind === 'expense' ? [...PROOF_TYPES, 'OTHER'] : PROOF_TYPES, target: 'proof' }); }}>{t('payments.action.upload_proof')}</Button>}
                        {kind === 'cashin' && <Choice label={t('other_cash.field.line')} value={line} onChange={setLine} options={(form?.lines ?? []).filter((each) => account === '' || each.account === account).map((each) => ({ value: each.id, label: `${each.date} · ${each.reference} · ${t('deductions.form.room', { sisa: money(each.uncited) })}` }))} error={record.errors.mutasi} help={t('other_cash.form.line_help')} />}
                    </FormSection>
                    {kind === 'cashin' && (
                        <FormField label={t('other_cash.field.note')} error={record.errors.catatan}>
                            {(control) => <Input {...control} value={note} maxLength={500} onChange={(event) => { setNote(event.target.value); }} />}
                        </FormField>
                    )}
                    <BusinessDateControl today={today} value={date} onChange={setDate} />
                    {record.errors.tanggal !== undefined && <p className="text-body text-danger">{record.errors.tanggal}</p>}
                    <Answered command={record} />
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="outline" onClick={onClose}>{t('actions.cancel')}</Button>
                        <Button type="submit" pending={record.pending} disabled={record.frozen || !ready}>{t(TITLES[kind])}</Button>
                    </div>
                </form>
                <ConfirmCommand
                    open={confirming}
                    onOpenChange={setConfirming}
                    title={t(TITLES[kind])}
                    consequence={t(`other_cash.confirm.${kind}` as TranslationKey, { jumlah: money(amount === '' ? '0' : amount) })}
                    confirmLabel={t(TITLES[kind])}
                    pending={record.pending}
                    answer={record.answer}
                    onConfirm={() => { record.submit(body(), () => { setConfirming(false); onClose(); }); }}
                />
                {uploading !== null && <ProofSheet open onOpenChange={(open) => { if (!open) setUploading(null); }} company={company} types={uploading.types} proven={uploading.target !== 'proof'} today={today} back={back} onRegistered={(id) => { const target = uploading.target; setUploading(null); if (target === 'reference') setReference(id); else if (target === 'extra') setExtra(id); else setProof(id); router.reload({ only: ['form'], data: { untuk: company } }); }} />}
            </SheetContent>
        </Sheet>
    );
}

function ContraDialog({ row, tab, today, back, onClose }: { row: Row; tab: Tab; today: string; back: string; onClose: () => void }) {
    const { t } = useTranslation();
    const [reason, setReason] = useState('');
    const contra = useCommand<Payload>(() => '/keuangan/kontra', 'post');
    const kind = tab === 'beban' ? 'beban' : 'pengeluaran';

    return (
        <ConfirmCommand
            open
            onOpenChange={(open) => { if (!open) onClose(); }}
            title={t('other_cash.action.contra')}
            destructive
            consequence={t(`other_cash.confirm.contra_${kind}` as TranslationKey, { jumlah: money(row.net) })}
            reason={{ value: reason, onChange: setReason, error: contra.errors.alasan }}
            confirmLabel={t('other_cash.action.contra')}
            pending={contra.pending}
            answer={contra.answer}
            onConfirm={() => { contra.submit({ jenis: kind, catatan: kind === 'pengeluaran' ? (row.id ?? '') : row.handle, jumlah: row.net, tanggal: today, tanggal_otomatis: true, alasan: reason, kembali: back }, onClose); }}
        />
    );
}

export default function OtherCash({ branches, branch, tabs, tab, rows, more, after, cursor_failed: cursorFailed, recorded, today, form }: Props) {
    const { t } = useTranslation();
    const arrived = (usePage().flash as { answer?: { kind?: string; text?: string } }).answer;
    const [base, setBase] = useState(rows);
    const [shown, setShown] = useState(rows);
    const [next, setNext] = useState(more);
    const [appending, setAppending] = useState(false);
    const [recording, setRecording] = useState<Kind | null>(null);
    const [contraing, setContraing] = useState<Row | null>(null);

    if (base !== rows) {
        setBase(rows);
        setShown(appending ? [...shown, ...rows.filter((row) => !shown.some((known) => known.handle === row.handle))] : rows);
        setNext(more);
        setAppending(false);
    }

    const query = (change: Record<string, string | null>) => {
        const params = new URLSearchParams();

        for (const [key, value] of Object.entries({ perusahaan: branch, tab, ...change })) {
            if (value !== null && value !== '') params.set(key, value);
        }

        return `/keuangan/kas-lain?${params.toString()}`;
    };
    const visit = (change: Record<string, string | null>) => { router.get(query(change), {}, { preserveState: true }); };
    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        router.reload({ only: ['rows', 'more', 'after', 'cursor_failed'], data: { setelah: next } });
    };
    const back = query({ setelah: after });
    const many = branches.length > 1;
    const can = (kind: Kind) => branches.some((each) => each.can[kind]);
    const rowActions = (row: Row): MenuAction[] => (row.can.contra ? [{ label: t('other_cash.action.contra'), tone: 'danger' as const, onSelect: () => { setContraing(row); } }] : []);
    const marker = (code: string | null | undefined) => (code === '' ? t('company.other') : (code ?? ''));
    const describe = (row: Row): string => {
        if (tab === 'beban') return `${row.category ?? ''}${row.kind === 'MANUAL' ? '' : ` · ${t(`other_cash.origin.${row.kind ?? ''}` as TranslationKey)}`}`;
        if (tab === 'kas-masuk') return `${t(`other_cash.category.${row.category ?? ''}` as TranslationKey)} · ${t(row.income === true ? 'other_cash.income' : 'other_cash.non_income')}`;

        return t(`other_cash.kind.${row.kind ?? ''}` as TranslationKey);
    };
    const cites = (row: Row) => {
        if (tab === 'beban') return row.project === null || row.project === undefined ? t('other_cash.company_level') : <a href={`/proyek/${row.project}`} className="text-accent-link underline underline-offset-2"><Code>{row.project_number ?? ''}</Code></a>;
        if (row.purchase !== null && row.purchase !== undefined) return <a href={`/pembelian/${row.purchase}`} className="text-accent-link underline underline-offset-2"><Code>{row.purchase_number ?? ''}</Code></a>;
        if (row.transfer_from !== null && row.transfer_from !== undefined) return t('other_cash.transfer_from', { perusahaan: marker(row.transfer_from) });
        if ((row.cited_by ?? []).length > 0) return t('other_cash.cited_by', { perusahaan: (row.cited_by ?? []).map(marker).join(', ') });
        if (row.expense !== null && row.expense !== undefined) return row.expense;
        if (row.client !== null && row.client !== undefined) return row.client;

        return '—';
    };
    const state = (row: Row) => (row.net === row.amount ? t(tab === 'beban' && row.paid === true ? 'finance.paid' : 'other_cash.standing') : row.net === '0.00' ? t('other_cash.contraed') : t('other_cash.part_contraed', { sisa: money(row.net) }));

    return (
        <AppShell title={t('pages.pg_049.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_049.title')} />
                <FinanceNav current="other" />
                <p className="text-body text-text-secondary">{t('other_cash.intro')}</p>
                {arrived?.kind === 'COMMITTED' && arrived.text !== undefined && <OutcomeMessage answer={{ kind: 'COMMITTED', text: arrived.text }} />}
                <div className="flex flex-wrap gap-2">
                    {can('disbursement') && <Button size="sm" onClick={() => { setRecording('disbursement'); }}><Plus className="size-4" aria-hidden />{t('actions.record_cash_disbursement')}</Button>}
                    {can('expense') && <Button size="sm" variant="outline" onClick={() => { setRecording('expense'); }}><Plus className="size-4" aria-hidden />{t('actions.record_expense')}</Button>}
                    {can('cashin') && <Button size="sm" variant="outline" onClick={() => { setRecording('cashin'); }}><Plus className="size-4" aria-hidden />{t('actions.record_other_cash_in')}</Button>}
                </div>
                {many && (
                    <FormField label={t('receivables.field.branch')} className="min-w-0 sm:w-80">
                        {(control) => (
                            <NativeSelect {...control} value={branch ?? ''} onChange={(event) => { visit({ perusahaan: event.target.value }); }}>
                                <option value="">{t('receivables.field.all_branches')}</option>
                                {branches.map((option) => <option key={option.id} value={option.id}>{`${option.code} · ${option.name}`}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                )}
                {tabs.length > 1 && (
                    <Tabs value={tab} onValueChange={(value) => { visit({ tab: String(value) }); }}>
                        <TabsList>
                            {tabs.map((each) => <TabsTab key={each} value={each}>{t(`other_cash.tab.${each}` as TranslationKey)}</TabsTab>)}
                        </TabsList>
                    </Tabs>
                )}
                {cursorFailed && shown.length === 0 ? (
                    <ListContinuation state="cursor_failed" onLoadMore={loadMore} onReload={() => { visit({}); }} />
                ) : shown.length === 0 ? (
                    <RegionEmpty text={branches.length === 0 ? t('receivables.empty_scope') : t(`other_cash.empty.${tab}` as TranslationKey)} />
                ) : (
                    <>
                        <DataList<Row>
                            caption={t(`other_cash.tab.${tab}` as TranslationKey)}
                            rows={shown}
                            rowKey={(row) => row.handle}
                            rowHref={() => null}
                            rowActions={rowActions}
                            columns={[
                                { key: 'date', header: t('pages.pg_045.col_date'), cell: (row) => <span data-recorded={row.handle === recorded ? 'true' : undefined}><DateText value={row.date} /></span> },
                                ...(many ? [{ key: 'company', header: t('receivables.field.branch'), cell: (row: Row) => row.company }] : []),
                                { key: 'what', header: t(tab === 'pengeluaran' ? 'other_cash.field.kind' : 'other_cash.field.category'), cell: describe },
                                { key: 'cites', header: t('other_cash.field.cites'), cell: cites },
                                ...(tab === 'beban' ? [] : [{ key: 'account', header: t('other_cash.field.account'), priority: 'low' as const, cell: (row: Row) => row.account ?? '' }]),
                                { key: 'proof', header: t('pages.pg_046.proof'), priority: 'low', cell: (row) => { const doc = tab === 'beban' ? row.bill : row.proof; return doc === null || doc === undefined ? '—' : <a href={`/dokumen/bukti?bukti=${doc}`} className="text-accent-link underline underline-offset-2">{tab === 'beban' ? (row.bill_issuer ?? t('payments.detail.open_proof')) : t('payments.detail.open_proof')}</a>; } },
                                { key: 'state', header: t('invoices.field.state'), cell: state },
                                { key: 'amount', header: t('pages.pg_046.amount'), numeric: true, cell: (row) => <Money value={row.amount} /> },
                            ]}
                            dense={(row) => ({ title: describe(row), secondary: <DateText value={row.date} />, figure: <Money value={row.amount} />, status: state(row) })}
                        />
                        <ListContinuation state={cursorFailed ? 'cursor_failed' : next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={() => { visit({}); }} />
                    </>
                )}
            </div>
            {recording !== null && <RecordSheet key={recording} kind={recording} branches={branches} form={form} today={today} back={query({ tab: recording === 'expense' ? 'beban' : recording === 'cashin' ? 'kas-masuk' : 'pengeluaran', setelah: null })} onClose={() => { setRecording(null); }} />}
            {contraing !== null && <ContraDialog key={contraing.handle} row={contraing} tab={tab} today={today} back={back} onClose={() => { setContraing(null); }} />}
        </AppShell>
    );
}
