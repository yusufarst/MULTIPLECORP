// PG-039 *Dokumen Administrasi* (ROUTE-039; NAV-039; SCR-37; IA §8.4; J-ADM-01; TASK-035): the unmet administrative
// requirements of the open projects of one company at a time — a branch of the account's scope (CS-07) —, each row
// its project, client and company, the item, its status by shape and label and what it takes; the tab (*Wajib* or
// *Semua*), the status filter, the sort and the cursor in the address, so Back and reload return them (IP-18). No
// count is shown. A row opens the project's PG-011; "Tandai Tidak Berlaku" with its reason on an eligible item for
// `requirements.waive`, and the Owner's relaxation, from the row's actions (IA §8.4).
import { router } from '@inertiajs/react';
import { useState } from 'react';

import { DataList, ListContinuation } from '@/components/data-list';
import { FormField } from '@/components/form';
import { type MenuAction, PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { NativeSelect } from '@/components/ui/native-select';
import { Tabs, TabsList, TabsTab } from '@/components/ui/tabs';
import { Code } from '@/components/values';
import { type Requirement, RequirementCommands, useRequirementName } from '@/features/adm/checklist';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';

type Branch = { id: string; code: string; name: string };
type Row = Requirement & { project: { id: string; number: string; title: string; client: string }; company: string };
type Filters = { tab: 'wajib' | 'semua'; status: 'belum-ada' | 'disiapkan' | null; urut: 'terbaru' | 'terlama' };
type Props = { branches: Branch[]; branch: string | null; filters: Filters; rows: Row[]; more: string | null; can: { waive: boolean; relax: boolean; owner: boolean } };
type Action = { kind: 'waive' | 'relax'; item: Requirement; project: string };

export default function AdministrativeDocuments({ branches, branch, filters, rows, more, can }: Props) {
    const { t } = useTranslation();
    const name = useRequirementName();
    // PT-08: "Muat Lagi" adds the page after the rows shown on a partial reload; any other visit shows its first page.
    const [base, setBase] = useState(rows);
    const [shown, setShown] = useState(rows);
    const [next, setNext] = useState(more);
    const [appending, setAppending] = useState(false);
    const [action, setAction] = useState<Action | null>(null);

    if (base !== rows) {
        setBase(rows);
        setShown(appending ? [...shown, ...rows.filter((row) => !shown.some((known) => known.handle === row.handle))] : rows);
        setNext(more);
        setAppending(false);
    }

    // The address carries the branch, tab, status and sort; a change starts at the first page (IP-18).
    const visit = (patch: Partial<Filters & { cabang: string }>) => {
        const query = { cabang: branch ?? '', tab: filters.tab, status: filters.status ?? '', urut: filters.urut, ...patch };
        const params = new URLSearchParams(Object.entries(query).filter(([, value]) => value !== '' && value !== null) as [string, string][]);
        router.get(`/dokumen/administrasi?${params.toString()}`, {}, { preserveScroll: true, preserveState: true });
    };
    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        router.reload({ only: ['rows', 'more'], data: { setelah: next }, preserveUrl: true });
    };
    const status = (row: Row) => <StatusBadge family="administrative_requirement" state={row.status} size="sm" />;
    const actions = (row: Row): MenuAction[] => [
        ...(row.may_waive ? [{ label: t('adm.action.waive'), onSelect: () => { setAction({ kind: 'waive', item: row, project: row.project.id }); } }] : []),
        ...(can.relax && !row.default_invoice ? [{ label: t('adm.action.relax'), onSelect: () => { setAction({ kind: 'relax', item: row, project: row.project.id }); } }] : []),
    ];

    return (
        <AppShell title={t('pages.pg_039.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_039.title')} />
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
                    <FormField label={t('adm.field.status')} className="min-w-0 md:w-56">
                        {(control) => (
                            <NativeSelect {...control} value={filters.status ?? ''} onChange={(event) => { visit({ status: event.target.value === '' ? null : (event.target.value as Filters['status']) }); }}>
                                <option value="">{t('adm.list.status.all')}</option>
                                <option value="belum-ada">{t('status.administrative_requirement.missing')}</option>
                                <option value="disiapkan">{t('status.administrative_requirement.draft_only')}</option>
                            </NativeSelect>
                        )}
                    </FormField>
                    <FormField label={t('adm.field.sort')} className="min-w-0 md:w-56">
                        {(control) => (
                            <NativeSelect {...control} value={filters.urut} onChange={(event) => { visit({ urut: event.target.value === 'terlama' ? 'terlama' : 'terbaru' }); }}>
                                {(['terbaru', 'terlama'] as const).map((sort) => <option key={sort} value={sort}>{t(`adm.list.sort.${sort}` as TranslationKey)}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                </div>
                <Tabs value={filters.tab} onValueChange={(value) => { visit({ tab: value === 'semua' ? 'semua' : 'wajib' }); }}>
                    <TabsList>
                        <TabsTab value="wajib">{t('adm.list.tab.wajib')}</TabsTab>
                        <TabsTab value="semua">{t('adm.list.tab.semua')}</TabsTab>
                    </TabsList>
                </Tabs>
                {shown.length === 0 ? (
                    <RegionEmpty text={branches.length === 0 ? t('documents.empty_scope') : t('adm.list.empty')} />
                ) : (
                    <>
                        <DataList<Row>
                            caption={t('pages.pg_039.title')}
                            rows={shown}
                            rowKey={(row) => row.handle}
                            rowHref={(row) => `/proyek/${row.project.id}`}
                            rowActions={(row) => actions(row)}
                            columns={[
                                { key: 'project', header: t('adm.field.project'), cell: (row) => (
                                    <span className="flex min-w-0 flex-col">
                                        <Code>{row.project.number}</Code>
                                        <span className="truncate text-body text-text-secondary">{row.project.title}</span>
                                    </span>
                                ) },
                                { key: 'client', header: t('projects.field.client'), priority: 'low', cell: (row) => row.project.client },
                                { key: 'company', header: t('projects.field.company'), priority: 'low', cell: (row) => row.company },
                                { key: 'item', header: t('adm.field.item'), cell: (row) => (
                                    <span className="flex min-w-0 flex-col">
                                        <span className="text-body text-text-primary">{name(row)}</span>
                                        <span className="text-caption text-text-secondary">{row.required ? t('adm.required') : t('adm.optional')}</span>
                                    </span>
                                ) },
                                { key: 'status', header: t('adm.field.status'), cell: status },
                            ]}
                            dense={(row) => ({
                                title: `${row.project.number} · ${name(row)}`,
                                secondary: `${row.company} · ${row.project.client}`,
                                status: status(row),
                            })}
                        />
                        <ListContinuation state={next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={loadMore} />
                    </>
                )}
                <RequirementCommands projectId={action?.project ?? ''} action={action} options={null} onClose={() => { setAction(null); }} />
            </div>
        </AppShell>
    );
}
