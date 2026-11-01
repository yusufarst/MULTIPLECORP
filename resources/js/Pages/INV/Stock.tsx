// PG-018 *Stok* (ROUTE-018; NAV-018; SCR-18; D-IA-17; UXS-20; UXS-39; PJ-01; PJ-22; TASK-040): the pool per product, and per rack and condition — *Stok fisik* · *Direservasi* · *Tidak layak pakai* · **Tersedia** with its formula, the pending quantity on its own line (MSG-41), the restock advice linking to PG-017 —; the stock value of a holder's own companies, per company and never a total across them. `[both]`: scan or search first.
import { Deferred, router } from '@inertiajs/react';
import { useRef, useState } from 'react';

import { DataList, ListContinuation } from '@/components/data-list';
import { FormField } from '@/components/form';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty, RegionLoading } from '@/components/region-state';
import { ScanField, ScanNotice, useScanGuard } from '@/components/scan-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Money, Quantity } from '@/components/values';
import { lookUp } from '@/features/inv/scan-client';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';

type Figures = { physical: string; reserved: string; unusable: string; available: string; unit: string; alternate?: { physical: string; reserved: string; unusable: string; available: string; unit: string } };
type ProductRow = { id: string; name: string; sku: string | null; figures: Figures; pending: string | null; below: string | null; href: string };
type PlaceRow = { id: string; rack: string; name: string; sku: string | null; condition: 'USABLE' | 'UNUSABLE'; qty: string; unit: string; href: string };
type Value = { company: string; name: string; value: string; exposures: { qty: string; unit: string }[] };
type Props = {
    view: 'produk' | 'rak';
    search: string | null;
    list: { rows: (ProductRow | PlaceRow)[]; more: string | null };
    values?: Value[] | { failed: true };
    can: { purchase: boolean };
};

const figure = (figures: Figures, key: 'physical' | 'reserved' | 'unusable' | 'available') => <Quantity value={figures[key]} unit={figures.unit} alternate={figures.alternate === undefined ? undefined : { value: figures.alternate[key], unit: figures.alternate.unit }} />;

function Values({ values }: { values?: Value[] | { failed: true } }) {
    const { t } = useTranslation();

    if (values === undefined || 'failed' in values || values.length === 0) return null;

    return (
        <section data-slot="stock-values" className="flex min-w-0 flex-col gap-2">
            <h2 className="text-subhead">{t('stock.value.title')}</h2>
            <ul role="list" className="flex min-w-0 flex-col gap-2 text-body">
                {values.map((value) => (
                    <li key={value.company} className="flex min-w-0 flex-col gap-1">
                        <span className="flex min-w-0 flex-wrap items-baseline gap-2"><span>{`${value.company} · ${value.name}`}</span><Money value={value.value} /></span>
                        {value.exposures.map((exposure) => <span key={exposure.unit} className="text-caption text-text-secondary">{t('messages.msg_41_company', { jumlah: exposure.qty.replace(/\.?0+$/, ''), satuan: exposure.unit })}</span>)}
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Stock({ view, search, list, values, can }: Props) {
    const { t } = useTranslation();
    const [shown, setShown] = useState(list.rows);
    const [seen, setSeen] = useState(list);
    const [appending, setAppending] = useState(false);
    const [code, setCode] = useState('');
    const [result, setResult] = useState<string | null>(null);
    const [scanError, setScanError] = useState<string | null>(null);
    const [term, setTerm] = useState(search ?? '');
    const scanRef = useRef<HTMLInputElement>(null);
    const guard = useScanGuard({ hasScanField: true });

    if (list !== seen) {
        setSeen(list);
        setShown(appending ? [...shown, ...list.rows] : list.rows);
        setAppending(false);
    }

    const visit = (next: Record<string, string | null>) => {
        const query = Object.fromEntries(Object.entries({ tampil: view === 'rak' ? 'rak' : null, cari: search, ...next }).filter(([, value]) => value !== null && value !== ''));
        router.get('/gudang/stok', query as Record<string, string>, { preserveScroll: true });
    };
    const more = () => {
        if (list.more === null) return;
        setAppending(true);
        router.reload({ only: ['list'], data: { setelah: list.more }, preserveUrl: true });
    };
    const scanned = (typed: string) => {
        setResult(null);
        setScanError(null);
        void lookUp(typed).then((scan) => {
            if (scan === null) {
                setScanError(t('messages.msg_06'));
            } else if (scan.kind === 'ambiguous') {
                setScanError(t('messages.msg_37', { kode: scan.code }));
            } else if (scan.kind === 'unknown' || scan.matches[0] === undefined) {
                setScanError(t('messages.msg_25', { kode: scan.code }));
            } else {
                router.visit(`/gudang/stok/${scan.matches[0].product}`);
            }
        });
    };

    return (
        <AppShell title={t('pages.pg_018.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_018.title')} actions={<Button variant="outline" render={<a href="/gudang/riwayat-stok" />}>{t('stock.history_link')}</Button>} />
                <ScanNotice notice={guard.notice} />
                <ScanField inputRef={scanRef} value={code} onValueChange={setCode} onLookup={scanned} result={result} error={scanError} />
                <form className="flex min-w-0 flex-wrap items-end gap-3" onSubmit={(event) => { event.preventDefault(); visit({ cari: term }); }}>
                    <FormField label={t('stock.search')} className="min-w-0 flex-1 sm:max-w-96">
                        {(control) => <Input {...control} value={term} maxLength={100} onChange={(event) => { setTerm(event.target.value); }} />}
                    </FormField>
                    <Button type="submit" variant="outline">{t('stock.search_action')}</Button>
                </form>
                <div className="flex min-w-0 flex-wrap gap-2" role="group">
                    <Button variant={view === 'produk' ? 'primary' : 'outline'} size="sm" aria-pressed={view === 'produk'} onClick={() => { visit({ tampil: null }); }}>{t('stock.tab.products')}</Button>
                    <Button variant={view === 'rak' ? 'primary' : 'outline'} size="sm" aria-pressed={view === 'rak'} onClick={() => { visit({ tampil: 'rak' }); }}>{t('stock.tab.places')}</Button>
                </div>
                <p data-slot="stock-formula" className="text-caption text-text-tertiary">{t('inventory.available_formula')}</p>
                {shown.length === 0 ? <RegionEmpty text={t('stock.empty')} /> : view === 'produk' ? (
                    <DataList<ProductRow>
                        caption={t('pages.pg_018.title')}
                        rows={shown as ProductRow[]}
                        rowKey={(row) => row.id}
                        rowHref={(row) => row.href}
                        columns={[
                            { key: 'name', header: t('stock.column.product'), cell: (row) => row.name },
                            { key: 'physical', header: t('glossary.gl_076'), numeric: true, cell: (row) => figure(row.figures, 'physical') },
                            { key: 'reserved', header: t('glossary.gl_077'), numeric: true, cell: (row) => figure(row.figures, 'reserved') },
                            { key: 'unusable', header: t('glossary.gl_078'), numeric: true, cell: (row) => figure(row.figures, 'unusable') },
                            { key: 'available', header: t('glossary.gl_079'), numeric: true, cell: (row) => <strong>{figure(row.figures, 'available')}</strong> },
                            { key: 'note', header: '', cell: (row) => <StockNotes row={row} canPurchase={can.purchase} /> },
                        ]}
                        dense={(row) => ({ title: row.name, secondary: <span className="flex flex-col gap-1"><span>{`${t('glossary.gl_076')} ${row.figures.physical} · ${t('glossary.gl_077')} ${row.figures.reserved} · ${t('glossary.gl_078')} ${row.figures.unusable}`}</span><StockNotes row={row} canPurchase={false} /></span>, status: figure(row.figures, 'available') })}
                    />
                ) : (
                    <DataList<PlaceRow>
                        caption={t('stock.tab.places')}
                        rows={shown as PlaceRow[]}
                        rowKey={(row) => `${row.rack}-${row.id}-${row.condition}`}
                        rowHref={(row) => row.href}
                        columns={[
                            { key: 'rack', header: t('stock.column.rack'), cell: (row) => row.rack },
                            { key: 'name', header: t('stock.column.product'), cell: (row) => row.name },
                            { key: 'condition', header: t('stock.column.condition'), cell: (row) => t(`stock.condition.${row.condition}`) },
                            { key: 'qty', header: t('stock.column.quantity'), numeric: true, cell: (row) => <Quantity value={row.qty} unit={row.unit} /> },
                        ]}
                        dense={(row) => ({ title: row.name, secondary: `${row.rack} · ${t(`stock.condition.${row.condition}`)}`, status: <Quantity value={row.qty} unit={row.unit} /> })}
                    />
                )}
                <ListContinuation state={list.more === null ? 'end' : 'more'} loading={appending} onLoadMore={more} onReload={more} />
                <Deferred data="values" fallback={<RegionLoading />}>
                    <Values values={values} />
                </Deferred>
            </div>
        </AppShell>
    );
}

function StockNotes({ row, canPurchase }: { row: ProductRow; canPurchase: boolean }) {
    const { t } = useTranslation();

    return (
        <span className="flex min-w-0 flex-col gap-1 text-caption text-text-secondary">
            {row.pending !== null && <span data-slot="stock-pending">{t('messages.msg_41', { jumlah: row.pending, satuan: row.figures.unit })}</span>}
            {row.below !== null && <span data-slot="stock-below" className="flex flex-wrap items-center gap-2">{t('inventory.below_minimum', { jumlah: row.below })}{canPurchase && <a className="underline" href={`/pembelian/baru?produk=${row.id}`}>{t('stock.restock')}</a>}</span>}
        </span>
    );
}
