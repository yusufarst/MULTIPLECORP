// PG-015 *Pembelian* (ROUTE-015; NAV-015; SCR-15; IP-18; PF-13; TASK-037): purchases one company at a time with the company label, number, supplier, project, state and business date, "Buat Pembelian" and "Muat Lagi"; no total across companies (CS-07; CS-08). `[desk]`, readable on the phone.
import { router } from '@inertiajs/react';
import { useState } from 'react';

import { DataList, ListContinuation } from '@/components/data-list';
import { FormField } from '@/components/form';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateText } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';

type Branch = { id: string; code: string; name: string };
type State = 'OPEN' | 'CLOSED' | 'CANCELLED';
type Row = { id: string; company: string; number: string; supplier: string; project: string | null; state: State; date: string; href: string };
type Props = { branches: Branch[]; branch: string | null; state: State | null; rows: Row[]; more: string | null; can: { create: boolean } };

export default function Purchases({ branches, branch, state, rows, more, can }: Props) {
    const { t } = useTranslation();
    const [base, setBase] = useState(rows);
    const [shown, setShown] = useState(rows);
    const [next, setNext] = useState(more);
    const [appending, setAppending] = useState(false);

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

        router.get(`/pembelian?${params.toString()}`, {}, { preserveState: true });
    };
    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        router.reload({ only: ['rows', 'more'], data: { setelah: next }, preserveUrl: true });
    };
    const badge = (row: Row) => <StatusBadge family="purchase" state={row.state} size="sm" />;

    return (
        <AppShell title={t('pages.pg_015.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_015.title')} primary={can.create ? <Button render={<a href="/pembelian/baru" />}>{t('actions.create_purchase')}</Button> : undefined} />
                {can.create && <Button className="self-start md:hidden" render={<a href="/pembelian/baru" />}>{t('actions.create_purchase')}</Button>}
                <div className="flex min-w-0 flex-wrap items-end gap-4">
                    {branches.length > 1 && (
                        <FormField label={t('purchases.field.company')} className="min-w-0 sm:w-80">
                            {(control) => (
                                <NativeSelect {...control} value={branch ?? ''} onChange={(event) => { visit({ perusahaan: event.target.value, status: state }); }}>
                                    {branches.map((option) => <option key={option.id} value={option.id}>{`${option.code} · ${option.name}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}
                    <FormField label={t('purchases.field.state')} className="min-w-0 sm:w-56">
                        {(control) => (
                            <NativeSelect {...control} value={state ?? ''} onChange={(event) => { visit({ perusahaan: branch, status: event.target.value }); }}>
                                <option value="">{t('purchases.list.all_states')}</option>
                                <option value="OPEN">{t('status.purchase.open')}</option>
                                <option value="CLOSED">{t('status.purchase.closed')}</option>
                                <option value="CANCELLED">{t('status.purchase.cancelled')}</option>
                            </NativeSelect>
                        )}
                    </FormField>
                </div>
                {shown.length === 0 ? (
                    <RegionEmpty text={t('purchases.list.empty')} action={can.create ? <Button variant="outline" render={<a href="/pembelian/baru" />}>{t('actions.create_purchase')}</Button> : undefined} />
                ) : (
                    <>
                        <DataList<Row>
                            caption={t('pages.pg_015.title')}
                            rows={shown}
                            rowKey={(row) => row.id}
                            rowHref={(row) => row.href}
                            columns={[
                                { key: 'company', header: t('purchases.field.company'), priority: 'low', cell: (row) => <Code>{row.company}</Code> },
                                { key: 'number', header: t('purchases.field.number'), cell: (row) => <Code>{row.number}</Code> },
                                { key: 'supplier', header: t('purchases.field.supplier'), cell: (row) => row.supplier },
                                { key: 'project', header: t('purchases.field.project'), priority: 'low', cell: (row) => (row.project === null ? t('purchases.list.replenishment') : <Code>{row.project}</Code>) },
                                { key: 'state', header: t('purchases.field.state'), cell: badge },
                                { key: 'date', header: t('purchases.field.date'), priority: 'low', cell: (row) => <DateText value={row.date} /> },
                            ]}
                            dense={(row) => ({ title: row.number, secondary: row.supplier, status: badge(row) })}
                        />
                        <ListContinuation state={next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={loadMore} />
                    </>
                )}
            </div>
        </AppShell>
    );
}
