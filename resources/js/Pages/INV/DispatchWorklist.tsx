// PG-023 *Barang Keluar* (ROUTE-023; NAV-023; SCR-22; CALC-02; D-IA-20; TASK-041): the dispatch-ready lines — the remaining reservations of ACTIVE projects' warehouse lines of the companies in scope —, grouped, each group with its own error and retry, leading to PG-024 of the project chosen; the open groups and the company filter in the address; "Muat Lagi" (IP-18). Never a cost. `[floor]`, the phone first.
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
import { Code } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';

type Group = { id: string; code: string; name: string };
type Row = { id: string; project: string; title: string; line: number; name: string; remaining: string; unit: string; href: string };
type Page = { rows: Row[]; more: string | null; cursor_failed: boolean } | { failed: true };
type Props = { groups: Group[]; companies: Group[]; chosen: string | null; closed: string[] };

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

    const reload = () => { router.reload({ only: ['worklists'], data: { kelompok: group.id, setelah: '' }, preserveUrl: true }); };

    if ('failed' in page) {
        return (
            <div className="flex flex-wrap items-center gap-3 text-body text-text-secondary" role="status">
                {t('receiving.list.group_failed')}
                <Button variant="outline" size="sm" onClick={reload}>{t('receiving.list.reload')}</Button>
            </div>
        );
    }

    const loadMore = () => {
        if (page.more === null) return;
        setAppending(true);
        router.reload({ only: ['worklists'], data: { kelompok: group.id, setelah: page.more }, preserveUrl: true });
    };

    return shown.length === 0 && !page.cursor_failed ? <RegionEmpty text={t('dispatch.list.empty')} /> : (
        <>
            <DataList<Row>
                caption={`${t('pages.pg_023.title')} ${group.code}`}
                rows={shown}
                rowKey={(row) => row.id}
                rowHref={(row) => row.href}
                columns={[
                    { key: 'project', header: t('receiving.field.project'), cell: (row) => <Code>{row.project}</Code> },
                    { key: 'title', header: t('dispatch.field.title'), priority: 'low', cell: (row) => row.title },
                    { key: 'name', header: t('stock.column.product'), cell: (row) => row.name },
                    { key: 'remaining', header: t('dispatch.field.reserved'), numeric: true, cell: (row) => `${row.remaining} ${row.unit}` },
                ]}
                dense={(row) => ({ title: row.name, secondary: row.project, status: `${row.remaining} ${row.unit}` })}
            />
            <ListContinuation state={page.cursor_failed ? 'cursor_failed' : page.more === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={reload} />
        </>
    );
}

export default function DispatchWorklist({ groups, companies, chosen, closed }: Props) {
    const { t } = useTranslation();
    const visit = (perusahaan: string | null, tutup: string[]) => {
        router.get('/gudang/barang-keluar', { ...(perusahaan === null ? {} : { perusahaan }), ...(tutup.length === 0 ? {} : { tutup: tutup.join(',') }) }, { preserveScroll: true });
    };

    return (
        <AppShell title={t('pages.pg_023.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_023.title')} />
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
                {groups.length === 0 ? <RegionEmpty text={t('dispatch.list.empty')} /> : groups.map((group) => {
                    const isClosed = closed.includes(group.id);

                    return (
                        <section key={group.id} data-slot="dispatch-group" className="flex min-w-0 flex-col gap-3">
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
