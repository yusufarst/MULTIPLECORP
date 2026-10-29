// PG-025 *Reservasi* (ROUTE-025; NAV-025; SCR-23; J-INV-02; WF-INV-02; TASK-038): the reservations of one company at a
// time — a branch of the account's scope (CS-08) —, each row its project and line, product, quantity and state; the tabs
// *Aktif*, *Perlu dilepas* — the residual reservations of completed or cancelled projects (QS-16) — and *Riwayat*; the
// sort, the product's pool and the cursor in the address, so Back and reload return them (IP-18). A product's pool shows
// another company's reservation by its quantity and the relation marker only (PJ-04). "Reservasi", "Kurangi Reservasi"
// and "Lepas" from the row's actions, the last two with their reason (AX-01; AX-02); the phone reads the rows (`[desk]`).
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { RelationMarker } from '@/components/company';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { DataList, ListContinuation } from '@/components/data-list';
import { DecimalInput, FormField } from '@/components/form';
import { OutcomeMessage } from '@/components/outcome-message';
import { type MenuAction, PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Tabs, TabsList, TabsTab } from '@/components/ui/tabs';
import { Code } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { quantity as formatQuantity } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Branch = { id: string; code: string; name: string };
type Tab = 'aktif' | 'dilepas' | 'riwayat';
type Sort = 'terbaru' | 'proyek' | 'produk';
type Row = {
    key: string;
    handle: string | null;
    state: 'ACTIVE' | 'RELEASED' | 'CONSUMED';
    quantity: string;
    unit: string;
    product: string;
    pool: string;
    company: string | null;
    project: { id: string; number: string; title: string; href: string } | null;
    line: { no: number; handle: string } | null;
    residual: boolean;
    may: { reserve: boolean; reduce: boolean; release: boolean };
};
type Filters = { tab: Tab; urut: Sort; produk: string | null };
type Props = { branches: Branch[]; branch: string | null; filters: Filters; product: { handle: string; name: string } | null; rows: Row[]; more: string | null; can: { reserve: boolean } };
type Action = { kind: 'reserve' | 'reduce' | 'release'; row: Row };
type Payload = Record<string, string | boolean>;

const LIST = '/gudang/reservasi';
const amount = (value: string, unit: string): string => formatQuantity(value.replace(/\.?0+$/, '') || '0', unit);

/** A command's success on the page its answer opened, once (IP-01; NAV-025). */
function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

export default function Reservations({ branches, branch, filters, product, rows, more }: Props) {
    const { t } = useTranslation();
    // PT-08: "Muat Lagi" adds the page after the rows shown on a partial reload; any other visit shows its first page.
    const [base, setBase] = useState(rows);
    const [shown, setShown] = useState(rows);
    const [next, setNext] = useState(more);
    const [appending, setAppending] = useState(false);
    const [action, setAction] = useState<Action | null>(null);
    const [quantity, setQuantity] = useState('');
    const [reason, setReason] = useState('');
    const reserve = useCommand<Payload>(() => LIST, 'post');
    const reduce = useCommand<Payload>(() => `${LIST}/${action?.row.handle ?? ''}/kurangi`, 'post');

    if (base !== rows) {
        setBase(rows);
        setShown(appending ? [...shown, ...rows.filter((row) => !shown.some((known) => known.key === row.key))] : rows);
        setNext(more);
        setAppending(false);
    }

    // The address carries the branch, tab, sort and product; a change starts at the first page (IP-18).
    const visit = (patch: Partial<Filters & { cabang: string }>) => {
        const query = { cabang: branch ?? '', tab: filters.tab, urut: filters.urut, produk: filters.produk ?? '', ...patch };
        const params = new URLSearchParams(Object.entries(query).filter(([, value]) => value !== '' && value !== null) as [string, string][]);
        router.get(`${LIST}?${params.toString()}`, {}, { preserveScroll: true, preserveState: true });
    };
    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        router.reload({ only: ['rows', 'more'], data: { setelah: next }, preserveUrl: true });
    };
    const open = (kind: Action['kind'], row: Row) => {
        [reserve, reduce].forEach((command) => { command.clear(); });
        setQuantity('');
        setReason('');
        setAction({ kind, row });
    };
    const actions = (row: Row): MenuAction[] => [
        ...(row.may.reserve ? [{ label: t('actions.reserve'), onSelect: () => { open('reserve', row); } }] : []),
        ...(row.may.reduce ? [{ label: t('actions.reduce_reservation'), onSelect: () => { open('reduce', row); } }] : []),
        ...(row.may.release ? [{ label: t('actions.release'), onSelect: () => { open('release', row); } }] : []),
        ...(product === null ? [{ label: t('reservations.action.pool'), onSelect: () => { visit({ produk: row.pool }); } }] : []),
    ];
    const status = (row: Row) => (row.residual ? <span className="text-label text-text-secondary">{t('reservations.residual')}</span> : <StatusBadge family="reservation" state={row.state} size="sm" />);
    const project = (row: Row) =>
        row.project === null ? (
            <RelationMarker />
        ) : (
            <span className="flex min-w-0 flex-col">
                <Code>{row.project.number}</Code>
                <span className="truncate text-body text-text-secondary">{row.project.title}</span>
            </span>
        );
    const close = (opened: boolean) => { if (!opened) setAction(null); };
    const row = action?.row ?? null;
    const lineNo = row?.line?.no ?? 0;
    const empty = branches.length === 0 ? t('reservations.empty_scope') : t(`reservations.list.empty.${filters.tab}` as TranslationKey);

    return (
        <AppShell title={t('pages.pg_025.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_025.title')} />
                <PageAnswer />
                {[reserve, reduce].map((command, index) => (command.answer === null || command.answer.kind === 'COMMITTED' || action !== null ? null : <OutcomeMessage key={index} answer={command.answer} />))}
                <div className="flex min-w-0 flex-col gap-4 md:flex-row md:flex-wrap md:items-end">
                    {branches.length > 1 && (
                        <FormField label={t('documents.field.branch')} className="min-w-0 md:w-80">
                            {(control) => (
                                <NativeSelect {...control} value={branch ?? ''} onChange={(event) => { visit({ cabang: event.target.value }); }}>
                                    {branches.map((option) => <option key={option.id} value={option.id}>{`${option.code} · ${option.name}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}
                    <FormField label={t('adm.field.sort')} className="min-w-0 md:w-56">
                        {(control) => (
                            <NativeSelect {...control} value={filters.urut} onChange={(event) => { visit({ urut: event.target.value as Sort }); }}>
                                {(['terbaru', 'proyek', 'produk'] as const).map((sort) => <option key={sort} value={sort}>{t(`reservations.list.sort.${sort}` as TranslationKey)}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                    {product !== null && (
                        <div className="flex min-w-0 flex-wrap items-center gap-3">
                            <span className="min-w-0 text-body text-text-primary">{t('reservations.filter.product', { nama: product.name })}</span>
                            <Button variant="outline" size="sm" onClick={() => { visit({ produk: null }); }}>
                                {t('reservations.filter.clear')}
                            </Button>
                        </div>
                    )}
                </div>
                <Tabs value={filters.tab} onValueChange={(value: unknown) => { visit({ tab: value === 'dilepas' || value === 'riwayat' ? value : 'aktif' }); }}>
                    <TabsList>
                        {(['aktif', 'dilepas', 'riwayat'] as const).map((tab) => <TabsTab key={tab} value={tab}>{t(`reservations.list.tab.${tab}` as TranslationKey)}</TabsTab>)}
                    </TabsList>
                </Tabs>
                {shown.length === 0 ? (
                    <RegionEmpty text={empty} />
                ) : (
                    <>
                        <DataList<Row>
                            caption={t('pages.pg_025.title')}
                            rows={shown}
                            rowKey={(item) => item.key}
                            rowHref={(item) => item.project?.href ?? null}
                            rowActions={(item) => actions(item)}
                            columns={[
                                { key: 'project', header: t('reservations.field.project'), cell: project },
                                { key: 'line', header: t('reservations.field.line'), cell: (item) => (item.line === null ? '' : t('reservations.line_no', { n: item.line.no })) },
                                { key: 'product', header: t('reservations.field.product'), cell: (item) => item.product },
                                { key: 'company', header: t('projects.field.company'), priority: 'low', cell: (item) => item.company ?? '' },
                                { key: 'quantity', header: t('reservations.field.reserved'), numeric: true, cell: (item) => amount(item.quantity, item.unit) },
                                { key: 'status', header: t('adm.field.status'), cell: status },
                            ]}
                            dense={(item) => ({
                                title: item.project === null ? <RelationMarker /> : `${item.project.number} · ${t('reservations.line_no', { n: item.line?.no ?? 0 })}`,
                                figure: amount(item.quantity, item.unit),
                                secondary: item.company === null ? item.product : `${item.company} · ${item.product}`,
                                status: status(item),
                            })}
                        />
                        <ListContinuation state={next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={loadMore} />
                    </>
                )}
                <ConfirmCommand
                    open={action?.kind === 'reserve'}
                    onOpenChange={close}
                    title={t('reservations.reserve.title', { n: lineNo })}
                    consequence={t('reservations.reserve.consequence')}
                    items={
                        <FormField label={t('reservations.field.quantity', { satuan: row?.unit ?? '' })} required error={reserve.errors.jumlah}>
                            {(control) => <DecimalInput {...control} value={quantity} scale={3} unit={row?.unit} onValueChange={(value) => { setQuantity(value ?? ''); }} />}
                        </FormField>
                    }
                    confirmLabel={t('actions.reserve')}
                    pending={reserve.pending}
                    answer={reserve.answer}
                    onConfirm={() => {
                        if (row === null || row.project === null || row.line === null) return;
                        reserve.submit({ proyek: row.project.id, baris: row.line.handle, jumlah: quantity }, () => { setAction(null); });
                    }}
                />
                <ConfirmCommand
                    open={action?.kind === 'reduce'}
                    onOpenChange={close}
                    title={t('reservations.reduce.title', { n: lineNo })}
                    consequence={t('reservations.reduce.consequence', { jumlah: row === null ? '' : amount(row.quantity, row.unit) })}
                    items={
                        <FormField label={t('reservations.field.reduce_quantity', { satuan: row?.unit ?? '' })} required error={reduce.errors.jumlah}>
                            {(control) => <DecimalInput {...control} value={quantity} scale={3} unit={row?.unit} onValueChange={(value) => { setQuantity(value ?? ''); }} />}
                        </FormField>
                    }
                    reason={{ value: reason, onChange: setReason, error: reduce.errors.alasan ?? null }}
                    confirmLabel={t('actions.reduce_reservation')}
                    pending={reduce.pending}
                    answer={reduce.answer}
                    onConfirm={() => { reduce.submit({ jumlah: quantity, alasan: reason }, () => { setAction(null); }); }}
                />
                <ConfirmCommand
                    open={action?.kind === 'release'}
                    onOpenChange={close}
                    title={t('reservations.release.title', { n: lineNo })}
                    consequence={t('reservations.release.consequence', { jumlah: row === null ? '' : amount(row.quantity, row.unit) })}
                    reason={{ value: reason, onChange: setReason, error: reduce.errors.alasan ?? null }}
                    confirmLabel={t('actions.release')}
                    pending={reduce.pending}
                    answer={reduce.answer}
                    onConfirm={() => { reduce.submit({ lepas: true, alasan: reason }, () => { setAction(null); }); }}
                />
            </div>
        </AppShell>
    );
}
