// PG-019 *Detail Stok Produk* (ROUTE-019; NAV-019; SCR-19; UXS-20; UXS-39; PJ-01–PJ-04; BR-INV-04; TASK-040): one product's four quantities, its racks, lots — "Ambil Lebih Dulu" on the oldest as a picking aid only —, serials with their presence, reservations and recent movements. A lot of a company in scope names its source, its cost and flagged basis only with `cost.view` of that company; a lot outside scope ends at its code, quantity, rack and condition with the relation marker, and no link. The warehouse commands appear here with their capabilities once their routes exist (RC-06; TASK-042; TASK-043).
import { Deferred, usePage } from '@inertiajs/react';

import { RelationMarker } from '@/components/company';
import { PageHeader } from '@/components/page-header';
import { InventoryBlock, type StockFigures } from '@/components/record-blocks';
import { RegionEmpty, RegionLoading } from '@/components/region-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Code, DateText, Money } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';

type Lot = { id: string; code: string; qty: string; rack: string; condition: 'USABLE' | 'UNUSABLE'; oldest: boolean; other: boolean; company?: string; kind?: string; date?: string; acquired?: string };
type Serial = { serial: string; lot: string; rack: string; condition: 'USABLE' | 'UNUSABLE' };
type Group = { lots: Lot[]; serials: Serial[] } | { failed: true };
type Cost = { cost: string; flagged: string | null };
type Movement = { id: string; direction: 'IN' | 'OUT'; type: string; date: string; qty: string; unit: string; rack: string; condition: string; lot: string | null };
type Props = {
    product: { id: string; name: string; sku: string | null; figures: StockFigures; pending: string | null; below: string | null; serialized: boolean };
    places: { rack: string; condition: 'USABLE' | 'UNUSABLE'; qty: string }[];
    reservations: { id: string; reserved: string; project: { label: string; href: string } | null }[];
    movements: Movement[];
    can: { purchase: boolean };
};

function Lots() {
    const { t } = useTranslation();
    const { group, costs } = usePage().props as unknown as { group?: Group; costs?: Record<string, Cost> | { failed: true } };

    if (group === undefined) return <RegionLoading />;
    if ('failed' in group) return <p role="status" className="text-body text-text-secondary">{t('stock.group_failed')}</p>;

    return (
        <>
            <section data-slot="product-lots" className="flex min-w-0 flex-col gap-2">
                <h2 className="text-subhead">{t('stock.section.lots')}</h2>
                {group.lots.length === 0 ? <RegionEmpty text={t('stock.empty_product')} /> : (
                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle text-body">
                        {group.lots.map((lot) => {
                            const cost = costs !== undefined && !('failed' in costs) ? costs[lot.id] : undefined;

                            return (
                                <li key={`${lot.id}-${lot.rack}-${lot.condition}`} data-slot="product-lot" className="flex min-w-0 flex-col gap-1 py-2">
                                    <span className="flex min-w-0 flex-wrap items-center gap-2">
                                        <Code>{lot.code}</Code>
                                        <span>{`${lot.qty} · ${lot.rack} · ${t(`stock.condition.${lot.condition}`)}`}</span>
                                        {lot.oldest && <Badge>{t('stock.lot.oldest')}</Badge>}
                                        {lot.other && <RelationMarker />}
                                    </span>
                                    {!lot.other && <span className="text-caption text-text-secondary">{t('stock.lot.source', { perusahaan: lot.company ?? '' })} · {t('stock.lot.acquired', { jenis: t(`stock.lot.kind.${lot.kind ?? 'RECEIPT'}`), tanggal: lot.date ?? '', jumlah: lot.acquired ?? '' })}</span>}
                                    {cost !== undefined && <span className="flex flex-wrap items-center gap-2 text-caption text-text-secondary">{t('stock.lot.cost', { nilai: '' })}<Money value={cost.cost} />{cost.flagged !== null && <>{t('stock.lot.flagged', { nilai: '' })}<Money value={cost.flagged} /></>}</span>}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </section>
            {group.serials.length > 0 && (
                <section data-slot="product-serials" className="flex min-w-0 flex-col gap-2">
                    <h2 className="text-subhead">{t('stock.section.serials')}</h2>
                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle text-body">
                        {group.serials.map((serial) => (
                            <li key={serial.serial} className="flex min-w-0 flex-wrap items-center gap-2 py-2">
                                <span className="font-mono">{serial.serial}</span>
                                <span className="text-text-secondary">{`${t('stock.serial.present')} · ${serial.lot} · ${serial.rack} · ${t(`stock.condition.${serial.condition}`)}`}</span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </>
    );
}

export default function ProductStock({ product, places, reservations, movements, can }: Props) {
    const { t } = useTranslation();

    return (
        <AppShell title={t('pages.pg_019.title')} back={{ href: '/gudang/stok', label: t('pages.pg_018.title') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={product.name} crumbs={[{ label: t('pages.pg_018.title'), href: '/gudang/stok' }]} />
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
                {reservations.length > 0 && (
                    <section data-slot="product-reservations" className="flex min-w-0 flex-col gap-2">
                        <h2 className="text-subhead">{t('stock.section.reservations')}</h2>
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle text-body">
                            {reservations.map((reservation) => (
                                <li key={reservation.id} className="flex min-w-0 flex-wrap items-center gap-2 py-2">
                                    <span>{reservation.reserved}</span>
                                    {reservation.project === null ? <span className="text-text-secondary">{t('projects.link.other_company')}</span> : <a className="underline" href={reservation.project.href}>{reservation.project.label}</a>}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
                {movements.length > 0 && (
                    <section data-slot="product-movements" className="flex min-w-0 flex-col gap-2">
                        <h2 className="text-subhead">{t('stock.section.movements')}</h2>
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle text-body">
                            {movements.map((movement) => (
                                <li key={movement.id} className="flex min-w-0 flex-wrap items-center gap-2 py-2">
                                    <DateText value={movement.date} />
                                    <span>{`${t(`stock.direction.${movement.direction}`)} · ${t(`stock.movement.${movement.type}`)} · ${movement.qty} ${movement.unit} · ${movement.rack}`}</span>
                                </li>
                            ))}
                        </ul>
                        <a className="self-start text-body underline" href={`/gudang/riwayat-stok?produk=${product.id}`}>{t('stock.history_link')}</a>
                    </section>
                )}
            </div>
        </AppShell>
    );
}
