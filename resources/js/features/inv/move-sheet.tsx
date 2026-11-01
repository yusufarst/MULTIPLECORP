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
import { lookUp } from '@/features/inv/scan-client';
import { useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Condition = 'USABLE' | 'UNUSABLE';
export type Moving = {
    units: { places: { lot: string; code: string; location: string; rack: string; condition: Condition; qty: string }[]; serials: { handle: string; serial: string; lot: string; location: string; condition: Condition }[] };
    racks: { id: string; code: string; name: string }[];
};

const placeKey = (place: { lot: string; location: string; condition: string }) => `${place.lot}|${place.location}|${place.condition}`;

export function MoveSheet({ open, onOpenChange, product, serialized, scale, moving, today, onMoved }: { open: boolean; onOpenChange: (open: boolean) => void; product: string; serialized: boolean; scale: number; moving: Moving | null; today: string; onMoved: () => void }) {
    const { t } = useTranslation();
    const [place, setPlace] = useState('');
    const [target, setTarget] = useState('');
    const [quantity, setQuantity] = useState('');
    const [chosen, setChosen] = useState<string[]>([]);
    const [code, setCode] = useState('');
    const [result, setResult] = useState<string | null>(null);
    const [scanError, setScanError] = useState<string | null>(null);
    const move = useCommand<Record<string, FormDataConvertible>>(() => `/gudang/stok/${product}/pindah`, 'post');
    const frozen = move.pending || move.unknown;
    const places = moving?.units.places ?? [];
    const from = places.find((one) => placeKey(one) === place);
    const serials = (moving?.units.serials ?? []).filter((one) => from !== undefined && one.lot === from.lot && one.location === from.location && one.condition === from.condition);
    const shown = serialized ? String(chosen.length) : quantity;
    const ready = from !== undefined && target !== '' && target !== from.location && shown !== '' && Number(shown) > 0;
    const scanned = (typed: string) => {
        setResult(null);
        setScanError(null);
        void lookUp(typed).then((scan) => {
            const match = scan?.matches[0];

            // A lot's label names the place to move from; a serial its unit.
            if (match?.kind === 'lot') {
                const lot = places.find((one) => one.code === match.lot);

                if (lot === undefined) {
                    setScanError(t('messages.msg_25', { kode: typed }));
                } else {
                    setPlace(placeKey(lot));
                    setResult(t('adjustments.scan.lot_opened', { kode: lot.code }));
                }

                return;
            }

            const number = match?.serial ?? typed;
            const row = (moving?.units.serials ?? []).find((one) => one.serial.toLowerCase() === number.toLowerCase());

            if (row === undefined) {
                setScanError(t('adjustments.scan.absent', { seri: number }));

                return;
            }

            // Units of one lot and condition: a unit from elsewhere starts the choice again.
            const same = placeKey(row) === place;
            setPlace(placeKey(row));
            setChosen((all) => (!same ? [row.handle] : all.includes(row.handle) ? all : [...all, row.handle]));
            setResult(t('receiving.scan.serial_added', { seri: number }));
        });
    };
    const send = () => {
        if (from === undefined) return;
        move.submit({ lot: from.lot, dari: from.location, ke: target, kondisi: from.condition, jumlah: shown, seri: chosen, kebijakan_seri: serialized, kebijakan_skala: scale, tanggal: today, tanggal_otomatis: true }, () => {
            setPlace('');
            setTarget('');
            setQuantity('');
            setChosen([]);
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
