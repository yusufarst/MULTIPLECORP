// PG-018 *Stok* (ROUTE-018; NAV-018; SCR-18; D-IA-17; UXS-20; UXS-39; PJ-01; PJ-22; TASK-040): the pool per product, and per rack and condition — *Stok fisik* · *Direservasi* · *Tidak layak pakai* · **Tersedia** with its formula, the pending quantity on its own line (MSG-41), the restock advice linking to PG-017 —; the stock value of a holder's own companies, per company and never a total across them. `[both]`: scan or search first; the filter sheet's keys and the cursor in the address, a search term never (INT-007; IA §11).
// APPR-042's pilot (CR-002 K-1, K-2): one line says what the figures cover (PJ-01, PJ-22) and when they were read, with
// "Muat Ulang"; search and scan side by side; the view as one segmented control; the company values as compact cards
// above the list; each product named with its SKU, so two products sharing a name stay apart; the four quantities always
// through the project's own formatter with their unit — on the phone too —; a product's minimum and pending notes in one
// *Perhatian* column; the warehouse pages the account opens named beside the page. Every quantity is the server's: none
// is summed across products or units, converted or rounded here.
import { Deferred, Link, router, usePage, useRemember } from '@inertiajs/react';
import { RefreshCw, TriangleAlert } from 'lucide-react';
import { type ReactNode, useEffect, useId, useRef, useState } from 'react';

import { CompanyTag } from '@/components/company';
import { DataList, ListContinuation } from '@/components/data-list';
import { type AppliedFilter, FilterToolbar } from '@/components/filters';
import { FormField } from '@/components/form';
import { PageHeader } from '@/components/page-header';
import { Disclosure } from '@/components/record-blocks';
import { RegionEmpty, RegionNoMatch } from '@/components/region-state';
import { ScanField, ScanNotice, useScanGuard } from '@/components/scan-field';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Skeleton } from '@/components/ui/skeleton';
import { useDelayed } from '@/components/ui/spinner';
import { Code, Money, Quantity } from '@/components/values';
import { lookUp } from '@/features/inv/scan-client';
import { rememberStockList } from '@/features/inv/stock-list-address';
import AppShell from '@/layouts/AppShell';
import { clock, exact, quantity } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { type AccountHints, leavesOf, MODULES } from '@/lib/navigation';
import { cn } from '@/lib/utils';

type Figures = { physical: string; reserved: string; unusable: string; available: string; unit: string; alternate?: { physical: string; reserved: string; unusable: string; available: string; unit: string } };
type ProductRow = { id: string; name: string; sku: string | null; figures: Figures; pending: string | null; below: string | null; href: string };
type PlaceRow = { id: string; rack: string; name: string; sku: string | null; condition: 'USABLE' | 'UNUSABLE'; qty: string; unit: string; figures: Figures; href: string };
type Value = { company: string; name: string; value: string; exposures: { id: string; product: string; sku: string | null; qty: string; unit: string }[] };
type Filters = { saring: string | null; kondisi: 'USABLE' | 'UNUSABLE' | null };
type Props = {
    view: 'produk' | 'rak';
    filters: Filters;
    list: { rows: (ProductRow | PlaceRow)[]; more: string | null; cursor_failed: boolean };
    values?: Value[] | { failed: true };
    can: { purchase: boolean; move: boolean };
};
type Key = 'physical' | 'reserved' | 'unusable' | 'available';

const BELOW_MINIMUM = 'di_bawah_minimum';
const KEYS: Key[] = ['physical', 'reserved', 'unusable', 'available'];
const LABEL = { physical: 'glossary.gl_076', reserved: 'glossary.gl_077', unusable: 'glossary.gl_078', available: 'glossary.gl_079' } as const;

/** One of the four quantities in the product's unit, its alternate unit as a hint where the server gave one. */
const figure = (figures: Figures, key: Key) => <Quantity value={figures[key]} unit={figures.unit} alternate={figures.alternate === undefined ? undefined : { value: figures.alternate[key], unit: figures.alternate.unit }} />;

/** The same quantity in a table cell: the alternate hint on a second line, so four figures fit the desktop's width — the
    words and numbers exactly as the formatter gives them, nothing converted here. */
const cellFigure = (figures: Figures, key: Key) => (
    <span className="inline-flex flex-col items-end">
        <Quantity value={figures[key]} unit={figures.unit} />
        {figures.alternate !== undefined && <span className="text-caption text-text-tertiary tabular-nums">{`(${exact(figures.alternate[key])} ${figures.alternate.unit})`}</span>}
    </span>
);

/** Each company's stock value, one card per company, never a total across them (PJ-22); what waits for the Owner beneath. */
function Values({ values }: { values?: Value[] | { failed: true } }) {
    const { t } = useTranslation();
    const id = useId();

    if (values === undefined) return null;
    if ('failed' in values) {
        return (
            <section data-slot="stock-values" className="flex min-w-0 flex-col items-start gap-2">
                <p role="status" className="text-body text-text-secondary">{t('stock.group_failed')}</p>
                <Button variant="outline" size="sm" onClick={() => { router.reload({ only: ['values'] }); }}>{t('stock.reload')}</Button>
            </section>
        );
    }
    if (values.length === 0) return null;

    return (
        <section aria-labelledby={id} data-slot="stock-values" className="flex min-w-0 flex-col gap-3 motion-safe:animate-fade-in">
            <h2 id={id} className="text-heading-sm font-semibold text-text-primary">{t('stock.value.title')}</h2>
            <ul role="list" className="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                {values.map((value) => (
                    <li key={value.company} className="flex min-w-0 flex-col gap-1 rounded-xl border border-border bg-surface px-4 py-3">
                        <span className="flex min-w-0 flex-wrap items-center gap-2 text-label text-text-secondary">
                            <CompanyTag code={value.company} />
                            <span className="min-w-0 break-words">{value.name}</span>
                        </span>
                        <Money value={value.value} className="text-heading-sm font-semibold text-text-primary" />
                        {/* One line per product, by its identity; a name two products share says each one's SKU (TASK-096). */}
                        {value.exposures.map((exposure) => (
                            <span key={exposure.id} className="text-caption text-text-secondary">{`${exposure.sku !== null && value.exposures.some((other) => other.id !== exposure.id && other.product === exposure.product) ? `${exposure.product} · ${exposure.sku}` : exposure.product}: ${t('messages.msg_41_company', { jumlah: exact(exposure.qty), satuan: exposure.unit })}`}</span>
                        ))}
                    </li>
                ))}
            </ul>
        </section>
    );
}

/** The values' cards while they load: their shape after 300 ms, said once in words. */
function ValuesLoading() {
    const { t } = useTranslation();
    const shown = useDelayed(true);

    return (
        <div role="status" className="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            {shown && (
                <>
                    <span className="sr-only">{t('states.loading')}</span>
                    {[0, 1].map((card) => (
                        <div key={card} className="flex flex-col gap-2 rounded-xl border border-border bg-surface px-4 py-3">
                            <Skeleton className="h-3 w-2/5" />
                            <Skeleton className="h-5 w-3/5" />
                        </div>
                    ))}
                </>
            )}
        </div>
    );
}

export default function Stock({ view, filters, list, values, can }: Props) {
    const { t } = useTranslation();
    const page = usePage<{ auth: AccountHints }>();
    const address = page.url;
    const [shown, setShown] = useState(list.rows);
    const [seen, setSeen] = useState(list);
    const [appending, setAppending] = useState(false);
    const [code, setCode] = useState('');
    const [result, setResult] = useState<string | null>(null);
    const [scanError, setScanError] = useState<string | null>(null);
    const [term, setTerm] = useState('');
    const [searched, setSearched] = useState('');
    const [searching, setSearching] = useState(false);
    // When the figures on screen were read: a list is a reading, not a live feed. Kept in the page's history entry, so a
    // page brought back by "Kembali" says when its figures were read, never the moment it was restored.
    const [mountedAt] = useState(() => new Date().toISOString());
    const [readAt, setReadAt] = useRemember(mountedAt, 'stock.read_at');
    const scanRef = useRef<HTMLInputElement>(null);
    const guard = useScanGuard({ hasScanField: true });
    const gudang = MODULES.find((entry) => entry.key === 'gudang');
    const related = gudang === undefined ? [] : leavesOf(gudang, page.props.auth).filter((leaf) => leaf.pg !== 'PG-018');

    if (list !== seen) {
        setSeen(list);
        setShown(appending ? [...shown, ...list.rows] : list.rows);
        // "Muat Lagi" adds rows read now beneath rows read at readAt: the view is as old as its oldest rows, so the time
        // stays; a new list (a search, a filter, "Muat Ulang") is read now.
        if (!appending) setReadAt(new Date().toISOString());
        setAppending(false);
    }

    useEffect(() => { rememberStockList(address); }, [address]);

    const visit = (next: Record<string, string | null>) => {
        const query = Object.fromEntries(Object.entries({ tampil: view === 'rak' ? 'rak' : null, saring: filters.saring, kondisi: filters.kondisi, ...next }).filter(([, value]) => value !== null && value !== ''));
        setTerm('');
        setSearched('');
        router.get('/gudang/stok', query as Record<string, string>, { preserveScroll: true });
    };
    const search = (event: { preventDefault: () => void }) => {
        event.preventDefault();
        const typed = term.trim();
        setSearched(typed);
        setSearching(true);
        router.reload({ only: ['list'], data: { cari: typed, setelah: '' }, preserveUrl: true, onFinish: () => { setSearching(false); } });
    };
    const more = () => {
        if (list.more === null) return;
        setAppending(true);
        router.reload({ only: ['list'], data: searched === '' ? { setelah: list.more } : { setelah: list.more, cari: searched }, preserveUrl: searched !== '' });
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
    const applied: AppliedFilter[] = [
        ...(filters.saring === null ? [] : [{ id: 'saring', label: t('stock.filter.below_minimum'), onRemove: () => { visit({ saring: null }); } }]),
        ...(filters.kondisi === null ? [] : [{ id: 'kondisi', label: t(`stock.condition.${filters.kondisi}`), onRemove: () => { visit({ kondisi: null }); } }]),
    ];
    const segment = (current: boolean) => cn(
        'inline-flex min-h-10 items-center rounded-md px-3 text-label font-medium text-text-secondary outline-none transition-colors duration-(--mc-dur-hover) hover:bg-nav-hover hover:text-text-primary focus-visible:outline-2 focus-visible:outline-solid focus-visible:outline-focus pointer-coarse:min-h-touch',
        current && 'bg-sidebar-accent font-semibold text-text-primary hover:bg-sidebar-accent',
    );
    // The quantities on the phone's and tablet's row, each with its label and unit (never a bare number), wrapping
    // between words and never cut: a figure is read whole.
    const figuresLine = (figures: Figures, keys: Key[]) => (
        <span className="flex min-w-0 flex-wrap gap-x-3 gap-y-0.5">
            {keys.map((key) => (
                <span key={key} className="min-w-0">
                    {t(LABEL[key])} {figure(figures, key)}
                </span>
            ))}
        </span>
    );
    // The row's key figure beside its name, named as the table's column is.
    const labelled = (label: string, value: ReactNode) => (
        <span className="inline-flex items-baseline gap-1">
            <span className="text-m-meta font-normal text-text-tertiary">{label}</span>
            <strong className="font-semibold text-text-primary">{value}</strong>
        </span>
    );
    const noMatch = searched !== '' && shown.length === 0 && !list.cursor_failed;

    return (
        <AppShell title={t('pages.pg_018.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader
                    title={t('pages.pg_018.title')}
                    facts={
                        <span className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 text-caption text-text-tertiary">
                            <span>{t('stock.scope')}</span>
                            <span className="inline-flex items-center gap-2">
                                {t('states.updated_at', { waktu: clock(readAt) })}
                                <Button variant="ghost" size="sm" onClick={() => { visit({}); }}>
                                    <Icon icon={RefreshCw} size="sm" />
                                    {t('states.refresh')}
                                </Button>
                            </span>
                        </span>
                    }
                />
                {related.length > 0 && (
                    <>
                        {/* From the tablet up: one line of links, each at least 24 px tall (WCAG 2.5.8). */}
                        <nav aria-label={t('stock.related')} className="-mt-2 flex min-w-0 flex-wrap items-center gap-x-4 gap-y-1 text-label max-md:hidden">
                            <span className="text-text-tertiary">{t('stock.related')}</span>
                            {related.map((leaf) => (
                                <Link key={leaf.pg} href={leaf.href} className="inline-flex min-h-6 items-center font-medium text-text-primary underline decoration-border-strong underline-offset-2 hover:decoration-text-primary pointer-coarse:min-h-touch">
                                    {t(leaf.label)}
                                </Link>
                            ))}
                        </nav>
                        {/* On the phone: folded under its name, one 44 px row per page, so the page starts with the stock. */}
                        <div className="-mt-2 md:hidden">
                            <Disclosure title={t('stock.related')}>
                                <nav aria-label={t('stock.related')}>
                                    <ul role="list" className="flex flex-col divide-y divide-border-subtle">
                                        {related.map((leaf) => (
                                            <li key={leaf.pg}>
                                                <Link href={leaf.href} className="flex min-h-touch items-center text-body font-medium text-text-primary">
                                                    {t(leaf.label)}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                </nav>
                            </Disclosure>
                        </div>
                    </>
                )}
                <ScanNotice notice={guard.notice} />
                <div className="grid min-w-0 grid-cols-1 items-end gap-4 lg:grid-cols-2">
                    <form className="flex min-w-0 items-end gap-3" role="search" onSubmit={search}>
                        <FormField label={t('stock.search')} className="min-w-0 flex-1">
                            {(control) => <Input {...control} value={term} maxLength={100} onChange={(event) => { setTerm(event.target.value); }} />}
                        </FormField>
                        <Button type="submit" variant="outline" pending={searching} pendingLabel={t('states.loading')}>{t('stock.search_action')}</Button>
                    </form>
                    <ScanField inputRef={scanRef} value={code} onValueChange={setCode} onLookup={scanned} result={result} error={scanError} />
                </div>
                <div className="flex min-w-0 flex-wrap items-center gap-3">
                    <div role="group" aria-label={t('pages.pg_018.title')} className="flex min-w-0 flex-wrap gap-1 rounded-lg border border-border bg-surface p-1">
                        <button type="button" aria-pressed={view === 'produk'} className={segment(view === 'produk')} onClick={() => { visit({ tampil: null, kondisi: null }); }}>{t('stock.tab.products')}</button>
                        <button type="button" aria-pressed={view === 'rak'} className={segment(view === 'rak')} onClick={() => { visit({ tampil: 'rak', saring: null }); }}>{t('stock.tab.places')}</button>
                    </div>
                    <FilterToolbar
                        applied={applied}
                        onReset={() => { visit({ saring: null, kondisi: null }); }}
                        promoted={view === 'produk' ? (
                            <NativeSelect aria-label={t('stock.filter.label')} value={filters.saring ?? ''} onChange={(event) => { visit({ saring: event.target.value }); }}>
                                <option value="">{t('stock.filter.all')}</option>
                                <option value={BELOW_MINIMUM}>{t('stock.filter.below_minimum')}</option>
                            </NativeSelect>
                        ) : (
                            <NativeSelect aria-label={t('stock.filter.condition')} value={filters.kondisi ?? ''} onChange={(event) => { visit({ kondisi: event.target.value }); }}>
                                <option value="">{t('stock.filter.all_conditions')}</option>
                                <option value="USABLE">{t('stock.condition.USABLE')}</option>
                                <option value="UNUSABLE">{t('stock.condition.UNUSABLE')}</option>
                            </NativeSelect>
                        )}
                    />
                </div>
                <Deferred data="values" fallback={<ValuesLoading />}>
                    <Values values={values} />
                </Deferred>
                <section aria-label={view === 'produk' ? t('pages.pg_018.title') : t('stock.tab.places')} aria-busy={searching || undefined} className={cn('flex min-w-0 flex-col gap-3 transition-opacity duration-(--mc-dur-fast)', searching && 'opacity-60')}>
                    <p data-slot="stock-formula" className="text-caption text-text-secondary">{t('inventory.available_formula')}</p>
                    {view === 'rak' && <p data-slot="stock-place-note" className="text-caption text-text-tertiary">{t('stock.place.product_figures')}</p>}
                    {noMatch ? (
                        <RegionNoMatch term={searched} onClear={() => { visit({}); }} clearLabel={t('actions.reset')} />
                    ) : shown.length === 0 && !list.cursor_failed ? (
                        <RegionEmpty text={t('stock.empty')} />
                    ) : view === 'produk' ? (
                        <DataList<ProductRow>
                            denseBelow="lg"
                            caption={t('pages.pg_018.title')}
                            rows={shown as ProductRow[]}
                            rowKey={(row) => row.id}
                            rowHref={(row) => row.href}
                            columns={[
                                { key: 'name', header: t('stock.column.product'), wrap: true, cell: (row) => row.name, secondary: (row) => (row.sku === null ? null : <Code>{row.sku}</Code>) },
                                { key: 'physical', header: t('glossary.gl_076'), numeric: true, cell: (row) => cellFigure(row.figures, 'physical') },
                                { key: 'reserved', header: t('glossary.gl_077'), numeric: true, cell: (row) => cellFigure(row.figures, 'reserved') },
                                { key: 'unusable', header: t('glossary.gl_078'), numeric: true, cell: (row) => cellFigure(row.figures, 'unusable') },
                                { key: 'available', header: t('glossary.gl_079'), numeric: true, cell: (row) => <strong>{cellFigure(row.figures, 'available')}</strong> },
                                { key: 'attention', header: t('stock.column.attention'), cell: (row) => <StockNotes row={row} canPurchase={can.purchase} /> },
                            ]}
                            dense={(row) => ({
                                title: row.name,
                                figure: labelled(t('glossary.gl_079'), figure(row.figures, 'available')),
                                wrap: true,
                                secondary: (
                                    <span className="flex min-w-0 flex-col gap-1">
                                        {row.sku !== null && <Code className="text-text-tertiary">{row.sku}</Code>}
                                        {figuresLine(row.figures, ['physical', 'reserved', 'unusable'])}
                                        <StockNotes row={row} canPurchase={false} bare />
                                    </span>
                                ),
                            })}
                        />
                    ) : (
                        <DataList<PlaceRow>
                            denseBelow="lg"
                            caption={t('stock.tab.places')}
                            rows={shown as PlaceRow[]}
                            rowKey={(row) => `${row.rack}-${row.id}-${row.condition}`}
                            rowHref={(row) => row.href}
                            columns={[
                                { key: 'rack', header: t('stock.column.rack'), cell: (row) => row.rack },
                                { key: 'name', header: t('stock.column.product'), cell: (row) => <span className="flex min-w-0 flex-col"><span>{row.name}</span>{row.sku !== null && <Code className="text-caption text-text-tertiary">{row.sku}</Code>}</span> },
                                { key: 'condition', header: t('stock.column.condition'), cell: (row) => t(`stock.condition.${row.condition}`) },
                                { key: 'qty', header: t('stock.place.here'), numeric: true, cell: (row) => <Quantity value={row.qty} unit={row.unit} /> },
                                { key: 'physical', header: t('glossary.gl_076'), numeric: true, priority: 'low', cell: (row) => figure(row.figures, 'physical') },
                                { key: 'reserved', header: t('glossary.gl_077'), numeric: true, priority: 'low', cell: (row) => figure(row.figures, 'reserved') },
                                { key: 'unusable', header: t('glossary.gl_078'), numeric: true, priority: 'low', cell: (row) => figure(row.figures, 'unusable') },
                                { key: 'available', header: t('glossary.gl_079'), numeric: true, priority: 'low', cell: (row) => <strong>{figure(row.figures, 'available')}</strong> },
                                ...(can.move ? [{ key: 'move', header: '', cell: (row: PlaceRow) => <a className="underline" href={`${row.href}?pindah=1`}>{t('actions.move_location')}</a> }] : []),
                            ]}
                            dense={(row) => ({
                                title: row.name,
                                figure: labelled(t('stock.place.here'), <Quantity value={row.qty} unit={row.unit} />),
                                wrap: true,
                                secondary: (
                                    <span className="flex min-w-0 flex-col gap-1">
                                        {row.sku !== null && <Code className="text-text-tertiary">{row.sku}</Code>}
                                        <span>{`${row.rack} · ${t(`stock.condition.${row.condition}`)}`}</span>
                                        {figuresLine(row.figures, KEYS)}
                                    </span>
                                ),
                            })}
                        />
                    )}
                    {!noMatch && <ListContinuation state={list.cursor_failed ? 'cursor_failed' : list.more === null ? 'end' : 'more'} loading={appending} onLoadMore={more} onReload={() => { visit({}); }} />}
                </section>
            </div>
        </AppShell>
    );
}

/** *Perhatian*: below the minimum — the minimum in the product's unit, "Buat Pembelian" where the account may buy —, and a
    difference waiting for the Owner (MSG-41), each with its icon and words, never colour alone. */
function StockNotes({ row, canPurchase, bare = false }: { row: ProductRow; canPurchase: boolean; bare?: boolean }) {
    const { t } = useTranslation();

    // The table's cell says "nothing" with its dash; a phone row with nothing to note simply ends.
    if (row.pending === null && row.below === null) {
        return bare ? null : <span className="text-text-tertiary">—</span>;
    }

    return (
        <span className="flex min-w-0 flex-col gap-1 text-caption">
            {row.below !== null && (
                <span data-slot="stock-below" className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                    <span className="inline-flex items-center gap-1 rounded-xs bg-warning-bg px-2 py-0.5 font-medium text-warning-fg">
                        <Icon icon={TriangleAlert} size="xs" />
                        {t('inventory.below_minimum', { jumlah: quantity(row.below, row.figures.unit) })}
                    </span>
                    {canPurchase && <a className="font-medium text-text-primary underline underline-offset-2" href={`/pembelian/baru?produk=${row.id}`}>{t('stock.restock')}</a>}
                </span>
            )}
            {row.pending !== null && (
                <span data-slot="stock-pending" className="flex min-w-0 items-start gap-1 text-text-secondary">
                    <Icon icon={TriangleAlert} size="xs" className="mt-0.5 shrink-0 text-warning-fg" />
                    <span className="min-w-0">{t('messages.msg_41', { jumlah: exact(row.pending), satuan: row.figures.unit })}</span>
                </span>
            )}
        </span>
    );
}
