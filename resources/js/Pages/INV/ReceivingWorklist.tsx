// PG-021 *Barang Masuk* (ROUTE-021; NAV-021; SCR-21; QS-05; PJ-18; TASK-039): the open warehouse remainders of OPEN purchases of the companies in scope, grouped, each group with its own error and retry, leading to PG-022 of the purchase chosen; "Muat Lagi" (IP-18). The receiving projection: never a price, tax, charge or cost. `[floor]`, the phone first.
import { Deferred, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { DataList, ListContinuation } from '@/components/data-list';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty, RegionLoading } from '@/components/region-state';
import { Button } from '@/components/ui/button';
import { Code, DateText } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';

type Group = { id: string; code: string; name: string };
type Row = { id: string; number: string; supplier: string; project: string | null; date: string; lines: number; remaining: string; href: string };
type Page = { rows: Row[]; more: string | null } | { failed: true };
type Props = { groups: Group[] };

function quantity(value: string): string {
    return value.includes('.') ? value.replace(/\.?0+$/, '') : value;
}

function GroupRows({ group }: { group: Group }) {
    const { t } = useTranslation();
    const key = `group_${group.id}`;
    const page = (usePage().props as unknown as Record<string, Page | undefined>)[key];
    const [shown, setShown] = useState<Row[]>([]);
    const [seen, setSeen] = useState<Page | undefined>(undefined);
    const [appending, setAppending] = useState(false);

    if (page !== undefined && page !== seen && !('failed' in page)) {
        setSeen(page);
        setShown(appending ? [...shown, ...page.rows.filter((row) => !shown.some((known) => known.id === row.id))] : page.rows);
        setAppending(false);
    }

    if (page === undefined) return <RegionLoading />;

    if ('failed' in page) {
        return (
            <div className="flex flex-wrap items-center gap-3 text-body text-text-secondary" role="status">
                {t('receiving.list.group_failed')}
                <Button variant="outline" size="sm" onClick={() => { router.reload({ only: [key] }); }}>{t('receiving.list.reload')}</Button>
            </div>
        );
    }

    const loadMore = () => {
        if (page.more === null) return;
        setAppending(true);
        router.reload({ only: [key], data: { kelompok: group.id, setelah: page.more }, preserveUrl: true });
    };

    return shown.length === 0 ? <RegionEmpty text={t('receiving.list.empty')} /> : (
        <>
            <DataList<Row>
                caption={`${t('pages.pg_021.title')} ${group.code}`}
                rows={shown}
                rowKey={(row) => row.id}
                rowHref={(row) => row.href}
                columns={[
                    { key: 'number', header: t('receiving.field.purchase'), cell: (row) => <Code>{row.number}</Code> },
                    { key: 'supplier', header: t('receiving.field.supplier'), cell: (row) => row.supplier },
                    { key: 'project', header: t('receiving.field.project'), priority: 'low', cell: (row) => (row.project === null ? '—' : <Code>{row.project}</Code>) },
                    { key: 'date', header: t('receiving.field.date'), priority: 'low', cell: (row) => <DateText value={row.date} /> },
                    { key: 'remaining', header: t('pages.pg_016.remaining'), numeric: true, cell: (row) => quantity(row.remaining) },
                ]}
                dense={(row) => ({ title: row.number, secondary: row.supplier, status: quantity(row.remaining) })}
            />
            <ListContinuation state={page.more === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={loadMore} />
        </>
    );
}

export default function ReceivingWorklist({ groups }: Props) {
    const { t } = useTranslation();

    return (
        <AppShell title={t('pages.pg_021.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_021.title')} />
                {groups.length === 0 ? <RegionEmpty text={t('receiving.list.empty')} /> : groups.map((group) => (
                    <section key={group.id} data-slot="receiving-group" className="flex min-w-0 flex-col gap-3">
                        <h2 className="text-subhead">{`${group.code} · ${group.name}`}</h2>
                        <Deferred data={`group_${group.id}`} fallback={<RegionLoading />}>
                            <GroupRows group={group} />
                        </Deferred>
                    </section>
                ))}
            </div>
        </AppShell>
    );
}
