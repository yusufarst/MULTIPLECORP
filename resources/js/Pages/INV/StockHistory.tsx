// PG-020 *Riwayat Stok* (ROUTE-020; ROUTE-212; NAV-020; SCR-20; PJ-03; CM-28; IP-09; IP-20; TASK-040): the movement history itemized within scope — direction, type, date, product, lot code, rack, condition, quantity, serial, reason and actor —, no cost ever; each original and its reversal linked by "Dikoreksi oleh" and "Mengoreksi". "Balikkan" records the linked reversal with its reason, the cut where usable supply would fall below the reservations; "Balikkan Penerimaan" on a receipt (ROUTE-183). `[both]`, dense on the phone.
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { DataList, ListContinuation } from '@/components/data-list';
import { FormField } from '@/components/form';
import { ReservationCut, type Reservation } from '@/components/notices';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty, RegionLoading } from '@/components/region-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateText } from '@/components/values';
import { ReceiptReversal, type ReceiptReversalFigures } from '@/features/inv/receipt-reversal';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Link = { label: string; href: string };
type MovementLink = { movement: string; type: string; date: string; href: string };
const movementLabel = (type: string): TranslationKey => `stock.movement.${type}` as TranslationKey;
type Row = { id: string; movement: string; direction: 'IN' | 'OUT'; type: string; date: string; product: string; product_href: string; lot: string | null; rack: string; condition: 'USABLE' | 'UNUSABLE'; qty: string; unit: string; serial: string | null; reason: string | null; actor: string | null; company: string | null; project: Link | null; purchase: Link | null; reference: string | null; corrected_by: MovementLink | null; corrects: MovementLink | null; reversible: boolean; receipt: string | null };
type Cut = { movement: string; shortfall: string | null; may_cut: boolean; reservations: Reservation[] };
type Filters = { produk: string | null; jenis: string | null; dari: string | null; sampai: string | null; urut: string | null; gerakan: string | null };
type Figures = ReceiptReversalFigures & { handle: string };
type Props = { filters: Filters; list: { rows: Row[]; more: string | null; cursor_failed: boolean }; types: string[]; today: string; cut?: Cut | null; reversal?: Figures | null };

function Reverse({ movement, cut, today, onClose }: { movement: string; cut: Cut | null | undefined; today: string; onClose: () => void }) {
    const { t } = useTranslation();
    const [reason, setReason] = useState('');
    const [cuts, setCuts] = useState<Record<string, string>>({});
    const [known, setKnown] = useState<Cut | null>(null);
    const reverse = useCommand<Record<string, never>>(() => `/gudang/riwayat-stok/${movement}/balikkan`, 'post');
    const send = () => {
        const potong = Object.entries(cuts).filter(([, qty]) => Number(qty) > 0).map(([reservasi, jumlah]) => ({ reservasi, jumlah }));
        (reverse.submit as unknown as (value: unknown, done: () => void) => void)({ alasan: reason, potong, tanggal: today, tanggal_otomatis: true }, onClose);
    };

    // This movement's figures alone (IP-20); kept through the reload a refusal answers with, then read again.
    if (cut?.movement === movement && cut !== known) setKnown(cut);

    useEffect(() => {
        if (reverse.answer?.kind === 'REJECTED') router.reload({ only: ['cut'], data: { gerak: movement }, preserveUrl: true });
    }, [reverse.answer, movement]);

    const short = known?.shortfall ?? null;
    const fieldError = reverse.errors.tanggal ?? Object.entries(reverse.errors).find(([field]) => field.startsWith('potong'))?.[1] ?? null;

    return (
        <ConfirmCommand
            open
            onOpenChange={(next) => { if (!next) onClose(); }}
            title={t('stock.reverse.title')}
            consequence={t('stock.reverse.consequence')}
            confirmLabel={t('stock.reverse.action')}
            destructive
            pending={reverse.pending}
            answer={reverse.answer}
            blocked={known === null}
            reason={{ value: reason, onChange: setReason, error: reverse.errors.alasan ?? null }}
            onConfirm={send}
            items={(
                <div className="flex min-w-0 flex-col gap-3">
                    {known === null && <RegionLoading />}
                    {short !== null && (known?.may_cut === true
                        ? <ReservationCut shortfall={t('stock.reverse.shortfall', { kurang: short })} unit="" reservations={known.reservations} cuts={cuts} onCut={(id, value) => { setCuts({ ...cuts, [id]: value ?? '' }); }} scale={3} />
                        : <p role="alert" className="text-body font-medium text-danger-fg">{t('stock.reverse.no_cut', { kurang: short })}</p>)}
                    {fieldError !== null && <p role="alert" className="text-body font-medium text-danger-fg">{fieldError}</p>}
                </div>
            )}
        />
    );
}

export default function StockHistory({ filters, list, types, today, cut, reversal }: Props) {
    const { t } = useTranslation();
    const page = usePage();
    const flash = page.flash as { answer?: { kind?: string; text?: string } };
    const [draft, setDraft] = useState<Filters>(filters);
    const [shown, setShown] = useState(list.rows);
    const [seen, setSeen] = useState(list);
    const [appending, setAppending] = useState(false);
    const [reversing, setReversing] = useState<string | null>(null);
    const [receipt, setReceipt] = useState<string | null>(null);
    const [figures, setFigures] = useState<Figures | null>(null);

    if (list !== seen) {
        setSeen(list);
        setShown(appending ? [...shown, ...list.rows] : list.rows);
        setAppending(false);
    }

    // Kept through the reload a refusal answers with: the dialog stays open with its answer (IP-14).
    if (receipt !== null && reversal?.handle === receipt && reversal !== figures) setFigures(reversal);

    const visit = (next: Filters) => {
        router.get('/gudang/riwayat-stok', Object.fromEntries(Object.entries(next).filter(([, value]) => value !== null && value !== '')) as Record<string, string>, { preserveScroll: true });
    };
    const apply = (event: { preventDefault: () => void }) => {
        event.preventDefault();
        visit({ ...draft, gerakan: null });
    };
    const more = () => {
        if (list.more === null) return;
        setAppending(true);
        router.reload({ only: ['list'], data: { setelah: list.more } });
    };
    const openReverse = (movement: string) => {
        setReversing(movement);
        router.reload({ only: ['cut'], data: { gerak: movement }, preserveUrl: true });
    };
    const openReceipt = (handle: string) => {
        setReceipt(handle);
        setFigures(null);
        router.reload({ only: ['reversal'], data: { terima: handle }, preserveUrl: true });
    };
    const actions = (row: Row) => (
        <span className="flex min-w-0 flex-wrap items-center gap-2">
            {row.reversible && <Button variant="ghost" size="sm" onClick={() => { openReverse(row.movement); }}>{t('stock.reverse.action')}</Button>}
            {row.receipt !== null && <Button variant="ghost" size="sm" onClick={() => { if (row.receipt !== null) openReceipt(row.receipt); }}>{t('actions.reverse_receipt')}</Button>}
        </span>
    );
    const linked = (label: string, slot: string, link: MovementLink) => (
        <a data-slot={slot} className="underline" href={link.href}>{`${label} ${t(movementLabel(link.type))} · `}<DateText value={link.date} /></a>
    );
    const notes = (row: Row) => (
        <span className="flex min-w-0 flex-col gap-1 text-caption text-text-secondary">
            {row.reason !== null && <span className="whitespace-normal">{row.reason}</span>}
            {row.actor !== null && <span>{row.actor}</span>}
            {row.serial !== null && <span className="font-mono">{row.serial}</span>}
            {row.project !== null && <a className="underline" href={row.project.href}>{row.project.label}</a>}
            {row.purchase !== null && <a className="underline" href={row.purchase.href}>{row.purchase.label}</a>}
            {row.reference !== null && <span>{t('stock.history.reference', { nomor: row.reference })}</span>}
            {row.corrected_by !== null && linked(t('stock.history.corrected_by'), 'corrected-by', row.corrected_by)}
            {row.corrects !== null && linked(t('stock.history.corrects'), 'corrects', row.corrects)}
        </span>
    );

    return (
        <AppShell title={t('pages.pg_020.title')} back={{ href: '/gudang/stok', label: t('pages.pg_018.title') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_020.title')} crumbs={[{ label: t('pages.pg_018.title'), href: '/gudang/stok' }]} />
                {flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' && <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} />}
                <form className="flex min-w-0 flex-wrap items-end gap-3" onSubmit={apply}>
                    <FormField label={t('stock.history.filter_type')} className="min-w-0 sm:w-64">
                        {(control) => (
                            <NativeSelect {...control} value={draft.jenis ?? ''} onChange={(event) => { setDraft({ ...draft, jenis: event.target.value }); }}>
                                <option value="">{t('stock.history.all_types')}</option>
                                {types.map((type) => <option key={type} value={type}>{t(movementLabel(type))}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                    <FormField label={t('stock.history.from')} className="w-44">
                        {(control) => <Input {...control} type="date" value={draft.dari ?? ''} onChange={(event) => { setDraft({ ...draft, dari: event.target.value }); }} />}
                    </FormField>
                    <FormField label={t('stock.history.to')} className="w-44">
                        {(control) => <Input {...control} type="date" value={draft.sampai ?? ''} onChange={(event) => { setDraft({ ...draft, sampai: event.target.value }); }} />}
                    </FormField>
                    <FormField label={t('stock.history.sort')} className="w-40">
                        {(control) => (
                            <NativeSelect {...control} value={draft.urut ?? 'terbaru'} onChange={(event) => { setDraft({ ...draft, urut: event.target.value }); }}>
                                <option value="terbaru">{t('stock.history.newest')}</option>
                                <option value="terlama">{t('stock.history.oldest')}</option>
                            </NativeSelect>
                        )}
                    </FormField>
                    <Button type="submit" variant="outline">{t('stock.history.apply')}</Button>
                </form>
                {filters.gerakan !== null && <a className="self-start text-body underline" href="/gudang/riwayat-stok">{t('stock.history.all_movements')}</a>}
                {shown.length === 0 && !list.cursor_failed ? <RegionEmpty text={t('stock.history_empty')} /> : (
                    <DataList<Row>
                        caption={t('pages.pg_020.title')}
                        rows={shown}
                        rowKey={(row) => row.id}
                        rowHref={() => null}
                        columns={[
                            { key: 'date', header: t('stock.column.date'), cell: (row) => <DateText value={row.date} /> },
                            { key: 'type', header: t('stock.column.type'), cell: (row) => `${t(`stock.direction.${row.direction}`)} · ${t(movementLabel(row.type))}` },
                            { key: 'product', header: t('stock.column.product'), cell: (row) => <a className="underline" href={row.product_href}>{row.product}</a> },
                            { key: 'lot', header: t('stock.column.lot'), priority: 'low', cell: (row) => (row.lot === null ? '—' : <Code>{row.lot}</Code>) },
                            { key: 'place', header: t('stock.column.rack'), priority: 'low', cell: (row) => `${row.rack} · ${t(`stock.condition.${row.condition}`)}` },
                            { key: 'qty', header: t('stock.column.quantity'), numeric: true, cell: (row) => `${row.direction === 'OUT' ? '−' : '+'}${row.qty} ${row.unit}` },
                            { key: 'notes', header: t('stock.column.reason'), cell: (row) => notes(row) },
                            { key: 'actions', header: '', cell: (row) => actions(row) },
                        ]}
                        dense={(row) => ({ title: `${row.direction === 'OUT' ? '−' : '+'}${row.qty} ${row.unit} · ${row.product}`, secondary: <span className="flex flex-col gap-1 whitespace-normal"><span>{`${t(movementLabel(row.type))} · ${row.rack} · ${t(`stock.condition.${row.condition}`)}`}</span>{row.lot !== null && <Code>{row.lot}</Code>}{notes(row)}{actions(row)}</span>, status: <DateText value={row.date} /> })}
                    />
                )}
                <ListContinuation state={list.cursor_failed ? 'cursor_failed' : list.more === null ? 'end' : 'more'} loading={appending} onLoadMore={more} onReload={() => { visit(filters); }} />
            </div>
            {reversing !== null && <Reverse key={reversing} movement={reversing} cut={cut} today={today} onClose={() => { setReversing(null); }} />}
            {receipt !== null && figures?.handle === receipt && <ReceiptReversal key={receipt} handle={receipt} figures={figures} today={today} back={page.url} onClose={() => { setReceipt(null); }} />}
        </AppShell>
    );
}
