// PG-047 *Potongan* (ROUTE-047; NAV-047; SCR-43; WF-FIN-04; J-FIN-04; TASK-052): by branch, the payments whose claimed deductions wait for evidence — claimed − evidenced (QS-13) — and the settlements with their payment, invoice, evidence and state; never a total across companies (CS-08).
// "Catat Potongan" with its confirmation and reason (INT-255), "Batalkan Potongan (Kontra)" (INT-260; CM-22), "Selesaikan Sisa Potongan" (INT-263). Without evidence no settlement exists, and the form has no field for an unexplained short payment. No control deletes a settlement (FS-02).
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

type Tab = 'semua' | 'sisa';
type Branch = { id: string; code: string; name: string };
type Row = { handle: string; company: string; client: string; class: string; type: string | null; direction: string | null; amount: string; state: string; date: string; payment: string; reference: string | null; invoice: string; number: string | null; evidence: string | null; issuer: string | null; can: { contra: boolean; resolve: boolean } };
type Waiting = { payment: string; company: string; client: string; date: string; reference: string | null; claimed: string; settled: string; waiting: string };
type Claim = { handle: string; class: string; type: string | null; fee_type: string | null; claimed: string };
type TargetInvoice = { id: string; number: string | null; project_number: string | null; outstanding: string };
type Evidence = { id: string; type: string; label: string; uncited: string };
type Candidate = { id: string; date: string; reference: string | null; room: string };
type Form = { claims: Claim[]; invoices: TargetInvoice[]; evidence: Evidence[]; payments: Candidate[]; can_upload: boolean };
type Props = { branches: Branch[]; branch: string | null; tab: Tab; rows: Row[]; more: string | null; after: string | null; cursor_failed: boolean; waiting: Waiting[]; recorded: string | null; today: string; opening: string | null; invoice: string | null; form?: Form | null };

const TABS: Tab[] = ['semua', 'sisa'];

const evidenceType = (claim: Claim | undefined, direction: string) => (claim?.class === 'FEE' ? 'FEE_ADVICE' : direction === 'COLLECTED' ? 'TAX_COLLECTION_PROOF' : 'TAX_WITHHOLDING_PROOF');

/** The form's options, loaded once the sheet opens and held across an answer: a redirect's page carries none (NV-04). */
function useForm(payment: string, form: Form | null | undefined): Form | null {
    const [held, setHeld] = useState<Form | null>(null);

    useEffect(() => {
        router.reload({ only: ['form'], data: { untuk: payment } });
    }, [payment]);

    if (form !== null && form !== undefined && form !== held) setHeld(form);

    return form ?? held;
}

function RecordSheet({ payment, form: loaded, invoice, today, back, branchOf, onClose }: { payment: Waiting; form: Form | null | undefined; invoice: string | null; today: string; back: string; branchOf: (code: string) => string | null; onClose: () => void }) {
    const { t } = useTranslation();
    const form = useForm(payment.payment, loaded);
    const [claimKey, setClaimKey] = useState('');
    const [direction, setDirection] = useState('WITHHELD');
    const [picked, setPicked] = useState(invoice ?? '');
    const [amount, setAmount] = useState('');
    const [evidence, setEvidence] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [reason, setReason] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [uploading, setUploading] = useState(false);
    const record = useCommand<Payload>(() => '/keuangan/potongan', 'post');
    const claims = form?.claims ?? [];
    // A seeded invoice stands only if it is this client's.
    const target = (form?.invoices ?? []).some((row) => row.id === picked) ? picked : '';
    const claim = claims.find((row) => row.handle === claimKey);
    const type = evidenceType(claim, direction);
    const proofs = (form?.evidence ?? []).filter((row) => row.type === type);
    const company = branchOf(payment.company);
    const ready = claim !== undefined && target !== '' && amount !== '' && evidence !== '';

    return (
        <Sheet open onOpenChange={(open) => { if (!open) onClose(); }}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t('actions.record_deduction')}</SheetTitle>
                    <SheetDescription>{t('deductions.form.line', { klien: payment.client, jumlah: money(payment.waiting) })}</SheetDescription>
                </SheetHeader>
                <form className="flex min-w-0 flex-col gap-6" noValidate onSubmit={(event) => { event.preventDefault(); setConfirming(true); }}>
                    <p className="text-body text-text-secondary">{t('deductions.form.no_evidence')}</p>
                    <FormSection title={t('deductions.form.deduction')}>
                        <FormField label={t('payments.claim.class')} required error={record.errors.klaim}>
                            {(control) => (
                                <NativeSelect {...control} value={claimKey} onChange={(event) => { setClaimKey(event.target.value); setEvidence(''); }}>
                                    <option value="">{t('deductions.form.choose')}</option>
                                    {claims.map((row) => <option key={row.handle} value={row.handle}>{`${t(`payments.claim.${row.class}` as TranslationKey)} · ${row.fee_type ?? row.type ?? ''} · ${money(row.claimed)}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        {claim?.class === 'TAX' && (
                            <FormField label={t('deductions.form.direction')} required error={record.errors.arah}>
                                {(control) => (
                                    <NativeSelect {...control} value={direction} onChange={(event) => { setDirection(event.target.value); setEvidence(''); }}>
                                        <option value="WITHHELD">{t('deductions.direction.WITHHELD')}</option>
                                        <option value="COLLECTED">{t('deductions.direction.COLLECTED')}</option>
                                    </NativeSelect>
                                )}
                            </FormField>
                        )}
                        <FormField label={t('pages.pg_041.title')} required error={record.errors.invoice}>
                            {(control) => (
                                <NativeSelect {...control} value={target} onChange={(event) => { setPicked(event.target.value); }}>
                                    <option value="">{t('deductions.form.choose')}</option>
                                    {(form?.invoices ?? []).map((row) => <option key={row.id} value={row.id}>{`${row.number ?? t('payments.invoice.unnumbered')} · ${t('deductions.form.outstanding', { sisa: money(row.outstanding) })}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        <FormField label={t('pages.pg_046.amount')} required error={record.errors.jumlah}>
                            {(control) => <DecimalInput {...control} scale={2} value={amount} onValueChange={(value) => { setAmount(value ?? ''); }} />}
                        </FormField>
                    </FormSection>
                    <FormSection title={t('pages.pg_046.proof')}>
                        <FormField label={t(`evidence.type.${type}` as TranslationKey)} required error={record.errors.bukti}>
                            {(control) => (
                                <NativeSelect {...control} value={evidence} onChange={(event) => { setEvidence(event.target.value); }}>
                                    <option value="">{t('deductions.form.choose')}</option>
                                    {proofs.map((row) => <option key={row.id} value={row.id}>{`${row.label} · ${t('deductions.form.room', { sisa: money(row.uncited) })}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        {form?.can_upload === true && company !== null && claim !== undefined && <Button variant="outline" size="sm" className="self-start" onClick={() => { setUploading(true); }}>{t('payments.action.upload_proof')}</Button>}
                    </FormSection>
                    <BusinessDateControl today={today} value={date} onChange={setDate} />
                    {record.errors.tanggal !== undefined && <p className="text-body text-danger">{record.errors.tanggal}</p>}
                    <Answered command={record} />
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="outline" onClick={onClose}>{t('actions.cancel')}</Button>
                        <Button type="submit" pending={record.pending} disabled={record.frozen || !ready}>{t('actions.record_deduction')}</Button>
                    </div>
                </form>
                <ConfirmCommand
                    open={confirming}
                    onOpenChange={setConfirming}
                    title={t('actions.record_deduction')}
                    consequence={t('deductions.confirm.record', { jumlah: money(amount === '' ? '0' : amount) })}
                    reason={{ value: reason, onChange: setReason, error: record.errors.alasan }}
                    confirmLabel={t('actions.record_deduction')}
                    pending={record.pending}
                    answer={record.answer}
                    onConfirm={() => {
                        record.submit({ pembayaran: payment.payment, klaim: claimKey, arah: claim?.class === 'TAX' ? direction : null, invoice: target, jumlah: amount, bukti: evidence, tanggal: date.date, tanggal_otomatis: !date.chosen, alasan: reason, kembali: back }, () => { setConfirming(false); onClose(); });
                    }}
                />
                {uploading && company !== null && <ProofSheet open onOpenChange={setUploading} company={company} types={[type]} proven today={today} back={back} onRegistered={(id) => { setUploading(false); setEvidence(id); router.reload({ only: ['form'], data: { untuk: payment.payment } }); }} />}
            </SheetContent>
        </Sheet>
    );
}

function ContraDialog({ row, today, back, onClose }: { row: Row; today: string; back: string; onClose: () => void }) {
    const { t } = useTranslation();
    const [reason, setReason] = useState('');
    const contra = useCommand<Payload>(() => '/keuangan/kontra', 'post');

    return (
        <ConfirmCommand
            open
            onOpenChange={(open) => { if (!open) onClose(); }}
            title={t('deductions.action.contra')}
            destructive
            consequence={t('deductions.confirm.contra', { jumlah: money(row.amount) })}
            reason={{ value: reason, onChange: setReason, error: contra.errors.alasan }}
            confirmLabel={t('deductions.action.contra')}
            pending={contra.pending}
            answer={contra.answer}
            onConfirm={() => { contra.submit({ jenis: 'potongan', catatan: row.handle, tanggal: today, tanggal_otomatis: true, alasan: reason, kembali: back }, onClose); }}
        />
    );
}

function ResolveSheet({ row, form: loaded, today, back, branchOf, onClose }: { row: Row; form: Form | null | undefined; today: string; back: string; branchOf: (code: string) => string | null; onClose: () => void }) {
    const { t } = useTranslation();
    const form = useForm(row.payment, loaded);
    const [choice, setChoice] = useState<'catat_ulang' | 'kontra'>('catat_ulang');
    const [target, setTarget] = useState('');
    const [picked, setPicked] = useState(row.payment);
    const [evidence, setEvidence] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [reason, setReason] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [uploading, setUploading] = useState(false);
    const candidates = form?.payments ?? [];
    // Its own payment only while it can still take it: a fully contra'd one holds none (C-45).
    const paying = candidates.some((each) => each.id === picked) ? picked : '';
    const resolve = useCommand<Payload>(() => `/keuangan/potongan/${row.handle}/selesaikan-sisa`, 'post');
    const company = branchOf(row.company);
    const type = row.class === 'FEE' ? 'FEE_ADVICE' : row.direction === 'COLLECTED' ? 'TAX_COLLECTION_PROOF' : 'TAX_WITHHOLDING_PROOF';
    const ready = choice === 'catat_ulang' ? target !== '' && paying !== '' : evidence !== '';

    return (
        <Sheet open onOpenChange={(open) => { if (!open) onClose(); }}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t('actions.resolve_residual_settlement')}</SheetTitle>
                    <SheetDescription>{t('deductions.resolve.line', { jumlah: money(row.amount) })}</SheetDescription>
                </SheetHeader>
                <form className="flex min-w-0 flex-col gap-6" noValidate onSubmit={(event) => { event.preventDefault(); setConfirming(true); }}>
                    <FormField label={t('deductions.resolve.choice')} required>
                        {(control) => (
                            <NativeSelect {...control} value={choice} onChange={(event) => { setChoice(event.target.value === 'kontra' ? 'kontra' : 'catat_ulang'); }}>
                                <option value="catat_ulang">{t('deductions.resolve.rerecord')}</option>
                                <option value="kontra">{t('deductions.resolve.contra')}</option>
                            </NativeSelect>
                        )}
                    </FormField>
                    {choice === 'catat_ulang' && (
                        <FormField label={t('deductions.resolve.payment')} required error={resolve.errors.pembayaran} help={candidates.length === 0 ? t('deductions.resolve.no_payment') : undefined}>
                            {(control) => (
                                <NativeSelect {...control} value={paying} onChange={(event) => { setPicked(event.target.value); }}>
                                    <option value="">{t('deductions.form.choose')}</option>
                                    {candidates.map((each) => <option key={each.id} value={each.id}>{`${each.id === row.payment ? t('deductions.resolve.same_payment') : (each.reference ?? each.date)} · ${t('deductions.form.claim_room', { sisa: money(each.room) })}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}
                    {choice === 'catat_ulang' ? (
                        <FormField label={t('pages.pg_041.title')} required error={resolve.errors.invoice}>
                            {(control) => (
                                <NativeSelect {...control} value={target} onChange={(event) => { setTarget(event.target.value); }}>
                                    <option value="">{t('deductions.form.choose')}</option>
                                    {(form?.invoices ?? []).map((invoice) => <option key={invoice.id} value={invoice.id}>{`${invoice.number ?? t('payments.invoice.unnumbered')} · ${t('deductions.form.outstanding', { sisa: money(invoice.outstanding) })}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                    ) : (
                        <FormSection title={t('pages.pg_046.proof')}>
                            <FormField label={t('deductions.resolve.issuer_evidence')} required error={resolve.errors.bukti}>
                                {(control) => (
                                    <NativeSelect {...control} value={evidence} onChange={(event) => { setEvidence(event.target.value); }}>
                                        <option value="">{t('deductions.form.choose')}</option>
                                        {(form?.evidence ?? []).filter((proof) => proof.id !== row.evidence).map((proof) => <option key={proof.id} value={proof.id}>{proof.label}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                            {form?.can_upload === true && company !== null && <Button variant="outline" size="sm" className="self-start" onClick={() => { setUploading(true); }}>{t('payments.action.upload_proof')}</Button>}
                        </FormSection>
                    )}
                    <BusinessDateControl today={today} value={date} onChange={setDate} />
                    {resolve.errors.tanggal !== undefined && <p className="text-body text-danger">{resolve.errors.tanggal}</p>}
                    <Answered command={resolve} />
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="outline" onClick={onClose}>{t('actions.cancel')}</Button>
                        <Button type="submit" pending={resolve.pending} disabled={resolve.frozen || !ready}>{t('actions.resolve_residual_settlement')}</Button>
                    </div>
                </form>
                <ConfirmCommand
                    open={confirming}
                    onOpenChange={setConfirming}
                    title={t('actions.resolve_residual_settlement')}
                    consequence={t('deductions.confirm.resolve', { jumlah: money(row.amount) })}
                    reason={{ value: reason, onChange: setReason, error: resolve.errors.alasan }}
                    confirmLabel={t('actions.resolve_residual_settlement')}
                    pending={resolve.pending}
                    answer={resolve.answer}
                    onConfirm={() => {
                        resolve.submit({ pilihan: choice, invoice: choice === 'catat_ulang' ? target : null, pembayaran: choice === 'catat_ulang' ? paying : null, bukti: choice === 'kontra' ? evidence : null, tanggal: date.date, tanggal_otomatis: !date.chosen, alasan: reason, kembali: back }, () => { setConfirming(false); onClose(); });
                    }}
                />
                {uploading && company !== null && <ProofSheet open onOpenChange={setUploading} company={company} types={[type]} proven today={today} back={back} onRegistered={(id) => { setUploading(false); setEvidence(id); router.reload({ only: ['form'], data: { untuk: row.payment } }); }} />}
            </SheetContent>
        </Sheet>
    );
}

export default function Deductions({ branches, branch, tab, rows, more, after, cursor_failed: cursorFailed, waiting, today, opening, invoice, form }: Props) {
    const { t } = useTranslation();
    const arrived = (usePage().flash as { answer?: { kind?: string; text?: string } }).answer;
    const [base, setBase] = useState(rows);
    const [shown, setShown] = useState(rows);
    const [next, setNext] = useState(more);
    const [appending, setAppending] = useState(false);
    const [paying, setSettling] = useState<Waiting | null>(waiting.find((row) => row.payment === opening) ?? null);
    const [contraing, setContraing] = useState<Row | null>(null);
    const [resolving, setResolving] = useState<Row | null>(null);

    if (base !== rows) {
        setBase(rows);
        setShown(appending ? [...shown, ...rows.filter((row) => !shown.some((known) => known.handle === row.handle))] : rows);
        setNext(more);
        setAppending(false);
    }

    const query = (change: Record<string, string | null>) => {
        const params = new URLSearchParams();

        for (const [key, value] of Object.entries({ perusahaan: branch, tab: tab === 'semua' ? null : tab, ...change })) {
            if (value !== null && value !== '') params.set(key, value);
        }

        const text = params.toString();

        return `/keuangan/potongan${text === '' ? '' : `?${text}`}`;
    };
    const visit = (change: Record<string, string | null>) => { router.get(query(change), {}, { preserveState: true }); };
    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        // The cursor stays in the address and comes back with reload (NV-05).
        router.reload({ only: ['rows', 'more', 'after', 'cursor_failed'], data: { setelah: next } });
    };
    const back = query({ setelah: after });
    const branchOf = (code: string) => branches.find((option) => option.code === code)?.id ?? null;
    const rowActions = (row: Row): MenuAction[] => [
        ...(row.can.resolve ? [{ label: t('actions.resolve_residual_settlement'), onSelect: () => { setResolving(row); } }] : []),
        ...(row.can.contra ? [{ label: t('deductions.action.contra'), tone: 'danger' as const, onSelect: () => { setContraing(row); } }] : []),
    ];
    const typeOf = (row: Row) => `${t(`payments.claim.${row.class}` as TranslationKey)}${row.type === null ? '' : ` · ${row.type}`}${row.direction === null ? '' : ` · ${t(`deductions.direction.${row.direction}` as TranslationKey)}`}`;

    return (
        <AppShell title={t('pages.pg_047.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_047.title')} />
                <FinanceNav current="deductions" />
                {arrived?.kind === 'COMMITTED' && arrived.text !== undefined && <OutcomeMessage answer={{ kind: 'COMMITTED', text: arrived.text }} />}
                {branches.length > 1 && (
                    <FormField label={t('receivables.field.branch')} className="min-w-0 sm:w-80">
                        {(control) => (
                            <NativeSelect {...control} value={branch ?? ''} onChange={(event) => { visit({ perusahaan: event.target.value }); }}>
                                <option value="">{t('receivables.field.all_branches')}</option>
                                {branches.map((option) => <option key={option.id} value={option.id}>{`${option.code} · ${option.name}`}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                )}
                <section aria-label={t('deductions.waiting.title')} className="flex min-w-0 flex-col gap-2">
                    <h2 className="text-heading-sm font-semibold text-text-primary">{t('deductions.waiting.title')}</h2>
                    {waiting.length === 0 ? <p className="text-body text-text-secondary">{t('signals.qs_13_empty')}</p> : (
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {waiting.map((row) => (
                                <li key={row.payment} data-testid="deduction-waiting" className="flex min-w-0 flex-wrap items-center justify-between gap-3 py-2">
                                    <div className="flex min-w-0 flex-col">
                                        <span className="text-body text-text-primary">{`${row.client}${branches.length > 1 ? ` · ${row.company}` : ''}`}</span>
                                        <span className="text-caption text-text-secondary"><DateText value={row.date} />{row.reference === null ? '' : ` · ${row.reference}`}{` · ${t('deductions.waiting.figures', { klaim: money(row.claimed), bukti: money(row.settled) })}`}</span>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-3">
                                        <Money value={row.waiting} />
                                        <Button size="sm" onClick={() => { setSettling(row); }}><Plus className="size-4" aria-hidden />{t('actions.record_deduction')}</Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
                <Tabs value={tab} onValueChange={(value) => { visit({ tab: String(value) }); }}>
                    <TabsList>
                        {TABS.map((each) => <TabsTab key={each} value={each}>{t(`deductions.tab.${each}` as TranslationKey)}</TabsTab>)}
                    </TabsList>
                </Tabs>
                {cursorFailed && shown.length === 0 ? (
                    <ListContinuation state="cursor_failed" onLoadMore={loadMore} onReload={() => { visit({}); }} />
                ) : shown.length === 0 ? (
                    <RegionEmpty text={branches.length === 0 ? t('receivables.empty_scope') : t(`deductions.empty.${tab}` as TranslationKey)} />
                ) : (
                    <>
                        <DataList<Row>
                            caption={t('pages.pg_047.title')}
                            rows={shown}
                            rowKey={(row) => row.handle}
                            rowHref={() => null}
                            rowActions={rowActions}
                            columns={[
                                { key: 'date', header: t('pages.pg_045.col_date'), cell: (row) => <DateText value={row.date} /> },
                                ...(branches.length > 1 ? [{ key: 'company', header: t('receivables.field.branch'), cell: (row: Row) => row.company }] : []),
                                { key: 'client', header: t('invoices.field.client'), cell: (row) => row.client },
                                { key: 'type', header: t('payments.claim.class'), cell: typeOf },
                                { key: 'invoice', header: t('pages.pg_041.title'), cell: (row) => <a href={`/keuangan/invoice/${row.invoice}`} className="text-accent-link underline underline-offset-2"><Code>{row.number ?? t('payments.invoice.unnumbered')}</Code></a> },
                                { key: 'evidence', header: t('pages.pg_046.proof'), priority: 'low', cell: (row) => row.evidence === null ? '—' : <a href={`/dokumen/bukti?bukti=${row.evidence}`} className="text-accent-link underline underline-offset-2">{row.issuer ?? t('payments.detail.open_proof')}</a> },
                                { key: 'state', header: t('invoices.field.state'), cell: (row) => t(`deductions.state.${row.state}` as TranslationKey) },
                                { key: 'amount', header: t('pages.pg_046.amount'), numeric: true, cell: (row) => <Money value={row.amount} /> },
                            ]}
                            dense={(row) => ({ title: typeOf(row), secondary: <DateText value={row.date} />, figure: <Money value={row.amount} />, status: t(`deductions.state.${row.state}` as TranslationKey) })}
                        />
                        <ListContinuation state={cursorFailed ? 'cursor_failed' : next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={() => { visit({}); }} />
                    </>
                )}
            </div>
            {paying !== null && <RecordSheet key={paying.payment} payment={paying} form={form} invoice={invoice} today={today} back={query({ pembayaran: paying.payment })} branchOf={branchOf} onClose={() => { setSettling(null); }} />}
            {contraing !== null && <ContraDialog key={contraing.handle} row={contraing} today={today} back={back} onClose={() => { setContraing(null); }} />}
            {resolving !== null && <ResolveSheet key={resolving.handle} row={resolving} form={form} today={today} back={back} branchOf={branchOf} onClose={() => { setResolving(null); }} />}
        </AppShell>
    );
}
