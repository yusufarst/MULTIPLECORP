// PG-041 *Invoice* (ROUTE-041; NAV-041; SCR-38; WF-FIN-01; J-FIN-01; TASK-049): a branch's invoices, each its number, project, client, date, shown state and billed sum — one company at a time, never a total
// across them (CS-07) —, the exact number lookup, and how "Buat Invoice" is reached for a holder of `invoice.issue`: from the project it bills (ROUTE_CONTRACTS §4). PG-041's filters and cursor stay in the address (IP-18).
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { type SyntheticEvent, useState } from 'react';

import { DataList, ListContinuation } from '@/components/data-list';
import { FormField } from '@/components/form';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateText, Money } from '@/components/values';
import { type InvoiceState } from '@/features/fin/invoice';
import { FinanceNav } from '@/features/fin/finance-nav';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';

type Branch = { id: string; code: string; name: string };
type Row = { id: string; number: string | null; project: string | null; project_number: string | null; client: string; date: string; value: string; state: InvoiceState; href: string };
type Props = { branches: Branch[]; branch: string | null; number: string | null; rows: Row[]; more: string | null; can: { create: boolean } };

export default function Invoices({ branches, branch, number, rows, more, can }: Props) {
    const { t } = useTranslation();
    const [base, setBase] = useState(rows);
    const [shown, setShown] = useState(rows);
    const [next, setNext] = useState(more);
    const [appending, setAppending] = useState(false);
    const [term, setTerm] = useState(number ?? '');

    if (base !== rows) {
        setBase(rows);
        setShown(appending ? [...shown, ...rows.filter((row) => !shown.some((known) => known.id === row.id))] : rows);
        setNext(more);
        setAppending(false);
    }

    const visit = (query: Record<string, string | null>) => {
        const params = new URLSearchParams();

        for (const [key, value] of Object.entries(query)) {
            if (value !== null && value !== '') params.set(key, value);
        }

        router.get(`/keuangan/invoice?${params.toString()}`, {}, { preserveState: true });
    };
    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        router.reload({ only: ['rows', 'more'], data: { setelah: next }, preserveUrl: true });
    };
    const search = (event: SyntheticEvent) => {
        event.preventDefault();
        visit({ perusahaan: branch, nomor: term });
    };
    const state = (row: Row) => <StatusBadge family="invoice_and_receivable" state={row.state} size="sm" />;

    return (
        <AppShell title={t('pages.pg_041.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_041.title')} />
                <FinanceNav current="invoices" />
                <div className="flex min-w-0 flex-wrap items-end gap-4">
                    {branches.length > 1 && (
                        <FormField label={t('invoices.field.branch')} className="min-w-0 sm:w-80">
                            {(control) => (
                                <NativeSelect {...control} value={branch ?? ''} onChange={(event) => { visit({ perusahaan: event.target.value }); }}>
                                    {branches.map((option) => (
                                        <option key={option.id} value={option.id}>{`${option.code} · ${option.name}`}</option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}
                    <form onSubmit={search} className="flex min-w-0 flex-wrap items-end gap-2">
                        <FormField label={t('invoices.list.number')} className="min-w-0 sm:w-72">
                            {(control) => <Input {...control} value={term} onChange={(event) => { setTerm(event.target.value); }} />}
                        </FormField>
                        <Button type="submit" variant="outline">
                            <Icon icon={Search} size="sm" />
                            {t('invoices.list.search')}
                        </Button>
                    </form>
                </div>
                {can.create && <p className="text-body text-text-secondary">{t('invoices.list.create_hint')}</p>}
                {shown.length === 0 ? (
                    <RegionEmpty text={branches.length === 0 ? t('invoices.list.empty_scope') : t('invoices.list.empty')} />
                ) : (
                    <>
                        <DataList<Row>
                            caption={t('pages.pg_041.title')}
                            rows={shown}
                            rowKey={(row) => row.id}
                            rowHref={(row) => row.href}
                            columns={[
                                { key: 'number', header: t('invoices.field.number'), cell: (row) => (row.number === null ? '—' : <Code>{row.number}</Code>) },
                                { key: 'project', header: t('invoices.field.project'), priority: 'low', cell: (row) => (row.project_number === null ? '—' : <Code>{row.project_number}</Code>) },
                                { key: 'client', header: t('invoices.field.client'), cell: (row) => row.client },
                                { key: 'date', header: t('invoices.field.date'), priority: 'low', cell: (row) => <DateText value={row.date} /> },
                                { key: 'state', header: t('invoices.field.state'), cell: state },
                                { key: 'value', header: t('invoices.field.value'), numeric: true, cell: (row) => <Money value={row.value} /> },
                            ]}
                            dense={(row) => ({ title: row.number ?? row.client, secondary: row.client, status: state(row) })}
                        />
                        <ListContinuation state={next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={loadMore} />
                    </>
                )}
            </div>
        </AppShell>
    );
}
