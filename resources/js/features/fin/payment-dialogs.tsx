// PG-045's commands in place (TASK-051; IP-19): "Terapkan" (INT-252), "Pindahkan Penerapan" with its reason (INT-253; CM-21; L-28) and "Batalkan Catatan Pembayaran (Kontra)" with its form listing every application and settlement and asking what
// becomes of each (INT-254; CM-19; ST-07). Each sheet keeps what was entered when a refusal answers; a stale list says so and comes back with the current one.
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { BusinessDateControl, type BusinessDate } from '@/components/date-field';
import { DecimalInput, FormField, FormSection } from '@/components/form';
import { Answered, type Payload } from '@/features/fin/invoice-receivable';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { Money } from '@/components/values';
import { money } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

export type Application = { handle: string; target: 'INVOICE' | 'REFUND'; invoice: string | null; number: string | null; amount: string; state: string; end_reason: string | null; date: string; receipt: string | null; kuitansi: string | null; can_move: boolean };
export type Settlement = { handle: string; invoice: string | null; number: string | null; class: string; amount: string; state: string };
export type Payment = {
    id: string; date: string; company: string; client: string; amount: string; net: string; contra: string; capacity: string; method: string; account: string | null; reference: string | null; intermediary: string | null; unevidenced: string | null; line: string | null; flagged: boolean; recovery: boolean; proof: string | null;
    claimed: string; settled: string; rerecords: string | null; rerecorded_by: string | null; rerecorded_capacity: string | null; applications: Application[]; settlements: Settlement[];
    can: { apply: boolean; reallocate: boolean; contra: boolean; kuitansi: boolean; link: boolean };
};
export type TargetInvoice = { id: string; number: string | null; project_number: string | null; outstanding: string; billed: boolean; kuitansi: boolean };
export type Targets = { invoices: TargetInvoice[]; shortfalls: { handle: string; shortfall: string }[] };

const positive = (value: string) => /[1-9]/.test(value);

/** Loads what the sheet may target, once it opens. */
function useTargets(payment: string): void {
    useEffect(() => {
        router.reload({ only: ['targets'], data: { sasaran: payment } });
    }, [payment]);
}

export function ApplySheet({ payment, targets, today, back, onClose }: { payment: Payment; targets: Targets | undefined; today: string; back: string; onClose: () => void }) {
    const { t } = useTranslation();
    useTargets(payment.id);
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [entered, setEntered] = useState<Record<string, string>>({});
    const [shortfalls, setShortfalls] = useState<Record<string, string>>({});
    const apply = useCommand<Payload>(() => `/keuangan/pembayaran/${payment.id}/terapkan`, 'post');
    const invoices = targets?.invoices ?? [];
    const filled = invoices.filter((invoice) => (entered[invoice.id] ?? '') !== '');
    const backing = (targets?.shortfalls ?? []).filter((row) => (shortfalls[row.handle] ?? '') !== '');

    return (
        <Sheet open onOpenChange={(open) => { if (!open) onClose(); }}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t('payments.action.apply')}</SheetTitle>
                    <SheetDescription>{t('payments.apply.line', { klien: payment.client, jumlah: money(payment.capacity) })}</SheetDescription>
                </SheetHeader>
                <form className="flex min-w-0 flex-col gap-6" noValidate onSubmit={(event) => {
                    event.preventDefault();
                    apply.submit({ tanggal: date.date, tanggal_otomatis: !date.chosen, penerapan: filled.map((invoice) => ({ invoice: invoice.id, jumlah: entered[invoice.id] ?? '' })), kekurangan: backing.map((row) => ({ baris: row.handle, jumlah: shortfalls[row.handle] ?? '' })), kembali: back }, onClose);
                }}>
                    <BusinessDateControl today={today} value={date} onChange={setDate} />
                    {apply.errors.tanggal !== undefined && <p className="text-body text-danger">{apply.errors.tanggal}</p>}
                    <FormSection title={t('pages.pg_046.applications')}>
                        {invoices.length === 0 && <p className="text-body text-text-secondary">{t('payments.applications.none')}</p>}
                        {invoices.map((invoice, index) => (
                            <FormField key={invoice.id} label={t('payments.apply.invoice', { nomor: invoice.number ?? t('payments.invoice.unnumbered'), sisa: money(invoice.outstanding) })} error={apply.errors[`penerapan.${String(index)}.jumlah`] ?? apply.errors[`penerapan.${String(index)}.invoice`]}>
                                {(control) => <DecimalInput {...control} scale={2} value={entered[invoice.id] ?? ''} onValueChange={(value) => { setEntered((current) => ({ ...current, [invoice.id]: value ?? '' })); }} />}
                            </FormField>
                        ))}
                        {apply.errors.penerapan !== undefined && <p className="text-body text-danger">{apply.errors.penerapan}</p>}
                    </FormSection>
                    {(targets?.shortfalls.length ?? 0) > 0 && (
                        <FormSection title={t('payments.apply.shortfalls')}>
                            {targets?.shortfalls.map((row) => (
                                <FormField key={row.handle} label={t('payments.apply.shortfall', { jumlah: money(row.shortfall) })}>
                                    {(control) => <DecimalInput {...control} scale={2} value={shortfalls[row.handle] ?? ''} onValueChange={(value) => { setShortfalls((current) => ({ ...current, [row.handle]: value ?? '' })); }} />}
                                </FormField>
                            ))}
                        </FormSection>
                    )}
                    <Answered command={apply} />
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="outline" onClick={onClose}>{t('actions.cancel')}</Button>
                        <Button type="submit" pending={apply.pending} disabled={apply.frozen || (filled.length === 0 && backing.length === 0)}>{t('payments.action.apply')}</Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}

export function MoveSheet({ payment, application, targets, today, back, onClose }: { payment: Payment; application: Application; targets: Targets | undefined; today: string; back: string; onClose: () => void }) {
    const { t } = useTranslation();
    useTargets(payment.id);
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [invoice, setInvoice] = useState('');
    const [amount, setAmount] = useState(application.amount);
    const [reason, setReason] = useState('');
    const anchored = payment.settlements.filter((row) => row.state === 'ACTIVE' && row.invoice === application.invoice);
    const [kept, setKept] = useState<Record<string, string>>({});
    const move = useCommand<Payload>(() => `/keuangan/pembayaran/${payment.id}/realokasi`, 'post');
    const options = (targets?.invoices ?? []).filter((row) => row.id !== application.invoice);

    return (
        <Sheet open onOpenChange={(open) => { if (!open) onClose(); }}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t('payments.action.move')}</SheetTitle>
                    <SheetDescription>{t('payments.move.line', { nomor: application.number ?? t('payments.invoice.unnumbered'), jumlah: money(application.amount) })}</SheetDescription>
                </SheetHeader>
                <form className="flex min-w-0 flex-col gap-6" noValidate onSubmit={(event) => {
                    event.preventDefault();
                    move.submit({ penerapan: application.handle, invoice, jumlah: amount, tanggal: date.date, tanggal_otomatis: !date.chosen, alasan: reason, potongan: anchored.map((row) => ({ baris: row.handle, aksi: kept[row.handle] ?? 'PINDAH' })), kembali: back }, onClose);
                }}>
                    <FormField label={t('payments.move.target')} required error={move.errors.invoice}>
                        {(control) => (
                            <NativeSelect {...control} value={invoice} onChange={(event) => { setInvoice(event.target.value); }}>
                                <option value="">{t('forms.choose')}</option>
                                {options.map((row) => <option key={row.id} value={row.id}>{t('payments.apply.invoice', { nomor: row.number ?? t('payments.invoice.unnumbered'), sisa: money(row.outstanding) })}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                    <FormField label={t('payments.move.amount')} required error={move.errors.jumlah}>
                        {(control) => <DecimalInput {...control} scale={2} value={amount} onValueChange={(value) => { setAmount(value ?? ''); }} />}
                    </FormField>
                    <BusinessDateControl today={today} value={date} onChange={setDate} />
                    {move.errors.tanggal !== undefined && <p className="text-body text-danger">{move.errors.tanggal}</p>}
                    {anchored.length > 0 && (
                        <FormSection title={t('payments.move.settlements')}>
                            {anchored.map((row) => (
                                <FormField key={row.handle} label={t('payments.settlement.line', { kelas: t(`payments.claim.${row.class}` as TranslationKey), jumlah: money(row.amount) })}>
                                    {(control) => (
                                        <NativeSelect {...control} value={kept[row.handle] ?? 'PINDAH'} onChange={(event) => { setKept((current) => ({ ...current, [row.handle]: event.target.value })); }}>
                                            <option value="PINDAH">{t('payments.move.settlement_move')}</option>
                                            <option value="TETAP">{t('payments.move.settlement_keep')}</option>
                                        </NativeSelect>
                                    )}
                                </FormField>
                            ))}
                        </FormSection>
                    )}
                    <FormField label={t('messages.msg_40')} required error={move.errors.alasan}>
                        {(control) => <Textarea {...control} rows={3} value={reason} maxLength={500} onChange={(event) => { setReason(event.target.value); }} />}
                    </FormField>
                    <Answered command={move} />
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="outline" onClick={onClose}>{t('dialogs.back')}</Button>
                        <Button type="submit" pending={move.pending} disabled={move.frozen || reason.trim() === '' || invoice === ''}>{t('payments.action.move')}</Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}

export function ContraSheet({ payment, today, back, onClose }: { payment: Payment; today: string; back: string; onClose: () => void }) {
    const { t } = useTranslation();
    const applications = payment.applications.filter((row) => row.state === 'ACTIVE');
    const settlements = payment.settlements.filter((row) => row.state === 'ACTIVE');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [amount, setAmount] = useState(payment.net);
    const [reason, setReason] = useState('');
    const [actions, setActions] = useState<Record<string, string>>({});
    const [parts, setParts] = useState<Record<string, string>>({});
    const [settled, setSettled] = useState<Record<string, string>>({});
    const contra = useCommand<Payload>(() => `/keuangan/pembayaran/${payment.id}/kontra`, 'post');
    const stale = contra.answer?.kind === 'CONFLICT' && contra.answer.cause === 'stale';
    const moving = payment.rerecorded_by !== null;

    return (
        <Sheet open onOpenChange={(open) => { if (!open) onClose(); }}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t('payments.action.contra')}</SheetTitle>
                    <SheetDescription>{t('payments.contra.line', { jumlah: money(payment.net) })}</SheetDescription>
                </SheetHeader>
                <form className="flex min-w-0 flex-col gap-6" noValidate onSubmit={(event) => {
                    event.preventDefault();
                    contra.submit({
                        jumlah: amount, tanggal: date.date, tanggal_otomatis: !date.chosen, alasan: reason, pengganti: payment.rerecorded_by, kembali: back,
                        penerapan: applications.map((row) => ({ baris: row.handle, aksi: actions[row.handle] ?? 'KEEP', jumlah: parts[row.handle] ?? null })),
                        potongan: settlements.map((row) => ({ baris: row.handle, aksi: settled[row.handle] ?? 'KEEP' })),
                    }, onClose);
                }}>
                    <p className="text-body text-text-secondary">{t('payments.contra.help')}</p>
                    <FormField label={t('payments.contra.amount')} required error={contra.errors.jumlah}>
                        {(control) => <DecimalInput {...control} scale={2} value={amount} onValueChange={(value) => { setAmount(value ?? ''); }} />}
                    </FormField>
                    <BusinessDateControl today={today} value={date} onChange={setDate} />
                    {contra.errors.tanggal !== undefined && <p className="text-body text-danger">{contra.errors.tanggal}</p>}
                    {applications.length > 0 && (
                        <FormSection title={t('payments.contra.applications')}>
                            {applications.map((row) => (
                                <div key={row.handle} className="grid min-w-0 gap-3 sm:grid-cols-2">
                                    <FormField label={row.target === 'REFUND' ? t('payments.contra.refund', { jumlah: money(row.amount) }) : t('payments.contra.invoice', { nomor: row.number ?? t('payments.invoice.unnumbered'), jumlah: money(row.amount) })}>
                                        {(control) => (
                                            <NativeSelect {...control} value={actions[row.handle] ?? 'KEEP'} onChange={(event) => { setActions((current) => ({ ...current, [row.handle]: event.target.value })); }}>
                                                <option value="KEEP">{t('payments.disposition.KEEP')}</option>
                                                {row.target === 'INVOICE' && <option value="RELEASE">{t('payments.disposition.RELEASE')}</option>}
                                                {moving && <option value="MOVE">{t('payments.disposition.MOVE')}</option>}
                                                {row.target === 'REFUND' && <option value="SHORTFALL">{t('payments.disposition.SHORTFALL')}</option>}
                                            </NativeSelect>
                                        )}
                                    </FormField>
                                    {(actions[row.handle] ?? 'KEEP') !== 'KEEP' && (
                                        <FormField label={t('payments.contra.part')} help={t('payments.contra.part_help')}>
                                            {(control) => <DecimalInput {...control} scale={2} value={parts[row.handle] ?? ''} onValueChange={(value) => { setParts((current) => ({ ...current, [row.handle]: value ?? '' })); }} />}
                                        </FormField>
                                    )}
                                </div>
                            ))}
                        </FormSection>
                    )}
                    {settlements.length > 0 && (
                        <FormSection title={t('payments.contra.settlements')}>
                            {settlements.map((row) => (
                                <FormField key={row.handle} label={t('payments.settlement.line', { kelas: t(`payments.claim.${row.class}` as TranslationKey), jumlah: money(row.amount) })}>
                                    {(control) => (
                                        <NativeSelect {...control} value={settled[row.handle] ?? 'KEEP'} onChange={(event) => { setSettled((current) => ({ ...current, [row.handle]: event.target.value })); }}>
                                            <option value="KEEP">{t('payments.disposition.KEEP')}</option>
                                            {moving && <option value="MOVE">{t('payments.disposition.MOVE')}</option>}
                                            <option value="RESIDUAL">{t('payments.disposition.RESIDUAL')}</option>
                                        </NativeSelect>
                                    )}
                                </FormField>
                            ))}
                        </FormSection>
                    )}
                    {!moving && <p className="text-body text-text-secondary">{t('payments.contra.no_rerecord')}</p>}
                    {positive(payment.capacity) && <p className="text-body text-text-secondary">{t('payments.contra.credit', { jumlah: money(payment.capacity) })}</p>}
                    <p className="text-body text-text-secondary">{t('payments.contra.total')} <Money value={payment.amount} /></p>
                    <FormField label={t('messages.msg_40')} required error={contra.errors.alasan}>
                        {(control) => <Textarea {...control} rows={3} value={reason} maxLength={500} onChange={(event) => { setReason(event.target.value); }} />}
                    </FormField>
                    <Answered command={contra} />
                    {stale && <Button variant="outline" size="sm" className="self-start" onClick={() => { router.reload({ only: ['rows', 'more'] }); onClose(); }}>{t('payments.contra.reload')}</Button>}
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="outline" onClick={onClose}>{t('dialogs.back')}</Button>
                        <Button type="submit" variant="destructive" pending={contra.pending} disabled={contra.frozen || reason.trim() === ''}>{t('payments.action.contra')}</Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
