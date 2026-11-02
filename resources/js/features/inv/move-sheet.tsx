// "Pindah Lokasi" (ROUTE-190; INT-190; NX-05; L-36; J-MD-05), a phone flow with the scan field: the same units of one lot and condition leaving one rack and arriving at another, in place; no reservation or cost changes, and a wrong move is corrected by the opposite one.
import type { FormDataConvertible } from '@inertiajs/core';
import { useState } from 'react';

import { DecimalInput, FormField } from '@/components/form';
import { OutcomeMessage } from '@/components/outcome-message';
import { RegionLoading } from '@/components/region-state';
import { ScanField } from '@/components/scan-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { NativeSelect } from '@/components/ui/native-select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { ChoicesFind } from '@/features/inv/dispatch-line';
import { lookUp } from '@/features/inv/scan-client';
import { useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Condition = 'USABLE' | 'UNUSABLE';
export type Moving = {
    units: { places: { lot: string; code: string; location: string; rack: string; condition: Condition; qty: string }[]; serials: { handle: string; serial: string; lot: string; location: string; condition: Condition }[]; more: boolean };
    racks: { id: string; code: string; name: string }[];
};

const placeKey = (place: { lot: string; location: string; condition: string }) => `${place.lot}|${place.location}|${place.condition}`;
const latest = <Row,>(rows: Row[], key: (row: Row) => string): Row[] => [...new Map(rows.map((row) => [key(row), row])).values()];

export function MoveSheet({ open, onOpenChange, product, serialized, scale, moving, onFind, today, onMoved }: { open: boolean; onOpenChange: (open: boolean) => void; product: string; serialized: boolean; scale: number; moving: Moving | null; onFind: (text: string, then: (units: Moving['units'] | null) => void) => void; today: string; onMoved: () => void }) {
    const { t } = useTranslation();
    const [place, setPlace] = useState('');
    const [target, setTarget] = useState('');
    const [quantity, setQuantity] = useState('');
    const [chosen, setChosen] = useState<string[]>([]);
    const [code, setCode] = useState('');
    const [result, setResult] = useState<string | null>(null);
    const [scanError, setScanError] = useState<string | null>(null);
    // What a search found past the first page, kept beside it until the move lands (NV-04).
    const [found, setFound] = useState<Moving['units'][]>([]);
    const [finding, setFinding] = useState(false);
    const move = useCommand<Record<string, FormDataConvertible>>(() => `/gudang/stok/${product}/pindah`, 'post');
    const frozen = move.pending || move.unknown || finding;
    const places = latest([...(moving?.units.places ?? []), ...found.flatMap((units) => units.places)], placeKey);
    const allSerials = latest([...(moving?.units.serials ?? []), ...found.flatMap((units) => units.serials)], (one) => one.handle);
    const from = places.find((one) => placeKey(one) === place);
    const serials = allSerials.filter((one) => from !== undefined && one.lot === from.lot && one.location === from.location && one.condition === from.condition);
    const shown = serialized ? String(chosen.length) : quantity;
    const ready = from !== undefined && target !== '' && target !== from.location && shown !== '' && Number(shown) > 0;
    const find = (text: string, then: (units: Moving['units'] | null) => void) => {
        setFinding(true);
        onFind(text, (units) => {
            setFinding(false);

            if (units !== null) setFound((all) => [...all, units]);

            then(units);
        });
    };
    const openLot = (code: string, rows: Moving['units']['places']): boolean => {
        const lot = rows.find((one) => one.code === code);

        if (lot === undefined) return false;

        // Another place starts the choice again: the units chosen were of the place left.
        if (placeKey(lot) !== place) setChosen([]);

        setPlace(placeKey(lot));
        setResult(t('adjustments.scan.lot_opened', { kode: lot.code }));

        return true;
    };
    const addSerial = (number: string, rows: Moving['units']['serials']): boolean => {
        const row = rows.find((one) => one.serial.toLowerCase() === number.toLowerCase());

        if (row === undefined) return false;

        // Units of one lot and condition: a unit from elsewhere starts the choice again.
        const same = placeKey(row) === place;
        setPlace(placeKey(row));
        setChosen((all) => (!same ? [row.handle] : all.includes(row.handle) ? all : [...all, row.handle]));
        setResult(t('receiving.scan.serial_added', { seri: number }));

        return true;
    };
    const scanned = (typed: string) => {
        setResult(null);
        setScanError(null);
        void lookUp(typed).then((scan) => {
            const match = scan?.matches[0];
            // A lot's label names the place to move from; a serial its unit.
            const lotCode = match?.kind === 'lot' ? match.lot : undefined;
            const number = match?.serial ?? typed;
            const settle = (units: Pick<Moving['units'], 'places' | 'serials'>) => (lotCode !== undefined ? openLot(lotCode, units.places) : addSerial(number, units.serials));
            const absent = () => { setScanError(lotCode !== undefined ? t('messages.msg_25', { kode: typed }) : t('adjustments.scan.absent', { seri: number })); };

            if (settle({ places, serials: allSerials })) return;

            // The first page is not all of it: ask the server before calling the unit absent.
            if (moving?.units.more === true) {
                find(lotCode ?? number, (units) => { if (units === null || !settle(units)) absent(); });
            } else {
                absent();
            }
        });
    };
    const send = () => {
        if (from === undefined) return;
        move.submit({ lot: from.lot, dari: from.location, ke: target, kondisi: from.condition, jumlah: shown, seri: chosen, kebijakan_seri: serialized, kebijakan_skala: scale, tanggal: today, tanggal_otomatis: true }, () => {
            setPlace('');
            setTarget('');
            setQuantity('');
            setChosen([]);
            setFound([]);
            onMoved();
        });
    };
    const unplaced = Object.entries(move.errors).slice(0, 1);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t('actions.move_location')}</SheetTitle>
                    <SheetDescription>{t('adjustments.move.line')}</SheetDescription>
                </SheetHeader>
                {moving === null ? <RegionLoading /> : (
                    <div data-slot="move-form" className="flex min-w-0 flex-col gap-4">
                        {move.answer !== null && <OutcomeMessage answer={move.answer} action={move.unknown ? <Button variant="outline" size="sm" onClick={move.retry}>{t('actions.try_again')}</Button> : undefined} />}
                        {<ScanField inputRef={undefined} value={code} onValueChange={setCode} onLookup={scanned} result={result} error={scanError} />}
                        {moving.units.more && <ChoicesFind disabled={frozen} onFind={(text) => { setResult(null); setScanError(null); find(text, (units) => { if (units === null || (units.places.length === 0 && units.serials.length === 0)) setScanError(t('messages.msg_25', { kode: text })); }); }} />}
                        <FormField label={t('adjustments.move.from')}>
                            {(control) => (
                                <NativeSelect {...control} value={place} disabled={frozen} onChange={(event) => { setPlace(event.target.value); setChosen([]); }}>
                                    <option value="">{t('dispatch.substitute.choose')}</option>
                                    {places.map((one) => <option key={placeKey(one)} value={placeKey(one)}>{`${one.code} · ${one.rack} · ${t(`stock.condition.${one.condition}`)} · ${one.qty}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        <FormField label={t('adjustments.move.to')} error={move.errors.ke}>
                            {(control) => (
                                <NativeSelect {...control} value={target} disabled={frozen} onChange={(event) => { setTarget(event.target.value); }}>
                                    <option value="">{t('dispatch.substitute.choose')}</option>
                                    {moving.racks.filter((rack) => rack.id !== from?.location).map((rack) => <option key={rack.id} value={rack.id}>{`${rack.code} · ${rack.name}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        {serialized ? (
                            <fieldset disabled={frozen} className="flex min-w-0 flex-col gap-2">
                                <legend className="text-body font-medium">{t('adjustments.field.serials')}</legend>
                                {serials.map((serial) => (
                                    <label key={serial.handle} className="flex min-h-floor items-center gap-2 text-body">
                                        <Checkbox checked={chosen.includes(serial.handle)} onCheckedChange={(checked) => { setChosen(checked ? [...chosen, serial.handle] : chosen.filter((other) => other !== serial.handle)); }} />
                                        <span className="font-mono">{serial.serial}</span>
                                    </label>
                                ))}
                            </fieldset>
                        ) : (
                            <FormField label={t('adjustments.move.quantity')} error={move.errors.jumlah}>
                                {(control) => <DecimalInput {...control} scale={scale} disabled={frozen} value={quantity} onValueChange={(next) => { setQuantity(next ?? ''); }} />}
                            </FormField>
                        )}
                        {unplaced.filter(([field]) => field !== 'ke' && field !== 'jumlah').map(([field, message]) => <p key={field} role="alert" className="text-body font-medium text-danger-fg">{message}</p>)}
                        <Button className="self-start" size="lg" disabled={!ready || frozen} pending={move.pending} onClick={send}>{t('actions.move_location')}</Button>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
