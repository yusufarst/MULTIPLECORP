// One line of PG-024 (ROUTE-186; INT-186; AX-03; J-INV-03; IP-05; IP-08; IP-19; UXS-15; UXS-22): the quantity, the lots the operator confirms — on the phone a list with one tap per lot, "Ambil Lebih Dulu" a picking aid only, another company's by its code, rack and quantity with the relation marker —, each unit of a serialized product, "Alasan alokasi antar-perusahaan" for another company's lot; the confirmation first (IP-08), its own answer at its line (IP-19): the dialog closes on the answer, a likely duplicate's ticks and reason filled in the form and confirmed again. Never a cost.
import type { FormDataConvertible } from '@inertiajs/core';
import { useEffect, useState } from 'react';

import { RelationMarker } from '@/components/company';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { type BusinessDate, BusinessDateControl } from '@/components/date-field';
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
import * as decimal from '@/lib/decimal';
import { useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

export type DispatchLine = { handle: string; no: number; description: string; product: string; name: string; sku: string | null; serialized: boolean; scale: number; demand: string; reserved: string; dispatched: string; remaining: string; units: { code: string; name: string; ratio: number }[] };
export type LotChoice = { lot: string; code: string; location: string; rack: string; qty: string; other: boolean; oldest: boolean };
export type SerialChoice = { handle: string; serial: string; lot: string; location: string; code: string; rack: string; other: boolean };
export type Choices = { line: string; find: string | null; lots: LotChoice[]; serials: SerialChoice[]; more: boolean };

const placeOf = (lot: string, location: string) => `${lot}|${location}`;
const figure = (value: string) => (/^\d+(\.\d+)?$/.test(value) ? value : '0');

/** A list longer than the page shows says so and is narrowed by what the operator looks for. */
export function ChoicesFind({ disabled, onFind }: { disabled: boolean; onFind: (text: string) => void }) {
    const { t } = useTranslation();
    const [text, setText] = useState('');

    return (
        <div data-slot="dispatch-find" className="flex min-w-0 flex-wrap items-end gap-3">
            <FormField label={t('dispatch.line.find')} help={t('dispatch.line.more')} className="min-w-0 sm:w-80">
                {(control) => <Input {...control} value={text} maxLength={120} disabled={disabled} onChange={(event) => { setText(event.target.value); }} />}
            </FormField>
            <Button variant="outline" disabled={disabled || text.trim() === ''} onClick={() => { onFind(text.trim()); }}>{t('dispatch.line.find_action')}</Button>
        </div>
    );
}

export function DispatchLineForm({ line, url, closed, today, choices, failed, onRetry, chosen, onChosen, hidden, onUnsent, onBusy, onFind }: {
    line: DispatchLine;
    url: string;
    closed: boolean;
    today: string;
    choices: Choices | null;
    /** The list's read failed: said in place, with its retry (NAV-024). */
    failed: boolean;
    onRetry: () => void;
    /** The units chosen, by serial handle; kept in the page, so a scan can add one (BD-18). */
    chosen: string[];
    onChosen: (next: string[]) => void;
    hidden: boolean;
    onUnsent: (unsent: boolean) => void;
    onBusy: (busy: boolean) => void;
    onFind: (text: string) => void;
}) {
    const { t } = useTranslation();
    const [unit, setUnit] = useState(line.units[0]?.code ?? '');
    const [quantity, setQuantity] = useState('');
    const [typed, setTyped] = useState(false);
    const [picks, setPicks] = useState<Record<string, string>>({});
    const [reason, setReason] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [confirming, setConfirming] = useState(false);
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [duplicateReason, setDuplicateReason] = useState('');
    const [sentAs, setSentAs] = useState('');
    const record = useCommand<Record<string, FormDataConvertible>>(() => url, 'post');
    const frozen = record.pending || record.unknown;
    const ratio = line.units.find((one) => one.code === unit)?.ratio ?? 1;
    const lots = choices?.lots ?? [];
    const serialRows = (choices?.serials ?? []).filter((serial) => chosen.includes(serial.handle));
    const anyOther = line.serialized
        ? serialRows.some((serial) => serial.other)
        : lots.some((lot) => lot.other && Number(figure(picks[placeOf(lot.lot, lot.location)] ?? '')) > 0);
    // A serialized line's quantity follows the units chosen, scanned or ticked, unless the operator typed one.
    const shown = line.serialized && !typed ? (chosen.length > 0 && chosen.length % ratio === 0 ? String(chosen.length / ratio) : '') : quantity;
    const base = decimal.multiply(figure(shown), String(ratio));
    const picked = line.serialized ? String(serialRows.length) : decimal.add('0', ...Object.values(picks).map(figure));
    const unsent = quantity !== '' || Object.keys(picks).length > 0 || chosen.length > 0 || reason !== '';
    const matches = Array.isArray(record.state.matches) ? (record.state.matches as DuplicateMatch[]) : [];
    const signature = JSON.stringify([shown, unit, picks, chosen, date.date]);
    const matched = record.answer?.kind === 'REJECTED' && matches.length > 0;
    // A likely duplicate answers what was sent: once the line changes, it is asked again.
    const warned = matched && sentAs === signature;
    const [seenAnswer, setSeenAnswer] = useState(record.answer);
    const [seenErrors, setSeenErrors] = useState(record.errors);

    if (seenAnswer !== record.answer) {
        setSeenAnswer(record.answer);

        if (record.answer !== null) setConfirming(false);
    }

    if (seenErrors !== record.errors) {
        setSeenErrors(record.errors);

        if (Object.keys(record.errors).length > 0) setConfirming(false);
    }

    useEffect(() => { onUnsent(unsent); }, [unsent, onUnsent]);
    useEffect(() => { onBusy(frozen); }, [frozen, onBusy]);

    const send = () => {
        const lot = line.serialized
            ? serialRows.map((serial) => ({ lot: serial.lot, rak: serial.location, jumlah: '1', seri: serial.handle }))
            : lots.filter((one) => Number(figure(picks[placeOf(one.lot, one.location)] ?? '')) > 0).map((one) => ({ lot: one.lot, rak: one.location, jumlah: picks[placeOf(one.lot, one.location)] ?? '0' }));
        setSentAs(signature);
        record.submit({
            baris: line.handle, satuan: unit, jumlah: shown, lot, alasan_alokasi: anyOther ? reason : null,
            duplikat: warned ? acknowledged : [], alasan_duplikat: warned ? duplicateReason : null,
            kebijakan_seri: line.serialized, kebijakan_skala: line.scale, tanggal: date.date, tanggal_otomatis: !date.chosen,
        }, () => {
            setConfirming(false);
            setQuantity('');
            setTyped(false);
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

        const left = decimal.add(Number(base) === 0 ? figure(line.remaining) : base, `-${picked}`);
        setPicks({ ...picks, [key]: Number(left) <= 0 ? '0' : Number(lot.qty) < Number(left) ? lot.qty : left });
    };
    const blocked = warned && (!matches.every((match) => acknowledged.includes(match.id)) || duplicateReason.trim() === '');
    const unplaced = Object.entries(record.errors).filter(([field]) => !['jumlah', 'satuan', 'alasan_alokasi', 'alasan_duplikat'].includes(field)).slice(0, 1);

    return (
        <section data-slot="dispatch-line" data-line={line.no} hidden={hidden} className="flex min-w-0 flex-col gap-4">
            <h2 className="text-subhead">{`${String(line.no)}. ${line.name}`}</h2>
            <p className="text-body text-text-secondary">{t('dispatch.line.figures', { diminta: line.demand, direservasi: line.reserved, keluar: line.dispatched, sisa: line.remaining })}</p>
            {record.answer !== null && !matched && !confirming && <OutcomeMessage answer={record.answer} action={record.unknown ? <Button variant="outline" size="sm" onClick={record.retry}>{t('actions.try_again')}</Button> : undefined} />}
            {warned && (
                <DuplicateWarning matches={matches} message={record.answer?.text} acknowledged={acknowledged} onAcknowledge={setAcknowledged} canContinue
                    reason={{ value: duplicateReason, onChange: setDuplicateReason, error: record.errors.alasan_duplikat ?? null }} />
            )}
            <BusinessDateControl today={today} value={date} onChange={setDate} />
            <div className="flex min-w-0 flex-wrap items-end gap-3">
                <FormField label={t('dispatch.field.quantity')} className="w-40" error={record.errors.jumlah}>
                    {(control) => <DecimalInput {...control} scale={line.scale} disabled={frozen || closed} value={shown} onValueChange={(next) => { setQuantity(next ?? ''); setTyped(true); }} />}
                </FormField>
                {line.units.length > 1 && (
                    <FormField label={t('dispatch.field.unit')} className="w-40" error={record.errors.satuan}>
                        {(control) => (
                            <NativeSelect {...control} value={unit} disabled={frozen || closed} onChange={(event) => { setUnit(event.target.value); }}>
                                {line.units.map((one) => <option key={one.code} value={one.code}>{one.name}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                )}
            </div>
            {choices?.more === true && <ChoicesFind disabled={frozen || closed} onFind={onFind} />}
            {choices === null ? (failed ? (
                <p role="alert" className="flex min-w-0 flex-wrap items-center gap-3 text-body text-danger-fg">
                    {t('receiving.list.group_failed')}
                    <Button variant="outline" size="sm" onClick={onRetry}>{t('receiving.list.reload')}</Button>
                </p>
            ) : <RegionLoading />) : line.serialized ? (
                <fieldset className="flex min-w-0 flex-col gap-2" disabled={frozen || closed} data-slot="dispatch-serials">
                    <legend className="text-body font-medium">{t('dispatch.field.serials')}</legend>
                    {choices.serials.length === 0 && <p className="text-body text-text-secondary">{t('dispatch.line.no_stock')}</p>}
                    {choices.serials.map((serial) => (
                        <label key={serial.handle} className="flex min-h-floor min-w-0 items-center gap-2 text-body">
                            <Checkbox checked={chosen.includes(serial.handle)} disabled={frozen || closed} onCheckedChange={(checked) => { onChosen(checked ? [...chosen, serial.handle] : chosen.filter((other) => other !== serial.handle)); }} />
                            <span className="min-w-0 font-mono">{serial.serial}</span>
                            <span className="flex min-w-0 flex-wrap items-center gap-2 text-text-secondary"><Code>{serial.code}</Code>{serial.rack}{serial.other && <RelationMarker />}</span>
                        </label>
                    ))}
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
            {unplaced.map(([field, message]) => <p key={field} role="alert" className="text-body font-medium text-danger-fg">{message}</p>)}
            {!closed && Number(line.remaining) > 0 && (
                <Button className="self-start" size="lg" disabled={frozen || shown === '' || Number(picked) === 0 || blocked} pending={record.pending} onClick={() => { if (!warned) record.clear(); setConfirming(true); }}>{t('actions.record_dispatch')}</Button>
            )}
            <ConfirmCommand
                open={confirming && !record.unknown}
                onOpenChange={setConfirming}
                title={t('actions.record_dispatch')}
                consequence={t('dispatch.confirm.consequence', { jumlah: shown, satuan: unit })}
                confirmLabel={t('actions.record_dispatch')}
                pending={record.pending}
                onConfirm={send}
            />
        </section>
    );
}
