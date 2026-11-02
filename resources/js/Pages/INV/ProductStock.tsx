// PG-019 *Detail Stok Produk* (ROUTE-019; NAV-019; SCR-19; UXS-20; UXS-39; PJ-01–PJ-04; BR-INV-04; TASK-040): one product's four quantities, its racks, lots — "Ambil Lebih Dulu" on the oldest holding usable units, as a picking aid only —, serials with their presence, reservations and recent movements, each long list continued (PF-13; IP-18), each deferred group with its own error and retry. A lot of a company in scope names its source, its cost and flagged basis only with `cost.view` of that company; a lot outside scope ends at its code, quantity, rack and condition with the relation marker, and no link. "Ubah Kondisi", "Catat Penyesuaian Stok" and "Pindah Lokasi" appear with their capabilities (TASK-042); PG-027's success lands here with its answer (NAV-027).
import { Deferred, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { RelationMarker } from '@/components/company';
import { ListContinuation } from '@/components/data-list';
import { PageHeader } from '@/components/page-header';
import { OutcomeMessage } from '@/components/outcome-message';
import { InventoryBlock, type StockFigures } from '@/components/record-blocks';
import { RegionEmpty, RegionLoading } from '@/components/region-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Code, DateText, Money } from '@/components/values';
import { MoveSheet, type Moving } from '@/features/inv/move-sheet';
import { stockListAddress } from '@/features/inv/stock-list-address';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';

type Lot = { id: string; code: string; qty: string; rack: string; condition: 'USABLE' | 'UNUSABLE'; oldest: boolean; other: boolean; company?: string; kind?: string; date?: string; acquired?: string };
type Serial = { serial: string; lot: string; rack: string; condition: 'USABLE' | 'UNUSABLE' };
type Loaded = { lots: Lot[]; lots_more: string | null; serials: Serial[]; serials_more: string | null; cursor_failed: boolean };
type Group = Loaded | { failed: true };
type Cost = { cost: string; flagged: string | null };
type Movement = { id: string; direction: 'IN' | 'OUT'; type: string; date: string; qty: string; unit: string; rack: string; condition: string; lot: string | null };
type ReservationRow = { id: string; reserved: string; other: boolean; project: { label: string; href: string } | null };
type Reservations = { rows: ReservationRow[]; more: string | null; cursor_failed: boolean };
type Props = {
    product: { id: string; name: string; sku: string | null; figures: StockFigures; pending: string | null; below: string | null; serialized: boolean };
    places: { rack: string; condition: 'USABLE' | 'UNUSABLE'; qty: string }[];
    reservations: Reservations;
    can: { purchase: boolean; condition: boolean; adjust: boolean; move: boolean; count: boolean; return: boolean };
    moving?: Moving;
    today: string;
    scale: number;
};

function Failed({ group }: { group: 'group' | 'movements' }) {
    const { t } = useTranslation();

    return (
        <div className="flex min-w-0 flex-col items-start gap-2">
            <p role="status" className="text-body text-text-secondary">{t('stock.group_failed')}</p>
            <Button variant="outline" size="sm" onClick={() => { router.reload({ only: [group] }); }}>{t('stock.reload')}</Button>
        </div>
    );
}

function Lots() {
    const { t } = useTranslation();
    const { group, costs, can, product } = usePage().props as unknown as { group?: Group; costs?: Record<string, Cost> | { failed: true }; can: { condition: boolean; return: boolean }; product: { id: string } };
    const [seen, setSeen] = useState<Group | undefined>(undefined);
    const [loading, setLoading] = useState<'lots' | 'serials' | null>(null);
    const [lots, setLots] = useState<Lot[]>([]);
    const [serials, setSerials] = useState<Serial[]>([]);
    const [moreLots, setMoreLots] = useState<string | null>(null);
    const [moreSerials, setMoreSerials] = useState<string | null>(null);
    const [cursorFailed, setCursorFailed] = useState(false);

    if (group !== seen) {
        setSeen(group);

        if (group !== undefined && !('failed' in group)) {
            setLots(loading === 'lots' ? [...lots, ...group.lots] : loading === 'serials' ? lots : group.lots);
            setSerials(loading === 'serials' ? [...serials, ...group.serials] : loading === 'lots' ? serials : group.serials);
            setMoreLots(loading === 'serials' ? moreLots : group.lots_more);
            setMoreSerials(loading === 'lots' ? moreSerials : group.serials_more);
            setCursorFailed(group.cursor_failed);
        }

        setLoading(null);
    }

    if (group === undefined) return <RegionLoading />;
    if ('failed' in group) return <Failed group="group" />;

    const next = (list: 'lots' | 'serials', data: Record<string, string>) => {
        setLoading(list);
        router.reload({ only: ['group'], data, preserveUrl: true });
    };

    return (
        <>
            <section data-slot="product-lots" className="flex min-w-0 flex-col gap-2">
                <h2 className="text-subhead">{t('stock.section.lots')}</h2>
                {lots.length === 0 ? <RegionEmpty text={t('stock.empty_product')} /> : (
                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle text-body">
                        {lots.map((lot) => {
                            const cost = costs !== undefined && !('failed' in costs) ? costs[lot.id] : undefined;

                            return (
                                <li key={`${lot.id}-${lot.rack}-${lot.condition}`} data-slot="product-lot" className="flex min-w-0 flex-col gap-1 py-2">
                                    <span className="flex min-w-0 flex-wrap items-center gap-2">
                                        <Code>{lot.code}</Code>
                                        <span>{`${lot.qty} · ${lot.rack} · ${t(`stock.condition.${lot.condition}`)}`}</span>
                                        {lot.oldest && <Badge>{t('stock.lot.oldest')}</Badge>}
                                        {lot.other && <RelationMarker />}
                                        {can.condition && <a className="underline" href={`/gudang/penyesuaian?produk=${product.id}&aksi=${lot.condition === 'USABLE' ? 'kondisi' : 'periksa'}&lot=${lot.id}`}>{t(lot.condition === 'USABLE' ? 'actions.change_condition' : 'actions.reinspect')}</a>}
                                        {can.return && !lot.other && lot.kind === 'RECEIPT' && <a className="underline" href={`/gudang/retur-pemasok?produk=${product.id}&lot=${lot.id}`}>{t('pages.pg_028.title')}</a>}
                                    </span>
                                    {!lot.other && <span className="text-caption text-text-secondary">{t('stock.lot.source', { perusahaan: lot.company ?? '' })} · {t('stock.lot.acquired', { jenis: t(`stock.lot.kind.${lot.kind ?? 'RECEIPT'}` as TranslationKey), tanggal: lot.date ?? '', jumlah: lot.acquired ?? '' })}</span>}
                                    {cost !== undefined && <span className="flex flex-wrap items-center gap-2 text-caption text-text-secondary">{t('stock.lot.cost', { nilai: '' })}<Money value={cost.cost} />{cost.flagged !== null && <>{t('stock.lot.flagged', { nilai: '' })}<Money value={cost.flagged} /></>}</span>}
                                </li>
                            );
                        })}
                    </ul>
                )}
                {(moreLots !== null || cursorFailed) && <ListContinuation state={cursorFailed ? 'cursor_failed' : 'more'} loading={loading === 'lots'} onLoadMore={() => { if (moreLots !== null) next('lots', { lot_setelah: moreLots }); }} onReload={() => { router.reload({ only: ['group'] }); }} />}
            </section>
            {serials.length > 0 && (
                <section data-slot="product-serials" className="flex min-w-0 flex-col gap-2">
                    <h2 className="text-subhead">{t('stock.section.serials')}</h2>
                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle text-body">
                        {serials.map((serial) => (
                            <li key={serial.serial} className="flex min-w-0 flex-wrap items-center gap-2 py-2">
                                <span className="font-mono">{serial.serial}</span>
                                <span className="text-text-secondary">{`${t('stock.serial.present')} · ${serial.lot} · ${serial.rack} · ${t(`stock.condition.${serial.condition}`)}`}</span>
                                {can.condition && <a className="underline" href={`/gudang/penyesuaian?produk=${product.id}&aksi=${serial.condition === 'USABLE' ? 'kondisi' : 'periksa'}&temukan=${encodeURIComponent(serial.serial)}`}>{t(serial.condition === 'USABLE' ? 'actions.change_condition' : 'actions.reinspect')}</a>}
                            </li>
                        ))}
                    </ul>
                    {moreSerials !== null && <ListContinuation state="more" loading={loading === 'serials'} onLoadMore={() => { next('serials', { seri_setelah: moreSerials }); }} onReload={() => { router.reload({ only: ['group'] }); }} />}
                </section>
            )}
        </>
    );
}

function ReservationList({ reservations }: { reservations: Reservations }) {
    const { t } = useTranslation();
    const [seen, setSeen] = useState(reservations);
    const [shown, setShown] = useState(reservations.rows);
    const [appending, setAppending] = useState(false);

    if (reservations !== seen) {
        setSeen(reservations);
        setShown(appending ? [...shown, ...reservations.rows] : reservations.rows);
        setAppending(false);
    }

    if (shown.length === 0 && !reservations.cursor_failed) return null;

    return (
        <section data-slot="product-reservations" className="flex min-w-0 flex-col gap-2">
            <h2 className="text-subhead">{t('stock.section.reservations')}</h2>
            <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle text-body">
                {shown.map((reservation) => (
                    <li key={reservation.id} className="flex min-w-0 flex-wrap items-center gap-2 py-2">
                        <span>{reservation.reserved}</span>
                        {reservation.project !== null ? <a className="underline" href={reservation.project.href}>{reservation.project.label}</a> : reservation.other ? <span className="text-text-secondary">{t('projects.link.other_company')}</span> : null}
                    </li>
                ))}
            </ul>
            {(reservations.more !== null || reservations.cursor_failed) && <ListContinuation state={reservations.cursor_failed ? 'cursor_failed' : 'more'} loading={appending} onLoadMore={() => { if (reservations.more !== null) { setAppending(true); router.reload({ only: ['reservations'], data: { reservasi_setelah: reservations.more }, preserveUrl: true }); } }} onReload={() => { router.reload({ only: ['reservations'] }); }} />}
        </section>
    );
}

function Movements({ product }: { product: string }) {
    const { t } = useTranslation();
    const { movements } = usePage().props as unknown as { movements?: Movement[] | { failed: true } };

    if (movements === undefined) return <RegionLoading />;
    if ('failed' in movements) return <Failed group="movements" />;
    if (movements.length === 0) return null;

    return (
        <section data-slot="product-movements" className="flex min-w-0 flex-col gap-2">
            <h2 className="text-subhead">{t('stock.section.movements')}</h2>
            <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle text-body">
                {movements.map((movement) => (
                    <li key={movement.id} className="flex min-w-0 flex-wrap items-center gap-2 py-2">
                        <DateText value={movement.date} />
                        <span>{`${t(`stock.direction.${movement.direction}`)} · ${t(`stock.movement.${movement.type}` as TranslationKey)} · ${movement.qty} ${movement.unit} · ${movement.rack}`}</span>
                    </li>
                ))}
            </ul>
            <a className="self-start text-body underline" href={`/gudang/riwayat-stok?produk=${product}`}>{t('stock.history_link')}</a>
        </section>
    );
}

export default function ProductStock({ product, places, reservations, can, moving, today, scale }: Props) {
    const { t } = useTranslation();
    const [list] = useState(stockListAddress);
    const [wantsMove] = useState(() => new URLSearchParams(window.location.search).get('pindah') === '1');
    const [moveOpen, setMoveOpen] = useState(can.move && wantsMove);
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };
    // An optional prop is absent from the visit a refused move redirects to: the sheet keeps what it read (NV-04).
    const [known, setKnown] = useState<Moving | null>(null);

    if (moving !== undefined && known !== moving) {
        setKnown(moving);
    }

    const findUnits = (text: string, then: (units: Moving['units'] | null) => void) => {
        let found: Moving['units'] | null = null;

        router.reload({
            only: ['moving_found'],
            data: { cari_unit: text },
            onSuccess: (page) => { found = (page.props as unknown as { moving_found?: Moving['units'] }).moving_found ?? null; },
            onFinish: () => { then(found); },
        });
    };
    const openMove = () => {
        setMoveOpen(true);
        router.reload({ only: ['moving'] });
    };

    useEffect(() => {
        if (can.move && wantsMove) router.reload({ only: ['moving'] });
    }, [can.move, wantsMove]);

    return (
        <AppShell title={t('pages.pg_019.title')} back={{ href: list, label: t('pages.pg_018.title') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={product.name} crumbs={[{ label: t('pages.pg_018.title'), href: list }]} />
                {flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' && <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} />}
                {(can.condition || can.adjust || can.move) && (
                    <div data-slot="product-commands" className="flex min-w-0 flex-wrap gap-2">
                        {can.condition && <Button variant="outline" size="sm" render={<a href={`/gudang/penyesuaian?produk=${product.id}&aksi=kondisi`} />}>{t('actions.change_condition')}</Button>}
                        {can.adjust && <Button variant="outline" size="sm" render={<a href={`/gudang/penyesuaian?produk=${product.id}&aksi=penyesuaian`} />}>{t('actions.record_adjustment')}</Button>}
                        {can.move && <Button variant="outline" size="sm" onClick={openMove}>{t('actions.move_location')}</Button>}
                        {can.count && <Button variant="outline" size="sm" render={<a href={`/gudang/stok-opname?produk=${product.id}`} />}>{t('actions.start_count')}</Button>}
                    </div>
                )}
                <InventoryBlock figures={product.figures} belowMinimum={product.below ?? undefined} />
                {product.below !== null && can.purchase && <Button variant="outline" className="self-start" render={<a href={`/pembelian/baru?produk=${product.id}`} />}>{t('stock.restock')}</Button>}
                {product.pending !== null && <p data-slot="stock-pending" className="text-body text-text-secondary">{t('messages.msg_41', { jumlah: product.pending, satuan: product.figures.unit })}</p>}
                <section data-slot="product-places" className="flex min-w-0 flex-col gap-2">
                    <h2 className="text-subhead">{t('stock.section.places')}</h2>
                    {places.length === 0 ? <RegionEmpty text={t('stock.empty_product')} /> : (
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle text-body">
                            {places.map((place) => <li key={`${place.rack}-${place.condition}`} className="py-2">{`${place.rack} · ${t(`stock.condition.${place.condition}`)} · ${place.qty}`}</li>)}
                        </ul>
                    )}
                </section>
                <Deferred data="group" fallback={<RegionLoading />}>
                    <Lots />
                </Deferred>
                <ReservationList reservations={reservations} />
                <Deferred data="movements" fallback={<RegionLoading />}>
                    <Movements product={product.id} />
                </Deferred>
            </div>
            {can.move && <MoveSheet open={moveOpen} onOpenChange={setMoveOpen} product={product.id} serialized={product.serialized} scale={scale} moving={known} onFind={findUnits} today={today} onMoved={() => { setMoveOpen(false); }} />}
        </AppShell>
    );
}
