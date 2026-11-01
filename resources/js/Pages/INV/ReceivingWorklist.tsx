// PG-021 *Barang Masuk* (ROUTE-021; NAV-021; SCR-21; QS-05; PJ-18; TASK-039): the open warehouse remainders of OPEN purchases of the companies in scope, grouped, each group with its own error and retry, leading to PG-022 of the purchase chosen; the open groups and the company filter in the address; "Muat Lagi" (IP-18). The receiving projection: never a price, tax, charge or cost. `[floor]`, the phone first.
import { Deferred, router, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';

import { DataList, ListContinuation } from '@/components/data-list';
import { FormField } from '@/components/form';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty, RegionLoading } from '@/components/region-state';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateText } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';

type Group = { id: string; code: string; name: string };
type Row = { id: string; number: string; supplier: string; project: string | null; date: string; lines: number; remaining: string; href: string };
type Page = { rows: Row[]; more: string | null } | { failed: true };
type Props = { groups: Group[]; companies: Group[]; chosen: string | null; closed: string[] };

function quantity(value: string): string {
    return value.includes('.') ? value.replace(/\.?0+$/, '') : value;
}

function GroupRows({ group }: { group: Group }) {
    const { t } = useTranslation();
    // One prop for every group: a reload of one group answers that group alone, the others keep what they have.
    const fresh = (usePage().props as unknown as { worklists?: Record<string, Page | undefined> }).worklists?.[group.id];
    const [shown, setShown] = useState<Row[]>([]);
    const [seen, setSeen] = useState<Page | undefined>(undefined);
    const [appending, setAppending] = useState(false);

    if (fresh !== undefined && fresh !== seen) {
        setSeen(fresh);

        if (!('failed' in fresh)) {
            setShown(appending ? [...shown, ...fresh.rows.filter((row) => !shown.some((known) => known.id === row.id))] : fresh.rows);
        }

        setAppending(false);
    }

    const page = fresh ?? seen;

    if (page === undefined) return <RegionLoading />;

    if ('failed' in page) {
        return (
            <div className="flex flex-wrap items-center gap-3 text-body text-text-secondary" role="status">
                {t('receiving.list.group_failed')}
                <Button variant="outline" size="sm" onClick={() => { router.reload({ only: ['worklists'], data: { kelompok: group.id }, preserveUrl: true }); }}>{t('receiving.list.reload')}</Button>
            </div>
        );
    }

    const loadMore = () => {
        if (page.more === null) return;
        setAppending(true);
        router.reload({ only: ['worklists'], data: { kelompok: group.id, setelah: page.more }, preserveUrl: true });
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

export default function ReceivingWorklist({ groups, companies, chosen, closed }: Props) {
    const { t } = useTranslation();
    const visit = (perusahaan: string | null, tutup: string[]) => {
        router.get('/gudang/barang-masuk', { ...(perusahaan === null ? {} : { perusahaan }), ...(tutup.length === 0 ? {} : { tutup: tutup.join(',') }) }, { preserveScroll: true });
    };

    return (
        <AppShell title={t('pages.pg_021.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_021.title')} />
                {companies.length > 1 && (
                    <FormField label={t('receiving.list.filter')} className="min-w-0 sm:w-80">
                        {(control) => (
                            <NativeSelect {...control} value={chosen ?? ''} onChange={(event) => { visit(event.target.value === '' ? null : event.target.value, closed); }}>
                                <option value="">{t('receiving.list.all')}</option>
                                {companies.map((company) => <option key={company.id} value={company.id}>{`${company.code} · ${company.name}`}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                )}
                {groups.length === 0 ? <RegionEmpty text={t('receiving.list.empty')} /> : groups.map((group) => {
                    const isClosed = closed.includes(group.id);

                    return (
                        <section key={group.id} data-slot="receiving-group" className="flex min-w-0 flex-col gap-3">
                            <h2 className="text-subhead">
                                <button type="button" aria-expanded={!isClosed} className="flex min-h-floor min-w-0 items-center gap-2 text-left" onClick={() => { visit(chosen, isClosed ? closed.filter((id) => id !== group.id) : [...closed, group.id]); }}>
                                    <Icon icon={isClosed ? ChevronRight : ChevronDown} size="sm" />
                                    <span className="min-w-0">{`${group.code} · ${group.name}`}</span>
                                </button>
                            </h2>
                            {!isClosed && (
                                <Deferred data="worklists" fallback={<RegionLoading />}>
                                    <GroupRows group={group} />
                                </Deferred>
                            )}
                        </section>
                    );
                })}
            </div>
        </AppShell>
    );
}
