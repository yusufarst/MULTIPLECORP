// PG-043's receivable controls (ROUTE-243, ROUTE-244, ROUTE-246–ROUTE-248; J-FIN-02; J-FIN-05; TASK-050), each by capability and absent without it (OD-11; AZ-01): "Tagihkan" with the billing date and the entered due date, its
// confirmation first (IP-08; MSG-39); "Koreksi Penagihan" with its evidence and reason (CM-25); "Tandai Sengketa" and "Cabut Sengketa" with their reason, the page saying what a hold does (J-FIN-05); the Owner's "Hapuskan Piutang" with the
// client's available credit to apply first or a reason (L-17), and "Terapkan dan Cabut Penghapusan" for money recovered (AX-24). Every dialog's "Kembali" keeps its input (IP-08; IP-09).
import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { type ReactNode, useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { BusinessDateControl, DateField, type BusinessDate } from '@/components/date-field';
import { DecimalInput, FormField, FormSection } from '@/components/form';
import { OutcomeMessage, type Answer } from '@/components/outcome-message';
import { type MenuAction } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Money } from '@/components/values';
import { dateLong, money } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { BUILT } from '@/lib/navigation';
import { type CommandForm, useCommand } from '@/lib/use-command';

export type Payload = Record<string, FormDataConvertible>;
export type BillingAct = { billed_at: string; due_date: string | null; kind: string; reason: string | null };
export type Billing = { current: BillingAct | null; history: BillingAct[]; days_past: number; bucket: string | null };
export type Hold = { reason: string; set_at: string; lapsed: boolean };
export type WriteoffRow = { handle: string; amount: string; standing: string; reason: string; date: string; before: string; after: string };
export type Recovery = { id: string; amount: string; date: string };
export type EvidenceOption = { id: string; type: string; issuer: string; reference: string | null; date: string };
export type ReceivableCan = { bill: boolean; correct_billing: boolean; dispute: boolean; write_off: boolean; recover: boolean; pay: boolean };

export function Answered({ command }: { command: { answer: Answer | null; unknown: boolean; retry: () => void } }) {
    const { t } = useTranslation();

    return command.answer === null ? null : (
        <OutcomeMessage answer={command.answer} action={command.unknown ? <Button variant="outline" size="sm" onClick={command.retry}>{t('actions.try_again')}</Button> : undefined} />
    );
}

/** "Tagihkan" after its confirmation, on PG-043 and PG-044's rows, its command the page's so the answer outlives the dialog; `back` returns a row's action to the list's filters (IP-18). */
export function BillDialog({ command: bill, outstanding, today, open, onOpenChange, back }: { command: CommandForm<Payload>; outstanding: string; today: string; open: boolean; onOpenChange: (open: boolean) => void; back?: string }) {
    const { t } = useTranslation();
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [due, setDue] = useState<string | null>(null);

    return (
        <ConfirmCommand
            open={open}
            onOpenChange={onOpenChange}
            title={t('receivables.action.bill')}
            consequence={t('receivables.bill.consequence', { jumlah: money(outstanding) })}
            items={
                <div className="flex min-w-0 flex-col gap-3">
                    <BusinessDateControl today={today} value={date} onChange={setDate} />
                    {bill.errors.tanggal !== undefined && <p className="text-danger">{bill.errors.tanggal}</p>}
                    <FormField label={t('receivables.field.due_date')} required error={bill.errors.jatuh_tempo}>
                        {(control) => <DateField {...control} value={due} onValueChange={(day) => { setDue(day); }} />}
                    </FormField>
                </div>
            }
            confirmLabel={t('receivables.action.bill')}
            pending={bill.pending}
            answer={bill.answer}
            onConfirm={() => { bill.submit({ tanggal: date.date, tanggal_otomatis: !date.chosen, jatuh_tempo: due ?? '', ...(back === undefined ? {} : { kembali: back }) }, () => { onOpenChange(false); }); }}
        />
    );
}

export function useInvoiceReceivable({ id, outstanding, billing, hold, inherited, unbilled, writeoffs, credit, recoveries, evidence, today, can }: {
    id: string;
    outstanding: string;
    billing: Billing;
    hold: Hold | null;
    inherited: boolean;
    unbilled: boolean;
    writeoffs: WriteoffRow[];
    credit: string | null;
    recoveries: Recovery[];
    evidence: EvidenceOption[];
    today: string;
    can: ReceivableCan;
}): { primary: ReactNode; menu: MenuAction[]; answers: ReactNode; section: ReactNode; dialogs: ReactNode } {
    const { t } = useTranslation();
    const [billingOpen, setBillingOpen] = useState(false);
    const [correcting, setCorrecting] = useState(false);
    const [holding, setHolding] = useState(false);
    const [writing, setWriting] = useState(false);
    const [recovering, setRecovering] = useState<Recovery | null>(null);
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [due, setDue] = useState<string | null>(null);
    const [cited, setCited] = useState('');
    const [reason, setReason] = useState('');
    const [amount, setAmount] = useState('');
    const [creditReason, setCreditReason] = useState('');
    const [writeoff, setWriteoff] = useState('');
    const bill = useCommand<Payload>(() => `/keuangan/invoice/${id}/tagihkan`, 'post');
    const correct = useCommand<Payload>(() => `/keuangan/invoice/${id}/koreksi-tagihan`, 'post');
    const dispute = useCommand<Payload>(() => `/keuangan/invoice/${id}/sengketa`, 'post');
    const writeOff = useCommand<Payload>(() => `/keuangan/invoice/${id}/hapuskan`, 'post');
    const recover = useCommand<Payload>(() => `/keuangan/invoice/${id}/cabut-penghapusan`, 'post');
    const held = hold !== null && !hold.lapsed;
    const standing = writeoffs.filter((row) => Number(row.standing) > 0);
    const fresh = () => { setDate({ date: today, chosen: false }); setReason(''); setAmount(''); };
    const openCorrection = () => { correct.clear(); fresh(); setDue(billing.current?.due_date ?? null); setCited(''); setCorrecting(true); };
    const openHold = () => { dispute.clear(); fresh(); setHolding(true); };
    const openWrite = () => { writeOff.clear(); fresh(); setCreditReason(''); setWriting(true); };
    const openRecover = (recovery: Recovery) => { recover.clear(); fresh(); setAmount(recovery.amount); setWriteoff(standing[0]?.handle ?? ''); setRecovering(recovery); };
    const bucketLabel = billing.bucket === null ? null : t(`receivables.bucket.${billing.bucket}` as TranslationKey);
    const menu: MenuAction[] = [
        // PG-046 is TASK-051's: the link appears with its page (J-FIN-03).
        ...(can.pay && BUILT.has('PG-046') ? [{ label: t('receivables.action.pay'), onSelect: () => { router.visit(`/keuangan/pembayaran/baru?${new URLSearchParams({ invoice: id }).toString()}`); } }] : []),
        ...(can.dispute ? [{ label: t(held ? 'receivables.action.clear' : 'receivables.action.hold'), onSelect: openHold }] : []),
        ...(can.correct_billing ? [{ label: t('receivables.action.correct'), onSelect: openCorrection }] : []),
        ...(can.write_off ? [{ label: t('receivables.action.write_off'), tone: 'danger' as const, onSelect: openWrite }] : []),
    ];
    const billButton = (className?: string) => can.bill ? <Button className={className} onClick={() => { setBillingOpen(true); }}>{t('receivables.action.bill')}</Button> : undefined;

    return {
        primary: billButton(),
        menu,
        answers: (
            <>
                <Answered command={bill} />
                <Answered command={correct} />
                <Answered command={dispute} />
                <Answered command={writeOff} />
                <Answered command={recover} />
            </>
        ),
        section: (
            <>
                <FormSection title={t('receivables.billing.title')}>
                    <div data-testid="billing" className="flex min-w-0 flex-col gap-2 text-body">
                        {billing.current !== null ? (
                            <>
                                <p className="text-text-primary">{t('invoices.detail.billing', { tanggal: dateLong(billing.current.billed_at), jatuh: billing.current.due_date === null ? t('receivables.billing.no_due') : dateLong(billing.current.due_date) })}</p>
                                {bucketLabel !== null && <p className="text-text-secondary">{billing.days_past > 0 ? t('receivables.age.days', { hari: String(billing.days_past), kelompok: bucketLabel }) : bucketLabel}</p>}
                            </>
                        ) : unbilled ? <p className="text-text-secondary">{t('invoices.position.unbilled')}</p> : null}
                        {inherited && <p className="text-text-secondary">{t('invoices.detail.inherited')}</p>}
                        {billing.history.length > 0 && (
                            <>
                                <p className="font-semibold text-text-primary">{t('receivables.billing.history_title')}</p>
                                <ul role="list" className="flex min-w-0 flex-col gap-1 text-text-secondary">
                                    {billing.history.map((act, index) => (
                                        <li key={index}>{t('invoices.detail.billing', { tanggal: dateLong(act.billed_at), jatuh: act.due_date === null ? t('receivables.billing.no_due') : dateLong(act.due_date) })}</li>
                                    ))}
                                </ul>
                                {billing.current?.reason !== null && billing.current?.reason !== undefined && <p className="text-text-secondary">{t('receivables.billing.corrected', { alasan: billing.current.reason })}</p>}
                            </>
                        )}
                        {billButton('md:hidden')}
                    </div>
                </FormSection>
                {hold !== null && (
                    <FormSection title={t('receivables.hold.title')}>
                        <div data-testid="hold" className="flex min-w-0 flex-col gap-2 text-body">
                            <p className="text-text-primary">{t('receivables.hold.active', { tanggal: dateLong(hold.set_at), alasan: hold.reason })}</p>
                            <p className="text-text-secondary">{t(hold.lapsed ? 'receivables.hold.lapsed' : 'receivables.hold.set_consequence')}</p>
                        </div>
                    </FormSection>
                )}
                {writeoffs.length > 0 && (
                    <FormSection title={t('receivables.writeoffs.title')}>
                        <ul role="list" data-testid="writeoffs" className="flex min-w-0 flex-col gap-3 text-body">
                            {writeoffs.map((row) => (
                                <li key={row.handle} className="flex min-w-0 flex-col gap-1">
                                    <span className="flex flex-wrap justify-between gap-3 text-text-primary"><span>{t('invoices.detail.writeoff', { tanggal: dateLong(row.date) })}</span><Money value={row.amount} /></span>
                                    <span className="text-text-secondary">{row.reason}</span>
                                    <span className="text-text-secondary">{t('receivables.writeoffs.before_after', { sebelum: money(row.before), sesudah: money(row.after) })}</span>
                                    <span className="text-text-secondary">{Number(row.standing) > 0 ? t('receivables.writeoffs.standing', { jumlah: money(row.standing) }) : t('receivables.writeoffs.superseded')}</span>
                                </li>
                            ))}
                        </ul>
                    </FormSection>
                )}
                {can.recover && standing.length > 0 && recoveries.length > 0 && (
                    <FormSection title={t('receivables.recoveries.title')}>
                        <ul role="list" data-testid="recoveries" className="flex min-w-0 flex-col gap-3 text-body">
                            {recoveries.map((row) => (
                                <li key={row.id} className="flex min-w-0 flex-wrap items-center justify-between gap-3">
                                    <span className="text-text-primary">{t('receivables.recoveries.row', { tanggal: dateLong(row.date), jumlah: money(row.amount) })}</span>
                                    <Button variant="outline" size="sm" onClick={() => { openRecover(row); }}>{t('receivables.action.supersede')}</Button>
                                </li>
                            ))}
                        </ul>
                    </FormSection>
                )}
            </>
        ),
        dialogs: (
            <>
                <BillDialog command={bill} outstanding={outstanding} today={today} open={billingOpen} onOpenChange={setBillingOpen} />
                <ConfirmCommand
                    open={correcting}
                    onOpenChange={setCorrecting}
                    title={t('receivables.action.correct')}
                    consequence={t('receivables.correct.consequence')}
                    items={
                        <div className="flex min-w-0 flex-col gap-3">
                            <BusinessDateControl today={today} value={date} onChange={setDate} />
                            {correct.errors.tanggal !== undefined && <p className="text-danger">{correct.errors.tanggal}</p>}
                            <FormField label={t('receivables.field.due_date')} required error={correct.errors.jatuh_tempo}>
                                {(control) => <DateField {...control} value={due} onValueChange={(day) => { setDue(day); }} />}
                            </FormField>
                            <FormField label={t('invoices.correct.evidence')} required error={correct.errors.bukti}>
                                {(control) => (
                                    <NativeSelect {...control} value={cited} onChange={(event) => { setCited(event.target.value); }}>
                                        <option value="" />
                                        {evidence.map((option) => <option key={option.id} value={option.id}>{`${t(`evidence.type.${option.type}` as TranslationKey)} · ${option.issuer} · ${dateLong(option.date)}`}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                        </div>
                    }
                    reason={{ value: reason, onChange: setReason, error: correct.errors.alasan ?? null }}
                    confirmLabel={t('receivables.action.correct')}
                    pending={correct.pending}
                    answer={correct.answer}
                    onConfirm={() => { correct.submit({ tanggal: date.date, tanggal_otomatis: !date.chosen, jatuh_tempo: due ?? '', bukti: cited, alasan: reason }, () => { setCorrecting(false); }); }}
                />
                <ConfirmCommand
                    open={holding}
                    onOpenChange={setHolding}
                    title={t(held ? 'receivables.action.clear' : 'receivables.action.hold')}
                    consequence={t(held ? 'receivables.hold.clear_consequence' : 'receivables.hold.set_consequence')}
                    reason={{ value: reason, onChange: setReason, error: dispute.errors.alasan ?? null }}
                    confirmLabel={t(held ? 'receivables.action.clear' : 'receivables.action.hold')}
                    pending={dispute.pending}
                    answer={dispute.answer}
                    onConfirm={() => { dispute.submit({ aksi: held ? 'CABUT' : 'TANDAI', alasan: reason }, () => { setHolding(false); }); }}
                />
                <ConfirmCommand
                    open={writing}
                    onOpenChange={setWriting}
                    title={t('receivables.action.write_off')}
                    consequence={t('receivables.writeoff.consequence')}
                    items={
                        <div className="flex min-w-0 flex-col gap-3">
                            <BusinessDateControl today={today} value={date} onChange={setDate} />
                            {writeOff.errors.tanggal !== undefined && <p className="text-danger">{writeOff.errors.tanggal}</p>}
                            <p className="text-text-secondary">{t('receivables.writeoff.outstanding', { jumlah: money(outstanding) })}</p>
                            <FormField label={t('receivables.writeoff.amount')} required error={writeOff.errors.jumlah}>
                                {(control) => <DecimalInput {...control} value={amount} scale={2} kind="money" onValueChange={(next) => { setAmount(next ?? ''); }} />}
                            </FormField>
                            {credit !== null && Number(credit) > 0 && (
                                <>
                                    <p data-testid="credit" className="text-text-primary">{t('receivables.writeoff.credit', { kredit: money(credit) })}</p>
                                    <FormField label={t('receivables.writeoff.credit_reason')} error={writeOff.errors.alasan_kredit}>
                                        {(control) => <Input {...control} value={creditReason} onChange={(event) => { setCreditReason(event.target.value); }} />}
                                    </FormField>
                                </>
                            )}
                        </div>
                    }
                    reason={{ value: reason, onChange: setReason, error: writeOff.errors.alasan ?? null }}
                    confirmLabel={t('receivables.action.write_off')}
                    destructive
                    pending={writeOff.pending}
                    answer={writeOff.answer}
                    onConfirm={() => { writeOff.submit({ jumlah: amount, alasan: reason, ...(creditReason.trim() === '' ? {} : { alasan_kredit: creditReason }), tanggal: date.date, tanggal_otomatis: !date.chosen }, () => { setWriting(false); }); }}
                />
                <ConfirmCommand
                    open={recovering !== null}
                    onOpenChange={(next) => { if (!next) setRecovering(null); }}
                    title={t('receivables.action.supersede')}
                    consequence={t('receivables.supersede.consequence')}
                    items={
                        <div className="flex min-w-0 flex-col gap-3">
                            <BusinessDateControl today={today} value={date} onChange={setDate} />
                            {recover.errors.tanggal !== undefined && <p className="text-danger">{recover.errors.tanggal}</p>}
                            <FormField label={t('receivables.supersede.writeoff')} required error={recover.errors.penghapusan}>
                                {(control) => (
                                    <NativeSelect {...control} value={writeoff} onChange={(event) => { setWriteoff(event.target.value); }}>
                                        {standing.map((row) => <option key={row.handle} value={row.handle}>{`${dateLong(row.date)} · ${money(row.standing)}`}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                            <FormField label={t('receivables.supersede.amount')} required error={recover.errors.jumlah}>
                                {(control) => <DecimalInput {...control} value={amount} scale={2} kind="money" onValueChange={(next) => { setAmount(next ?? ''); }} />}
                            </FormField>
                        </div>
                    }
                    reason={{ value: reason, onChange: setReason, error: recover.errors.alasan ?? null }}
                    confirmLabel={t('receivables.action.supersede')}
                    pending={recover.pending}
                    answer={recover.answer}
                    onConfirm={() => { recover.submit({ penghapusan: writeoff, pembayaran: recovering?.id ?? '', jumlah: amount, alasan: reason, tanggal: date.date, tanggal_otomatis: !date.chosen }, () => { setRecovering(null); }); }}
                />
            </>
        ),
    };
}
