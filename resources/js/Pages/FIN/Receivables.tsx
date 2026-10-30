// PG-044 *Piutang* (ROUTE-044; NAV-044; SCR-40; D-IA-15; J-FIN-02; TASK-050): by branch, the tabs *Aktif*, *Belum Ditagihkan* and *Sengketa*, each row with its company where several are in scope, oldest due first; a subtotal per company with its ageing
// buckets, never a total across companies (CS-08). "Tagihkan" on an unbilled row (J-FIN-02), "Catat Pembayaran" → PG-046 once that page exists (J-FIN-03). Tabs, filters and cursor stay in the address (IP-18); on the phone, the lookup of a client's receivables.
import { router } from '@inertiajs/react';
import { useState } from 'react';

import { DataList, ListContinuation } from '@/components/data-list';
import { FormField } from '@/components/form';
import { type MenuAction, PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { NativeSelect } from '@/components/ui/native-select';
import { Tabs, TabsList, TabsTab } from '@/components/ui/tabs';
import { Code, DateText, Money } from '@/components/values';
import { type InvoiceState } from '@/features/fin/invoice';
import { BillDialog } from '@/features/fin/invoice-receivable';
import AppShell from '@/layouts/AppShell';
import { money } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { BUILT } from '@/lib/navigation';

type Tab = 'aktif' | 'belum' | 'sengketa';
type Branch = { id: string; code: string; name: string };
type Client = { id: string; name: string };
type Subtotal = { company: string; name: string; count: number; amount: string; buckets: Record<string, string> };
type Row = { id: string; number: string | null; company: string; client: string; project: string | null; project_number: string | null; billed_at: string | null; due_date: string | null; days_past: number; bucket: string | null; outstanding: string; held: boolean; state: InvoiceState; href: string; can: { bill: boolean; pay: boolean } };
type Props = { branches: Branch[]; branch: string | null; tab: Tab; client: string | null; clients: Client[]; subtotals: Subtotal[]; rows: Row[]; more: string | null; today: string };

const TABS: Tab[] = ['aktif', 'belum', 'sengketa'];
const BUCKETS = ['MIGRASI', 'CURRENT', 'DAYS_1_30', 'DAYS_31_60', 'DAYS_61_90', 'OVER_90'];

export default function Receivables({ branches, branch, tab, client, clients, subtotals, rows, more, today }: Props) {
    const { t } = useTranslation();
    const [base, setBase] = useState(rows);
    const [shown, setShown] = useState(rows);
    const [next, setNext] = useState(more);
    const [appending, setAppending] = useState(false);
    const [billing, setBilling] = useState<Row | null>(null);

    if (base !== rows) {
        setBase(rows);
        setShown(appending ? [...shown, ...rows.filter((row) => !shown.some((known) => known.id === row.id))] : rows);
        setNext(more);
        setAppending(false);
    }

    const query = (change: Record<string, string | null>) => {
        const params = new URLSearchParams();

        for (const [key, value] of Object.entries({ perusahaan: branch, tab, klien: client, ...change })) {
            if (value !== null && value !== '') params.set(key, value);
        }

        return `/keuangan/piutang?${params.toString()}`;
    };
    const visit = (change: Record<string, string | null>) => { router.get(query(change), {}, { preserveState: true }); };
    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        router.reload({ only: ['rows', 'more'], data: { setelah: next }, preserveUrl: true });
    };
    const state = (row: Row) => <StatusBadge family="invoice_and_receivable" state={row.state} size="sm" />;
    const age = (row: Row) => row.bucket === null ? '—' : row.days_past > 0 ? t('receivables.age.short', { hari: String(row.days_past) }) : t(`receivables.bucket.${row.bucket}` as TranslationKey);
    const rowActions = (row: Row): MenuAction[] => [
        ...(row.can.bill ? [{ label: t('receivables.action.bill'), onSelect: () => { setBilling(row); } }] : []),
        ...(row.can.pay && BUILT.has('PG-046') ? [{ label: t('receivables.action.pay'), onSelect: () => { router.visit(`/keuangan/pembayaran/baru?${new URLSearchParams({ invoice: row.id }).toString()}`); } }] : []),
    ];

    return (
        <AppShell title={t('pages.pg_044.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_044.title')} />
                <div className="flex min-w-0 flex-wrap items-end gap-4">
                    {branches.length > 1 && (
                        <FormField label={t('receivables.field.branch')} className="min-w-0 sm:w-80">
                            {(control) => (
                                <NativeSelect {...control} value={branch ?? ''} onChange={(event) => { visit({ perusahaan: event.target.value, klien: null }); }}>
                                    <option value="">{t('receivables.field.all_branches')}</option>
                                    {branches.map((option) => <option key={option.id} value={option.id}>{`${option.code} · ${option.name}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}
                    <FormField label={t('receivables.field.client')} className="min-w-0 sm:w-80">
                        {(control) => (
                            <NativeSelect {...control} value={client ?? ''} onChange={(event) => { visit({ klien: event.target.value }); }}>
                                <option value="">{t('receivables.field.all_clients')}</option>
                                {clients.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                </div>
                <Tabs value={tab} onValueChange={(value) => { visit({ tab: String(value), klien: null }); }}>
                    <TabsList>
                        {TABS.map((each) => <TabsTab key={each} value={each}>{t(`receivables.tab.${each}` as TranslationKey)}</TabsTab>)}
                    </TabsList>
                </Tabs>
                {subtotals.length > 0 && (
                    <section aria-label={t('receivables.subtotal.title')}>
                        <ul role="list" data-testid="subtotals" className="flex min-w-0 flex-col gap-3 text-body">
                            {subtotals.map((row) => (
                                <li key={row.company} className="flex min-w-0 flex-col gap-1">
                                    <span className="flex min-w-0 flex-wrap items-baseline justify-between gap-3">
                                        <span className="font-semibold text-text-primary">{t('receivables.subtotal.line', { kode: row.company, baris: String(row.count) })}</span>
                                        <span className="font-semibold text-text-primary"><Money value={row.amount} /></span>
                                    </span>
                                    {tab !== 'belum' && (
                                        <span className="flex min-w-0 flex-wrap gap-x-4 gap-y-1 text-text-secondary">
                                            {BUCKETS.filter((bucket) => row.buckets[bucket] !== undefined).map((bucket) => <span key={bucket}>{`${t(`receivables.bucket.${bucket}` as TranslationKey)}: ${money(row.buckets[bucket] ?? '0')}`}</span>)}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
                {shown.length === 0 ? (
                    <RegionEmpty text={branches.length === 0 ? t('receivables.empty_scope') : t(`receivables.empty.${tab}` as TranslationKey)} />
                ) : (
                    <>
                        <DataList<Row>
                            caption={t('pages.pg_044.title')}
                            rows={shown}
                            rowKey={(row) => row.id}
                            rowHref={(row) => row.href}
                            rowActions={rowActions}
                            columns={[
                                { key: 'number', header: t('invoices.field.number'), cell: (row) => (row.number === null ? '—' : <Code>{row.number}</Code>) },
                                ...(branches.length > 1 ? [{ key: 'company', header: t('receivables.field.branch'), cell: (row: Row) => row.company }] : []),
                                { key: 'client', header: t('invoices.field.client'), cell: (row) => row.client },
                                { key: 'project', header: t('invoices.field.project'), priority: 'low', cell: (row) => (row.project_number === null ? '—' : <Code>{row.project_number}</Code>) },
                                { key: 'due', header: t('receivables.field.due'), cell: (row) => (row.due_date === null ? '—' : <DateText value={row.due_date} />) },
                                { key: 'age', header: t('receivables.field.age'), priority: 'low', cell: age },
                                { key: 'state', header: t('invoices.field.state'), cell: state },
                                { key: 'outstanding', header: t('invoices.position.outstanding'), numeric: true, cell: (row) => <Money value={row.outstanding} /> },
                            ]}
                            dense={(row) => ({ title: row.number ?? row.client, secondary: `${row.company} · ${row.client}`, figure: <Money value={row.outstanding} />, status: state(row) })}
                        />
                        <ListContinuation state={next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={loadMore} />
                    </>
                )}
            </div>
            {billing !== null && <BillDialog key={billing.id} invoice={billing.id} outstanding={billing.outstanding} today={today} open onOpenChange={(open) => { if (!open) setBilling(null); }} back={query({})} />}
        </AppShell>
    );
}
