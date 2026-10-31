// PG-043 *Detail Invoice* (ROUTE-043, ROUTE-245, ROUTE-249, ROUTE-250; NAV-043; SCR-39; J-FIN-01; J-FIN-08; TASK-049): its lines and tax, the position block keeping sales value, billed sum, applied, settled and outstanding apart (PT-24;
// CALC-05; CALC-08), the billing act, the payments and deductions, its documents and its pre-payment Kuitansi. "Terbitkan Kuitansi untuk Proses Pembayaran" takes the amount, then PG-036 shows its draft and issues it with its reason
// (`DIR-081` OB-02); it records no payment. "Revisi Turun" and "Batalkan Invoice" list every reduction and what becomes of it (ST-07), each with its typed basis and reason (IP-09). The billing, dispute and write-off controls live in `invoice-receivable` (TASK-050).
import type { FormDataConvertible } from '@inertiajs/core';
import { FileText, Link as LinkIcon } from 'lucide-react';
import { useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { BusinessDateControl, type BusinessDate } from '@/components/date-field';
import { DecimalInput, FormField, FormSection } from '@/components/form';
import { OutcomeMessage, type Answer } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateText, Money } from '@/components/values';
import { type InvoiceState } from '@/features/fin/invoice';
import { type Billing, type Hold, type Recovery, type WriteoffRow, useInvoiceReceivable } from '@/features/fin/invoice-receivable';
import AppShell from '@/layouts/AppShell';
import { dateLong, money, quantity as formatQuantity } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Option = { handle: string; label: string };
type Application = { handle: string; amount: string; date: string; payment: string | null };
type Settlement = { handle: string; amount: string; date: string; class: string; direction: string | null };
type Writeoff = { handle: string; amount: string; date: string };
type Kuitansi = { id: string; state: string; number: string | null; amount: string | null; linked: boolean; cause: string | null };
type Props = {
    invoice: { id: string; number: string | null; state: InvoiceState; lock_version: number; version_no: number; kind: string; date: string; company: string; client: string; project: string | null; project_number: string | null; title: string | null; void_basis: string | null; void_reason: string | null; replaces: string | null; replaced_by: string | null; billed_at: string | null; due_date: string | null; inherited: boolean } | null;
    position: { sales: string; tax: string; gross: string; applied: string; fee_settled: string; tax_settled: string; written_off: string; outstanding: string; billed: boolean } | null;
    lines: { description: string; quantity: string | null; unit: string | null; price: string | null; value: string }[];
    taxes: { code: string; rate: string; base: string; amount: string }[];
    reductions: { applications: Application[]; settlements: Settlement[]; writeoffs: Writeoff[] };
    documents: { id: string; type: string; state: string; number: string | null }[];
    kuitansi: Kuitansi[];
    payments: { id: string; amount: string; date: string }[];
    bases: { cases: Option[]; confirmations: Option[]; cancellations: Option[]; transitions: Option[] };
    evidence: { id: string; type: string; issuer: string; reference: string | null; date: string }[];
    today: string;
    billing: Billing;
    hold: Hold | null;
    writeoffs: WriteoffRow[];
    credit: string | null;
    recoveries: Recovery[];
    can: { correct: boolean; supersede: boolean; settle: boolean; kuitansi: boolean; link: boolean; nota: boolean; bill: boolean; correct_billing: boolean; dispute: boolean; write_off: boolean; recover: boolean; pay: boolean; request: boolean };
};
type Correcting = 'REVISI' | 'BATAL';
type Payload = Record<string, FormDataConvertible>;

function Answered({ command }: { command: { answer: Answer | null; unknown: boolean; retry: () => void } }) {
    const { t } = useTranslation();

    return command.answer === null ? null : (
        <OutcomeMessage answer={command.answer} action={command.unknown ? <Button variant="outline" size="sm" onClick={command.retry}>{t('actions.try_again')}</Button> : undefined} />
    );
}

function PositionRow({ label, value, strong = false }: { label: string; value: string; strong?: boolean }) {
    return (
        <div className="flex min-w-0 items-baseline justify-between gap-4">
            <dt className={strong ? 'font-semibold text-text-primary' : 'text-text-secondary'}>{label}</dt>
            <dd className={strong ? 'font-semibold text-text-primary' : 'text-text-primary'}><Money value={value} /></dd>
        </div>
    );
}

export default function Invoice({ invoice, position, lines, taxes, reductions, documents, kuitansi, payments, bases, evidence, billing, hold, writeoffs, credit, recoveries, today, can }: Props) {
    const { t } = useTranslation();
    const id = invoice?.id ?? '';
    const [correcting, setCorrecting] = useState<Correcting | null>(null);
    const [enteredFor, setEnteredFor] = useState<Correcting | null>(null);
    const [reason, setReason] = useState('');
    const [basis, setBasis] = useState('');
    const [basisRow, setBasisRow] = useState('');
    const [cited, setCited] = useState('');
    const [value, setValue] = useState('');
    const [description, setDescription] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [actions, setActions] = useState<Record<string, string>>({});
    const [amount, setAmount] = useState('');
    const [preparing, setPreparing] = useState(false);
    const [linking, setLinking] = useState<Kuitansi | null>(null);
    const [picked, setPicked] = useState<string[]>([]);
    const correct = useCommand<Payload>(() => `/keuangan/invoice/${id}/koreksi`, 'post');
    const prepare = useCommand<Payload>(() => '/dokumen', 'post');
    const other = useCommand<Payload>(() => '/dokumen', 'post');
    const link = useCommand<Payload>(() => `/keuangan/kuitansi/${linking?.id ?? ''}/tautkan`, 'post');
    const receivable = useInvoiceReceivable({ id, outstanding: position?.outstanding ?? '0', billing, hold, inherited: invoice?.inherited ?? false, unbilled: invoice?.state === 'ISSUED_NOT_BILLED', writeoffs, credit, recoveries, evidence, today, can });

    if (invoice === null || position === null) {
        return null;
    }

    const billed = position.billed;
    const replacing = basis === 'ENTRY_ERROR_REPLACED';
    const voiding = correcting === 'BATAL';
    const basisOptions: [string, TranslationKey][] = voiding
        ? [...(billed ? [] : [['UNBILLED_ENTRY_ERROR', 'invoices.correct.basis_unbilled'] as [string, TranslationKey]]), ['ENTRY_ERROR_REPLACED', 'invoices.correct.basis_replaced'], ['RETURN', 'invoices.correct.basis_return'], ['RECONFIRMATION', 'invoices.correct.basis_reconfirmation'], ['CANCELLATION', 'invoices.correct.basis_cancellation']]
        : [['ENTRY_ERROR', 'invoices.correct.basis_entry'], ['RETURN', 'invoices.correct.basis_return'], ['RECONFIRMATION', 'invoices.correct.basis_reconfirmation'], ['CANCELLATION', 'invoices.correct.basis_cancellation']];
    const rowOptions = basis === 'RETURN' ? bases.cases : basis === 'RECONFIRMATION' ? bases.confirmations : basis === 'CANCELLATION' ? [...bases.cancellations, ...bases.transitions] : [];
    const needsEvidence = basis === 'ENTRY_ERROR' || basis === 'ENTRY_ERROR_REPLACED';
    const needsValue = !voiding || replacing;
    const applicationChoices: [string, TranslationKey][] = voiding ? (replacing ? [['RETARGET', 'invoices.correct.retarget'], ['RELEASE', 'invoices.correct.release']] : [['RELEASE', 'invoices.correct.release']]) : [['KEEP', 'invoices.correct.keep'], ['RELEASE', 'invoices.correct.release']];
    const settlementChoices: [string, TranslationKey][] = voiding ? (replacing ? [['RERECORD', 'invoices.correct.rerecord'], ['RESIDUAL', 'invoices.correct.residual']] : [['RESIDUAL', 'invoices.correct.residual']]) : [['KEEP', 'invoices.correct.keep'], ['RESIDUAL', 'invoices.correct.residual']];
    const writeoffChoices: [string, TranslationKey][] = replacing ? [['SUPERSEDE', 'invoices.correct.supersede'], ['RERECORD', 'invoices.correct.rerecord_writeoff']] : [['SUPERSEDE', 'invoices.correct.supersede']];
    const open = (kind: Correcting) => {
        correct.clear();

        // Reopening keeps what was entered (IP-08; IP-09); the other correction, or a committed one, starts anew.
        if (enteredFor !== kind) {
            setReason('');
            setBasis('');
            setBasisRow('');
            setCited('');
            setValue('');
            setDescription(invoice.title ?? '');
            setDate({ date: today, chosen: false });
            setActions({});
            setEnteredFor(kind);
        }

        setCorrecting(kind);
    };
    const chosenAction = (handle: string, choices: [string, TranslationKey][]): string => actions[handle] ?? choices[0]?.[0] ?? '';
    const submitCorrection = () => {
        const body: Payload = {
            aksi: correcting,
            lock_version: invoice.lock_version,
            alasan: reason,
            dasar: basis,
            tanggal: date.date,
            tanggal_otomatis: !date.chosen,
            ...(cited === '' ? {} : { bukti: cited }),
            ...(basisRow === '' ? {} : basis === 'RETURN' ? { kasus: basisRow } : basis === 'RECONFIRMATION' ? { konfirmasi: basisRow } : bases.transitions.some((row) => row.handle === basisRow) ? { transisi: basisRow } : { pembatalan: basisRow }),
            ...(needsValue ? { isi: { jenis: 'VALUE_ONLY', tanggal: date.date, baris: [{ keterangan: description, nilai: value }] } } : {}),
            disposisi: {
                aplikasi: reductions.applications.map((row) => ({ id: row.handle, tindakan: chosenAction(row.handle, applicationChoices) })),
                potongan: reductions.settlements.map((row) => ({ id: row.handle, tindakan: chosenAction(row.handle, settlementChoices) })),
                hapus: reductions.writeoffs.map((row) => ({ id: row.handle, tindakan: chosenAction(row.handle, writeoffChoices) })),
            },
        };
        correct.submit(body, () => { setCorrecting(null); setEnteredFor(null); });
    };
    const noReductions = reductions.applications.length + reductions.settlements.length + reductions.writeoffs.length === 0;
    const actionsMenu = [
        ...(can.kuitansi ? [{ label: t('invoices.action.kuitansi'), onSelect: () => { prepare.clear(); setAmount(''); setPreparing(true); } }] : []),
        ...(can.correct ? [{ label: t('invoices.action.revise'), onSelect: () => { open('REVISI'); } }, { label: t('invoices.action.void'), tone: 'danger' as const, onSelect: () => { open('BATAL'); } }] : []),
    ];
    const otherLayout = documents.some((document) => document.type === 'DOC-06') ? 'DOC-04' : 'DOC-06';

    return (
        <AppShell title={t('pages.pg_043.title')} back={{ href: '/keuangan/invoice', label: t('pages.pg_041.title') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader
                    title={t('invoices.title', { nomor: invoice.number ?? '—' })}
                    crumbs={[{ label: t('pages.pg_041.title'), href: '/keuangan/invoice' }]}
                    back={{ label: t('dialogs.back'), href: invoice.project === null ? '/keuangan/invoice' : `/proyek/${invoice.project}` }}
                    facts={
                        <div className="flex flex-wrap items-center gap-3 text-body text-text-secondary">
                            <StatusBadge family="invoice_and_receivable" state={invoice.state} />
                            <span>{`${invoice.company} · ${invoice.client}`}</span>
                            {invoice.project_number !== null && <a href={`/proyek/${invoice.project ?? ''}`} className="text-accent-link underline underline-offset-2"><Code>{invoice.project_number}</Code></a>}
                            <DateText value={invoice.date} />
                        </div>
                    }
                    primary={receivable.primary}
                    actions={[...receivable.menu, ...actionsMenu]}
                />
                <Answered command={correct} />
                <Answered command={prepare} />
                <Answered command={other} />
                <Answered command={link} />
                {receivable.answers}
                {invoice.void_reason !== null && <p className="text-body text-text-secondary">{t('invoices.detail.void', { alasan: invoice.void_reason })}</p>}
                {invoice.replaces !== null && <a href={`/keuangan/invoice/${invoice.replaces}`} className="text-body text-accent-link underline underline-offset-2">{t('invoices.detail.replaces')}</a>}
                {invoice.replaced_by !== null && <a href={`/keuangan/invoice/${invoice.replaced_by}`} className="text-body text-accent-link underline underline-offset-2">{t('invoices.detail.replaced_by')}</a>}
                <FormSection title={t('invoices.position.title')}>
                    <dl data-testid="position" className="flex min-w-0 flex-col gap-2 text-body">
                        <PositionRow label={t('invoices.position.sales')} value={position.sales} />
                        <PositionRow label={t('invoices.position.tax')} value={position.tax} />
                        <PositionRow label={t('invoices.position.gross')} value={position.gross} />
                        <PositionRow label={t('invoices.position.applied')} value={position.applied} />
                        <PositionRow label={t('invoices.position.fee')} value={position.fee_settled} />
                        <PositionRow label={t('invoices.position.tax_settled')} value={position.tax_settled} />
                        <PositionRow label={t('invoices.position.written_off')} value={position.written_off} />
                        <PositionRow label={t('invoices.position.outstanding')} value={position.outstanding} strong />
                    </dl>
                </FormSection>
                {receivable.section}
                <FormSection title={t('invoices.detail.lines')}>
                    <ol aria-label={t('invoices.detail.lines')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {lines.map((line, index) => (
                            <li key={index} className="flex min-w-0 flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3">
                                <span className="min-w-0 text-body text-text-primary">{`${String(index + 1)}. ${line.description}`}</span>
                                <span className="flex flex-wrap items-baseline gap-4 text-body text-text-secondary">
                                    {line.quantity !== null && <span>{formatQuantity(line.quantity, line.unit ?? '')}</span>}
                                    <Money value={line.value} />
                                </span>
                            </li>
                        ))}
                    </ol>
                    {taxes.map((tax) => (
                        <p key={tax.code} className="flex justify-between gap-3 text-body text-text-secondary"><span>{`${tax.code} ${tax.rate}%`}</span><Money value={tax.amount} /></p>
                    ))}
                </FormSection>
                <FormSection title={t('invoices.detail.reductions')}>
                    {can.settle && <a href={`/keuangan/potongan?invoice=${invoice.id}`} className="inline-flex min-h-touch items-center self-start text-body text-accent-link underline underline-offset-2">{t('actions.record_deduction')}</a>}
                    {noReductions ? <RegionEmpty text={t('invoices.detail.no_reductions')} /> : (
                        <ul role="list" data-testid="reductions" className="flex min-w-0 flex-col gap-2 text-body text-text-primary">
                            {reductions.applications.map((row) => <li key={row.handle} className="flex justify-between gap-3"><span>{t('invoices.detail.application', { tanggal: dateLong(row.date) })}</span><Money value={row.amount} /></li>)}
                            {reductions.settlements.map((row) => <li key={row.handle} className="flex justify-between gap-3"><span>{t(row.class === 'FEE' ? 'invoices.detail.settlement_fee' : 'invoices.detail.settlement_tax', { tanggal: dateLong(row.date) })}</span><Money value={row.amount} /></li>)}
                            {reductions.writeoffs.map((row) => <li key={row.handle} className="flex justify-between gap-3"><span>{t('invoices.detail.writeoff', { tanggal: dateLong(row.date) })}</span><Money value={row.amount} /></li>)}
                        </ul>
                    )}
                </FormSection>
                <FormSection title={t('invoices.detail.documents')}>
                    {documents.length === 0 ? <p className="text-body text-text-secondary">{t('invoices.detail.no_documents')}</p> : (
                        <ul role="list" className="flex min-w-0 flex-col gap-2">
                            {documents.map((document) => (
                                <li key={document.id} className="flex flex-wrap items-center gap-3 text-body">
                                    <Icon icon={FileText} size="sm" />
                                    <a href={`/dokumen/${document.id}`} className="text-accent-link underline underline-offset-2">{`${t(`documents.${document.type.toLowerCase().replace('-', '_')}` as TranslationKey)}${document.number === null ? '' : ` ${document.number}`}`}</a>
                                </li>
                            ))}
                        </ul>
                    )}
                    {can.nota && !documents.some((document) => document.type === otherLayout) && (
                        <div><Button variant="ghost" size="sm" pending={other.pending} onClick={() => { other.submit({ jenis: otherLayout, subjek: { invoice_id: id } }); }}>{t('invoices.action.other_layout', { jenis: t(`documents.${otherLayout.toLowerCase().replace('-', '_')}` as TranslationKey) })}</Button></div>
                    )}
                    {can.request && !documents.some((document) => document.type === 'DOC-13') && (
                        <div><Button variant="ghost" size="sm" pending={other.pending} onClick={() => { other.submit({ jenis: 'DOC-13', subjek: { invoice_id: id } }); }}>{t('receivables.action.request')}</Button></div>
                    )}
                </FormSection>
                <FormSection title={t('invoices.kuitansi.title')}>
                    {kuitansi.length === 0 ? <p className="text-body text-text-secondary">{t('invoices.kuitansi.empty')}</p> : (
                        <ul role="list" data-testid="kuitansi" className="flex min-w-0 flex-col gap-3">
                            {kuitansi.map((row) => (
                                <li key={row.id} className="flex min-w-0 flex-col gap-1">
                                    <div className="flex flex-wrap items-center gap-3 text-body">
                                        <a href={`/dokumen/${row.id}`} className="text-accent-link underline underline-offset-2">{row.number ?? t('invoices.kuitansi.draft')}</a>
                                        {row.amount !== null && <Money value={row.amount} />}
                                        {row.state === 'ISSUED' && <StatusBadge family="pre_payment_kuitansi" state={row.linked ? 'LINKED' : 'OPEN_NOT_LINKED'} size="sm" />}
                                        {row.state === 'ISSUED' && !row.linked && can.link && (
                                            <Button variant="outline" size="sm" onClick={() => { link.clear(); setPicked([]); setLinking(row); }}>
                                                <Icon icon={LinkIcon} size="sm" />
                                                {t('invoices.action.link')}
                                            </Button>
                                        )}
                                    </div>
                                    {!row.linked && row.cause !== null && <p className="text-body text-text-secondary">{t(row.cause as TranslationKey)}</p>}
                                </li>
                            ))}
                        </ul>
                    )}
                </FormSection>
            </div>
            <ConfirmCommand
                open={preparing}
                onOpenChange={setPreparing}
                title={t('invoices.action.kuitansi')}
                consequence={t('invoices.kuitansi.dialog')}
                items={
                    <FormField label={t('invoices.kuitansi.amount')} required error={prepare.errors.jumlah}>
                        {(control) => <DecimalInput {...control} value={amount} scale={2} kind="money" onValueChange={(next) => { setAmount(next ?? ''); }} />}
                    </FormField>
                }
                confirmLabel={t('composer.next')}
                pending={prepare.pending}
                answer={prepare.answer}
                onConfirm={() => { prepare.submit({ jenis: 'DOC-05', mode: 'PRE_PAYMENT', subjek: { invoice_id: id }, jumlah: amount }); }}
            />
            <ConfirmCommand
                open={linking !== null}
                onOpenChange={(next) => { if (!next) setLinking(null); }}
                title={t('invoices.kuitansi.link_title', { nomor: linking?.number ?? '' })}
                consequence={t('invoices.kuitansi.link_figures', { kuitansi: money(linking?.amount ?? '0') })}
                items={payments.length === 0 ? <p>{t('invoices.kuitansi.no_payments')}</p> : (
                    <ul role="list" className="flex flex-col gap-2">
                        {payments.map((payment) => (
                            <li key={payment.id} className="flex items-center gap-2 text-body">
                                <Checkbox id={`bayar-${payment.id}`} checked={picked.includes(payment.id)} onCheckedChange={(checked) => { setPicked(checked ? [...picked, payment.id] : picked.filter((each) => each !== payment.id)); }} />
                                <label htmlFor={`bayar-${payment.id}`}>{t('invoices.kuitansi.payment', { tanggal: dateLong(payment.date), jumlah: money(payment.amount) })}</label>
                            </li>
                        ))}
                    </ul>
                )}
                confirmLabel={t('invoices.action.link')}
                pending={link.pending}
                answer={link.answer}
                onConfirm={() => { link.submit({ pembayaran: picked }, () => { setLinking(null); }); }}
            />
            <ConfirmCommand
                open={correcting !== null}
                onOpenChange={(next) => { if (!next) setCorrecting(null); }}
                title={t(voiding ? 'invoices.correct.void_title' : 'invoices.correct.revise_title')}
                consequence={t('invoices.correct.consequence')}
                items={
                    <div className="flex min-w-0 flex-col gap-3">
                        <BusinessDateControl today={today} value={date} onChange={setDate} />
                        {correct.errors.tanggal !== undefined && <p className="text-danger">{correct.errors.tanggal}</p>}
                        <FormField label={t('invoices.correct.basis')} required error={correct.errors.dasar}>
                            {(control) => (
                                <NativeSelect {...control} value={basis} onChange={(event) => { setBasis(event.target.value); setBasisRow(''); setActions({}); }}>
                                    <option value="" />
                                    {basisOptions.map(([code, key]) => <option key={code} value={code}>{t(key)}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        {rowOptions.length > 0 && (
                            <FormField label={t('invoices.correct.basis_row')} required>
                                {(control) => (
                                    <NativeSelect {...control} value={basisRow} onChange={(event) => { setBasisRow(event.target.value); }}>
                                        <option value="" />
                                        {rowOptions.map((row) => <option key={row.handle} value={row.handle}>{row.label}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                        )}
                        {needsEvidence && (
                            <FormField label={t('invoices.correct.evidence')} required error={correct.errors.bukti}>
                                {(control) => (
                                    <NativeSelect {...control} value={cited} onChange={(event) => { setCited(event.target.value); }}>
                                        <option value="" />
                                        {evidence.map((option) => <option key={option.id} value={option.id}>{`${t(`evidence.type.${option.type}` as TranslationKey)} · ${option.issuer} · ${dateLong(option.date)}`}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                        )}
                        {needsValue && (
                            <>
                                <FormField label={t(voiding ? 'invoices.correct.replacement_value' : 'invoices.correct.new_value')} required error={correct.errors['isi.baris.0.nilai']}>
                                    {(control) => <DecimalInput {...control} value={value} scale={2} kind="money" onValueChange={(next) => { setValue(next ?? ''); }} />}
                                </FormField>
                                <FormField label={t('invoices.correct.new_description')} required error={correct.errors['isi.baris.0.keterangan']}>
                                    {(control) => <Input {...control} value={description} onChange={(event) => { setDescription(event.target.value); }} />}
                                </FormField>
                            </>
                        )}
                        {!noReductions && <p className="font-semibold">{t('invoices.correct.action')}</p>}
                        {([[reductions.applications, applicationChoices, 'invoices.detail.application'], [reductions.settlements, settlementChoices, 'invoices.detail.settlement_fee'], [reductions.writeoffs, can.supersede ? writeoffChoices : [], 'invoices.detail.writeoff']] as [(Application | Settlement | Writeoff)[], [string, TranslationKey][], TranslationKey][]).map(([rows, choices, key]) => rows.map((row) => (
                            <FormField key={row.handle} label={`${t(key, { tanggal: dateLong(row.date) })} · ${money(row.amount)}`}>
                                {(control) => (
                                    <NativeSelect {...control} value={chosenAction(row.handle, choices)} disabled={choices.length === 0} onChange={(event) => { setActions({ ...actions, [row.handle]: event.target.value }); }}>
                                        {choices.map(([code, label]) => <option key={code} value={code}>{t(label)}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                        )))}
                    </div>
                }
                reason={{ value: reason, onChange: setReason, error: correct.errors.alasan ?? null }}
                confirmLabel={t(voiding ? 'invoices.action.void' : 'invoices.action.revise')}
                destructive={voiding}
                pending={correct.pending}
                answer={correct.answer}
                onConfirm={submitCorrection}
            />
            {receivable.dialogs}
        </AppShell>
    );
}
