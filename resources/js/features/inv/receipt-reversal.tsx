// "Balikkan Penerimaan" (ROUTE-183; INT-183; AX-05; CM-02; CM-03; IP-09; IP-20), PG-016's and PG-020's: only the lot's unconsumed units, whole or the excess, a serialized lot serial by serial; MSG-40's reason; the actor's cut where usable supply would fall below the reservations. One per receipt, so an open intent is never retried against another (IP-02).
import { useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { DecimalInput, FormField } from '@/components/form';
import { ReservationCut, type Reservation } from '@/components/notices';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

export type ReceiptReversalFigures = {
    places: { location: string; code: string; condition: 'USABLE' | 'UNUSABLE'; qty: string }[];
    serials: { handle: string; serial: string; location: string; condition: 'USABLE' | 'UNUSABLE' }[];
    available: string;
    reservations: Reservation[];
    may_cut: boolean;
    consumed: boolean;
};

const plain = (value: string) => value.replace(/\.?0+$/, '') || '0';
const placeKey = (place: { location: string; condition: string }) => `${place.location}|${place.condition}`;

export function ReceiptReversal({ handle, figures, today, back, onClose }: { handle: string; figures: ReceiptReversalFigures; today: string; back: string; onClose: () => void }) {
    const { t } = useTranslation();
    const [reason, setReason] = useState('');
    const [quantities, setQuantities] = useState<Record<string, string>>(() => Object.fromEntries(figures.places.map((place) => [placeKey(place), plain(place.qty)])));
    const [cuts, setCuts] = useState<Record<string, string>>({});
    const [chosen, setChosen] = useState<string[]>([]);
    const reverse = useCommand<Record<string, never>>(() => `/gudang/penerimaan/${handle}/balikkan`, 'post');
    const frozen = reverse.pending || reverse.unknown;
    const serialized = figures.serials.length > 0;
    const serials = figures.serials.filter((serial) => chosen.includes(serial.handle));
    const usable = serialized
        ? serials.filter((serial) => serial.condition === 'USABLE').length
        : figures.places.filter((place) => place.condition === 'USABLE').reduce((sum, place) => sum + Number(quantities[placeKey(place)] ?? 0), 0);
    const shortfall = Math.max(0, usable - Number(figures.available));
    const send = () => {
        const tempat = serialized
            ? serials.map((serial) => ({ rak: serial.location, kondisi: serial.condition, jumlah: '1', seri: serial.handle }))
            : figures.places.filter((place) => Number(quantities[placeKey(place)] ?? 0) > 0).map((place) => ({ rak: place.location, kondisi: place.condition, jumlah: quantities[placeKey(place)] ?? '0' }));
        const potong = Object.entries(cuts).filter(([, qty]) => Number(qty) > 0).map(([reservasi, jumlah]) => ({ reservasi, jumlah }));
        (reverse.submit as unknown as (value: unknown, done: () => void) => void)({ tempat, potong, alasan: reason, tanggal: today, tanggal_otomatis: true, kembali: back }, onClose);
    };

    return (
        <ConfirmCommand
            open
            onOpenChange={(next) => { if (!next) onClose(); }}
            title={t('receiving.reverse.title')}
            consequence={figures.consumed ? t('receiving.reverse.consumed') : t('receiving.reverse.consequence')}
            confirmLabel={t('actions.reverse_receipt')}
            destructive
            pending={reverse.pending}
            answer={reverse.answer}
            reason={{ value: reason, onChange: setReason, error: reverse.errors.alasan ?? null }}
            blocked={serialized && chosen.length === 0}
            onConfirm={send}
            items={(
                <div className="flex min-w-0 flex-col gap-3">
                    {figures.consumed && <a className="self-start text-caption underline" href="/dokumen/kasus">{t('receiving.reverse.cases')}</a>}
                    {serialized ? (
                        <fieldset className="flex min-w-0 flex-col gap-2" disabled={frozen}>
                            <legend className="text-body font-medium">{t('receiving.reverse.serials')}</legend>
                            {chosen.length === 0 && <p className="text-caption text-text-secondary">{t('receiving.reverse.choose')}</p>}
                            {figures.serials.map((serial) => (
                                <label key={serial.handle} className="flex min-w-0 items-center gap-2 text-body">
                                    <Checkbox checked={chosen.includes(serial.handle)} disabled={frozen} onCheckedChange={(checked) => { setChosen((all) => (checked ? [...all, serial.handle] : all.filter((other) => other !== serial.handle))); }} />
                                    <span className="min-w-0 font-mono">{serial.serial}</span>
                                    <span className="min-w-0 text-text-secondary">{`${figures.places.find((place) => place.location === serial.location)?.code ?? ''} · ${t(serial.condition === 'USABLE' ? 'pages.pg_022.usable' : 'pages.pg_022.damaged')}`}</span>
                                </label>
                            ))}
                        </fieldset>
                    ) : figures.places.map((place) => (
                        <FormField key={placeKey(place)} label={`${place.code} · ${t(place.condition === 'USABLE' ? 'pages.pg_022.usable' : 'pages.pg_022.damaged')}`} help={t('receiving.reverse.held', { sisa: plain(place.qty) })}>
                            {(control) => <DecimalInput {...control} scale={3} disabled={frozen} value={quantities[placeKey(place)] ?? ''} onValueChange={(next) => { setQuantities({ ...quantities, [placeKey(place)]: next ?? '' }); }} />}
                        </FormField>
                    ))}
                    {reverse.errors.tempat !== undefined && <p role="alert" className="text-body font-medium text-danger-fg">{reverse.errors.tempat}</p>}
                    {shortfall > 0 && (figures.may_cut
                        ? <ReservationCut shortfall={t('receiving.reverse.shortfall', { kurang: String(shortfall) })} unit="" reservations={figures.reservations} cuts={cuts} onCut={(id, value) => { setCuts({ ...cuts, [id]: value ?? '' }); }} scale={3} />
                        : <p role="alert" className="text-body font-medium text-danger-fg">{t('receiving.reverse.no_cut', { kurang: String(shortfall) })}</p>)}
                </div>
            )}
        />
    );
}
