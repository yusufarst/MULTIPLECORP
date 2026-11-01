// One line of PG-024 (ROUTE-186; INT-186; AX-03; J-INV-03; IP-05; IP-08; IP-19; UXS-15; UXS-22): the quantity, the lots the operator confirms — on the phone a list with one tap per lot, "Ambil Lebih Dulu" a picking aid only, another company's by its code, rack and quantity with the relation marker —, each unit of a serialized product, "Alasan alokasi antar-perusahaan" for another company's lot; the confirmation first (IP-08), its own answer in place (IP-19). Never a cost.
import type { FormDataConvertible } from '@inertiajs/core';
import { useEffect, useState } from 'react';

import { RelationMarker } from '@/components/company';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { DecimalInput, FormField } from '@/components/form';
import { DuplicateWarning, type DuplicateMatch } from '@/components/notices';
import { OutcomeMessage } from '@/components/outcome-message';
import { RegionLoading } from '@/components/region-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Code } from '@/components/values';
import { useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

export type DispatchLine = { handle: string; no: number; description: string; product: string; name: string; sku: string | null; serialized: boolean; scale: number; demand: string; reserved: string; dispatched: string; remaining: string; units: { code: string; name: string; ratio: number }[] };
export type LotChoice = { lot: string; code: string; location: string; rack: string; qty: string; other: boolean; oldest: boolean };
export type SerialChoice = { handle: string; serial: string; lot: string; location: string };
export type Choices = { line: string; lots: LotChoice[]; serials: SerialChoice[] };

const placeOf = (lot: string, location: string) => `${lot}|${location}`;

export function DispatchLineForm({ line, url, closed, today, choices, chosen, onChosen, hidden, onUnsent, onBusy }: {
    line: DispatchLine;
    url: string;
    closed: boolean;
    today: string;
    choices: Choices | null;
    /** The units chosen, by serial handle; kept in the page, so a scan can add one (BD-18). */
    chosen: string[];
    onChosen: (next: string[]) => void;
    hidden: boolean;
    onUnsent: (unsent: boolean) => void;
    onBusy: (busy: boolean) => void;
}) {
    const { t } = useTranslation();
    const [unit, setUnit] = useState(line.units[0]?.code ?? '');
    const [quantity, setQuantity] = useState('');
    const [picks, setPicks] = useState<Record<string, string>>({});
    const [reason, setReason] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [duplicateReason, setDuplicateReason] = useState('');
    const record = useCommand<Record<string, FormDataConvertible>>(() => url, 'post');
    const frozen = record.pending || record.unknown;
    const ratio = line.units.find((one) => one.code === unit)?.ratio ?? 1;
    const lots = choices?.lots ?? [];
    const serialRows = (choices?.serials ?? []).filter((serial) => chosen.includes(serial.handle));
    const anyOther = line.serialized
        ? serialRows.some((serial) => lots.find((lot) => lot.lot === serial.lot)?.other === true)
        : lots.some((lot) => lot.other && Number(picks[placeOf(lot.lot, lot.location)] ?? 0) > 0);
    const base = Number(quantity === '' ? 0 : quantity) * ratio;
    const picked = line.serialized ? serialRows.length : Object.values(picks).reduce((sum, qty) => sum + Number(qty === '' ? 0 : qty), 0);
    const unsent = quantity !== '' || Object.keys(picks).length > 0 || chosen.length > 0 || reason !== '';
    const matches = Array.isArray(record.state.matches) ? (record.state.matches as DuplicateMatch[]) : [];
    const warned = record.answer?.kind === 'REJECTED' && matches.length > 0;

    useEffect(() => { onUnsent(unsent); }, [unsent, onUnsent]);
    useEffect(() => { onBusy(frozen); }, [frozen, onBusy]);

    const send = () => {
        const lot = line.serialized
            ? serialRows.map((serial) => ({ lot: serial.lot, rak: serial.location, jumlah: '1', seri: serial.handle }))
            : lots.filter((one) => Number(picks[placeOf(one.lot, one.location)] ?? 0) > 0).map((one) => ({ lot: one.lot, rak: one.location, jumlah: picks[placeOf(one.lot, one.location)] ?? '0' }));
        record.submit({
            baris: line.handle, satuan: unit, jumlah: quantity, lot, alasan_alokasi: anyOther ? reason : null,
            duplikat: warned ? acknowledged : [], alasan_duplikat: warned ? duplicateReason : null,
            kebijakan_seri: line.serialized, kebijakan_skala: line.scale, tanggal: today, tanggal_otomatis: true,
        }, () => {
            setConfirming(false);
            setQuantity('');
            setPicks({});
            setReason('');
            setAcknowledged([]);
            setDuplicateReason('');
            onChosen([]);
        });
    };
    // One tap takes what the lot holds up to what the line still needs; a second tap leaves it.
    const tap = (lot: LotChoice) => {
        const key = placeOf(lot.lot, lot.location);

        if (key in picks) {
            setPicks(Object.fromEntries(Object.entries(picks).filter(([other]) => other !== key)));

            return;
        }

        const left = Math.max(0, (base === 0 ? Number(line.remaining) : base) - picked);
        setPicks({ ...picks, [key]: String(Math.min(Number(lot.qty), left)) });
    };

    return (
        <section data-slot="dispatch-line" data-line={line.no} hidden={hidden} className="flex min-w-0 flex-col gap-4">
            <h2 className="text-subhead">{`${String(line.no)}. ${line.name}`}</h2>
            <p className="text-body text-text-secondary">{t('dispatch.line.figures', { diminta: line.demand, direservasi: line.reserved, keluar: line.dispatched, sisa: line.remaining })}</p>
            {record.answer !== null && !warned && !confirming && <OutcomeMessage answer={record.answer} action={record.unknown ? <Button variant="outline" size="sm" onClick={record.retry}>{t('actions.try_again')}</Button> : undefined} />}
            {warned && (
                <DuplicateWarning matches={matches} message={record.answer?.text} acknowledged={acknowledged} onAcknowledge={setAcknowledged} canContinue
                    reason={{ value: duplicateReason, onChange: setDuplicateReason, error: record.errors.alasan_duplikat ?? null }} />
            )}
            <div className="flex min-w-0 flex-wrap items-end gap-3">
                <FormField label={t('dispatch.field.quantity')} className="w-40" error={record.errors.jumlah}>
                    {(control) => <DecimalInput {...control} scale={line.scale} disabled={frozen || closed} value={quantity} onValueChange={(next) => { setQuantity(next ?? ''); }} />}
                </FormField>
                {line.units.length > 1 && (
                    <FormField label={t('dispatch.field.unit')} className="w-40">
                        {(control) => (
                            <NativeSelect {...control} value={unit} disabled={frozen || closed} onChange={(event) => { setUnit(event.target.value); }}>
                                {line.units.map((one) => <option key={one.code} value={one.code}>{one.name}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                )}
            </div>
            {choices === null ? <RegionLoading /> : line.serialized ? (
                <fieldset className="flex min-w-0 flex-col gap-2" disabled={frozen || closed} data-slot="dispatch-serials">
                    <legend className="text-body font-medium">{t('dispatch.field.serials')}</legend>
                    {choices.serials.length === 0 && <p className="text-body text-text-secondary">{t('dispatch.line.no_stock')}</p>}
                    {choices.serials.map((serial) => {
                        const lot = lots.find((one) => one.lot === serial.lot && one.location === serial.location);

                        return (
                            <label key={serial.handle} className="flex min-h-floor min-w-0 items-center gap-2 text-body">
                                <Checkbox checked={chosen.includes(serial.handle)} disabled={frozen || closed} onCheckedChange={(checked) => { onChosen(checked ? [...chosen, serial.handle] : chosen.filter((other) => other !== serial.handle)); }} />
                                <span className="min-w-0 font-mono">{serial.serial}</span>
                                <span className="flex min-w-0 flex-wrap items-center gap-2 text-text-secondary"><Code>{lot?.code ?? ''}</Code>{lot?.rack ?? ''}{lot?.other === true && <RelationMarker />}</span>
                            </label>
                        );
                    })}
                </fieldset>
            ) : (
                <fieldset className="flex min-w-0 flex-col gap-2" disabled={frozen || closed} data-slot="dispatch-lots">
                    <legend className="text-body font-medium">{t('dispatch.field.lots')}</legend>
                    {lots.length === 0 && <p className="text-body text-text-secondary">{t('dispatch.line.no_stock')}</p>}
                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {lots.map((lot) => {
                            const key = placeOf(lot.lot, lot.location);
                            const selected = key in picks;

                            return (
                                <li key={key} data-slot="dispatch-lot" className="flex min-w-0 flex-wrap items-center gap-3 py-2">
                                    <button type="button" aria-pressed={selected} className="flex min-h-floor min-w-0 flex-1 flex-wrap items-center gap-2 text-left text-body" onClick={() => { tap(lot); }}>
                                        <Code>{lot.code}</Code>
                                        <span>{`${lot.qty} · ${lot.rack}`}</span>
                                        {lot.oldest && <Badge>{t('stock.lot.oldest')}</Badge>}
                                        {lot.other && <RelationMarker />}
                                    </button>
                                    {selected && (
                                        <FormField label={t('dispatch.field.take', { kode: lot.code })} className="w-32">
                                            {(control) => <DecimalInput {...control} scale={line.scale} disabled={frozen} value={picks[key] ?? ''} onValueChange={(next) => { setPicks({ ...picks, [key]: next ?? '' }); }} />}
                                        </FormField>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </fieldset>
            )}
            {anyOther && (
                <FormField label={t('forms.intercompany_reason')} error={record.errors.alasan_alokasi}>
                    {(control) => <Input {...control} value={reason} maxLength={500} disabled={frozen} onChange={(event) => { setReason(event.target.value); }} />}
                </FormField>
            )}
            {!closed && Number(line.remaining) > 0 && (
                <Button className="self-start" size="lg" disabled={frozen || quantity === '' || picked === 0 || (warned && (acknowledged.length < matches.length || duplicateReason.trim() === ''))} pending={record.pending} onClick={() => { if (warned) { send(); } else { setConfirming(true); } }}>{t('actions.record_dispatch')}</Button>
            )}
            <ConfirmCommand
                open={confirming}
                onOpenChange={setConfirming}
                title={t('actions.record_dispatch')}
                consequence={t('dispatch.confirm.consequence', { jumlah: quantity, satuan: unit })}
                confirmLabel={t('actions.record_dispatch')}
                pending={record.pending}
                answer={record.answer}
                onConfirm={send}
            />
        </section>
    );
}
