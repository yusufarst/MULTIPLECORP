// PG-022's current line (WF-INV-01; J-INV-01; IP-19; IP-08; IP-04; L-13; D-UX-12): the arrived quantity split into *Layak pakai* to a rack, *Rusak diterima* and *Ditolak*, serials as they are scanned, the supplier's delivery reference, the optional "Reservasi untuk Proyek Ini" inside the same command, its own command form and answer. A failed answer freezes the inputs with "Coba Lagi" under the same `command_id`.
import type { FormDataConvertible } from '@inertiajs/core';
import { useEffect, useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { type BusinessDate, BusinessDateControl } from '@/components/date-field';
import { DecimalInput, FormField } from '@/components/form';
import { OutcomeMessage } from '@/components/outcome-message';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { ProofSheet } from '@/features/fin/proof-sheet';
import * as decimal from '@/lib/decimal';
import { useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

export type ReceiveLine = { handle: string; no: number; description: string; product: string; name: string; sku: string | null; serialized: boolean; scale: number; ordered: string; received: string; closed: string; remaining: string; replacement: string; reservable: string | null; units: { code: string; name: string; ratio: number }[] };
export type Receipt = { handle: string; movement: string; line: number; kind: string; usable: string; damaged: string; rejected: string; reference: string | null; date: string; lot: string | null; reversed: boolean };
export type ReturnCase = { id: string; label: string };
export type Rack = { id: string; code: string; name: string };
export type ScannedSerial = { serial: string; damaged: boolean };

export const plain = (value: string): string => (value.includes('.') ? value.replace(/\.?0+$/, '') : value);

export function LineForm({ line, url, company, racks, receipts, cases, closed, today, canEvidence, serials, onSerials, hidden, onUnsent, onBusy }: {
    line: ReceiveLine;
    url: string;
    company: string;
    racks: Rack[];
    receipts: Receipt[];
    cases: ReturnCase[];
    closed: boolean;
    today: string;
    canEvidence: boolean;
    serials: ScannedSerial[];
    onSerials: (next: ScannedSerial[]) => void;
    hidden: boolean;
    onUnsent: (unsent: boolean) => void;
    onBusy: (busy: boolean) => void;
}) {
    const { t } = useTranslation();
    const command = useCommand<Record<string, FormDataConvertible>>(() => url, 'post');
    const [unit, setUnit] = useState(line.units[0]?.code ?? '');
    const [usable, setUsable] = useState('');
    const [damaged, setDamaged] = useState('');
    const [rejected, setRejected] = useState('');
    const [rack, setRack] = useState('');
    const [reference, setReference] = useState('');
    const [kind, setKind] = useState('ORIGINAL');
    const [cited, setCited] = useState('');
    const [returnCase, setReturnCase] = useState('');
    const [reserve, setReserve] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [evidence, setEvidence] = useState<string[]>([]);
    const [uploading, setUploading] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const frozen = command.pending || command.unknown || closed;
    const expected = Number(line.replacement) > 0;
    const busy = command.pending || command.unknown;

    // A retry resends what was confirmed: a serial scanned meanwhile would be cleared with it, never received (IP-02; IP-04).
    useEffect(() => {
        onBusy(busy);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only the line's own change reports it
    }, [busy]);
    const error = (field: string) => command.errors[field];
    const ownReceipts = receipts.filter((receipt) => receipt.line === line.no);
    const usableCount = line.serialized ? String(serials.filter((serial) => !serial.damaged).length) : usable;
    const damagedCount = line.serialized ? String(serials.filter((serial) => serial.damaged).length) : damaged;
    const arrived = decimal.add(usableCount === '' ? '0' : usableCount, damagedCount === '' ? '0' : damagedCount, rejected === '' ? '0' : rejected);
    const accepted = decimal.add(usableCount === '' ? '0' : usableCount, damagedCount === '' ? '0' : damagedCount);
    const unsent = serials.length > 0 || usable !== '' || damaged !== '' || rejected !== '' || reference !== '' || reserve !== '' || evidence.length > 0;

    useEffect(() => {
        onUnsent(unsent);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only the line's own change reports it
    }, [unsent]);

    const ready = Number(arrived) > 0 && (Number(accepted) === 0 || rack !== '');
    const reset = () => {
        setUsable('');
        setDamaged('');
        setRejected('');
        setReference('');
        setReserve('');
        setKind('ORIGINAL');
        setCited('');
        setReturnCase('');
        setEvidence([]);
        onSerials([]);
        setConfirming(false);
    };
    const send = () => {
        command.submit({
            baris: line.handle, jenis: kind, satuan: line.serialized ? (line.units[0]?.code ?? unit) : unit, layak: usableCount === '' ? '0' : usableCount, rusak: damagedCount === '' ? '0' : damagedCount, tolak: rejected === '' ? '0' : rejected,
            rak: rack === '' ? null : rack, referensi: reference === '' ? null : reference, mengoreksi: cited === '' || kind === 'REPLACEMENT' ? null : cited, kasus: returnCase === '' || kind !== 'REPLACEMENT' ? null : returnCase, reservasi: reserve === '' ? null : reserve, bukti: evidence,
            seri: serials.map((serial) => ({ nomor: serial.serial, rusak: serial.damaged })), kebijakan_seri: line.serialized, kebijakan_skala: line.scale, tanggal: date.date, tanggal_otomatis: !date.chosen,
        }, reset);
    };

    return (
        <div data-slot="receive-line" data-line={line.no} hidden={hidden} className="flex min-w-0 flex-col gap-4">
            <h2 className="text-subhead">{line.name}</h2>
            <p className="text-body text-text-secondary">{t('receiving.line.remaining', { sisa: plain(line.remaining) })}</p>
            {expected && <p className="text-body text-text-secondary">{t('receiving.line.replacement', { jumlah: plain(line.replacement) })}</p>}
            {closed && <p className="text-body text-text-secondary">{t('receiving.refusal.purchase_state')}</p>}
            <BusinessDateControl today={today} value={date} onChange={setDate} />
            <div className="flex min-w-0 flex-wrap items-start gap-4">
                {!line.serialized && (
                    <>
                        <FormField label={t('pt10.columns.unit')} error={error('satuan')} className="w-32">
                            {(control) => (
                                <NativeSelect {...control} value={unit} disabled={frozen} onChange={(event) => { setUnit(event.target.value); }}>
                                    {line.units.map((option) => <option key={option.code} value={option.code}>{option.code}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        <FormField label={t('pages.pg_022.usable')} error={error('layak')} className="w-32">
                            {(control) => <DecimalInput {...control} scale={3} value={usable} disabled={frozen} onValueChange={(value) => { setUsable(value ?? ''); }} />}
                        </FormField>
                        <FormField label={t('pages.pg_022.damaged')} error={error('rusak')} className="w-32">
                            {(control) => <DecimalInput {...control} scale={3} value={damaged} disabled={frozen} onValueChange={(value) => { setDamaged(value ?? ''); }} />}
                        </FormField>
                    </>
                )}
                <FormField label={t('pages.pg_022.rejected')} error={error('tolak')} className="w-32">
                    {(control) => <DecimalInput {...control} scale={3} value={rejected} disabled={frozen} onValueChange={(value) => { setRejected(value ?? ''); }} />}
                </FormField>
                <FormField label={t('pages.pg_022.rack')} error={error('rak')} className="min-w-0 sm:w-64">
                    {(control) => (
                        <NativeSelect {...control} value={rack} disabled={frozen} onChange={(event) => { setRack(event.target.value); }}>
                            <option value="">{t('pages.pg_022.rack_choose')}</option>
                            {racks.map((option) => <option key={option.id} value={option.id}>{`${option.code} · ${option.name}`}</option>)}
                        </NativeSelect>
                    )}
                </FormField>
                <FormField label={t('pages.pg_022.delivery_ref')} error={error('referensi')} className="min-w-0 sm:w-64">
                    {(control) => <Input {...control} value={reference} maxLength={120} disabled={frozen} onChange={(event) => { setReference(event.target.value); }} />}
                </FormField>
            </div>
            {line.serialized && (
                <div data-slot="line-serials" className="flex min-w-0 flex-col gap-2">
                    <p className="text-body font-medium">{t('receiving.line.serials', { jumlah: String(serials.length) })}</p>
                    {serials.length === 0 ? <p className="text-caption text-text-secondary">{t('receiving.line.scan_serials')}</p> : (
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {serials.map((serial) => (
                                <li key={serial.serial} className="flex min-w-0 flex-wrap items-center justify-between gap-3 py-2 text-body">
                                    <span className="min-w-0 font-mono">{serial.serial}</span>
                                    <label className="flex items-center gap-2">
                                        <Checkbox checked={serial.damaged} disabled={frozen} onCheckedChange={(checked) => { onSerials(serials.map((other) => (other.serial === serial.serial ? { ...other, damaged: checked } : other))); }} />
                                        {t('pages.pg_022.damaged')}
                                    </label>
                                    <Button variant="ghost" size="sm" disabled={frozen} onClick={() => { onSerials(serials.filter((other) => other.serial !== serial.serial)); }}>{t('receiving.line.remove_serial')}</Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
            {(ownReceipts.length > 0 || expected) && (
                <div className="flex min-w-0 flex-wrap items-start gap-4">
                    <FormField label={t('receiving.line.kind')} className="min-w-0 sm:w-64">
                        {(control) => (
                            <NativeSelect {...control} value={kind} disabled={frozen} onChange={(event) => { setKind(event.target.value); }}>
                                <option value="ORIGINAL">{t('receiving.kind.ORIGINAL')}</option>
                                <option value="SUPPLEMENT">{t('receiving.kind.SUPPLEMENT')}</option>
                                <option value="RE_RECEIPT">{t('receiving.kind.RE_RECEIPT')}</option>
                                {expected && <option value="REPLACEMENT">{t('receiving.kind.REPLACEMENT')}</option>}
                            </NativeSelect>
                        )}
                    </FormField>
                    {kind === 'REPLACEMENT' && (
                        <FormField label={t('receiving.line.case')} error={error('kasus')} className="min-w-0 sm:w-80">
                            {(control) => (
                                <NativeSelect {...control} value={returnCase} disabled={frozen} onChange={(event) => { setReturnCase(event.target.value); }}>
                                    <option value="">{t('receiving.line.choose_case')}</option>
                                    {cases.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}
                    {(kind === 'SUPPLEMENT' || kind === 'RE_RECEIPT') && (
                        <FormField label={t('receiving.line.cited')} error={error('mengoreksi')} className="min-w-0 sm:w-80">
                            {(control) => (
                                <NativeSelect {...control} value={cited} disabled={frozen} onChange={(event) => { setCited(event.target.value); }}>
                                    <option value="">{t('receiving.line.choose_cited')}</option>
                                    {ownReceipts.map((receipt) => <option key={receipt.handle} value={receipt.handle}>{`${receipt.date} · ${receipt.reference ?? receipt.lot ?? ''}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}
                </div>
            )}
            {line.reservable !== null && (
                <FormField label={t('pages.pg_022.reserve')} help={t('pages.pg_022.reserve_limit_tpl', { v: plain(line.reservable) })} error={error('reservasi')} className="min-w-0 sm:w-64">
                    {(control) => <DecimalInput {...control} scale={3} value={reserve} disabled={frozen} onValueChange={(value) => { setReserve(value ?? ''); }} />}
                </FormField>
            )}
            {canEvidence && (
                <div className="flex min-w-0 flex-wrap items-center gap-3">
                    <Button variant="outline" disabled={frozen} onClick={() => { setUploading(true); }}>{t('receiving.line.evidence')}</Button>
                    {evidence.length > 0 && <span className="text-caption text-text-secondary">{t('receiving.line.evidence_count', { jumlah: String(evidence.length) })}</span>}
                </div>
            )}
            {command.answer !== null && <OutcomeMessage answer={command.answer} action={command.unknown ? <Button variant="outline" size="sm" onClick={command.retry}>{t('actions.try_again')}</Button> : undefined} />}
            <div className="flex min-w-0 flex-wrap items-center gap-3">
                <Button size="lg" disabled={!ready || frozen} aria-describedby={ready ? undefined : `reason-${String(line.no)}`} onClick={() => { command.clear(); setConfirming(true); }}>{t('pages.pg_022.record')}</Button>
                {!ready && <span id={`reason-${String(line.no)}`} className="text-caption text-text-secondary">{t('pages.pg_022.record_reason')}</span>}
            </div>
            <ConfirmCommand
                open={confirming && !command.unknown}
                onOpenChange={setConfirming}
                title={t('receiving.confirm.title')}
                consequence={t('receiving.confirm.consequence', { layak: plain(usableCount === '' ? '0' : usableCount), rusak: plain(damagedCount === '' ? '0' : damagedCount), tolak: plain(rejected === '' ? '0' : rejected) })}
                confirmLabel={t('pages.pg_022.record')}
                pending={command.pending}
                answer={command.answer}
                onConfirm={send}
            />
            {canEvidence && <ProofSheet open={uploading} onOpenChange={setUploading} company={company} types={['DELIVERY_PROOF', 'WAREHOUSE_RECORD']} today={today} back={window.location.pathname} onRegistered={(id) => { setUploading(false); setEvidence((current) => [...current, id]); }} />}
        </div>
    );
}
