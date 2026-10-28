// PG-037 *Daftar Penawaran* (ROUTE-037; NAV-037; SCR-12; IA §8.2; WF-QUO-01; J-QUO-01; TASK-033): the quotation
// revisions of one company at a time — a branch of the account's scope —, each row its project, revision, state —
// *Kedaluwarsa* derived from the validity (L-25) —, validity and DOC-01 number; the value only with `finance.view`
// (PJ-15), never a total across companies (CS-07). A row opens PG-014. Nothing here implies a reservation or a payment.
import { router } from '@inertiajs/react';
import { useState } from 'react';

import { DataList, ListContinuation } from '@/components/data-list';
import { FormField } from '@/components/form';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateText, Money } from '@/components/values';
import { revisionState, type RevisionState } from '@/features/prj/quotation';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';

type Branch = { id: string; code: string; name: string };
type Row = { id: string; project: string; project_number: string; title: string; client: string; no: number; state: RevisionState; valid_until: string | null; number: string | null; value?: string };
type Props = { branches: Branch[]; branch: string | null; money: boolean; rows: Row[]; more: string | null };

export default function Quotations({ branches, branch, money, rows, more }: Props) {
    const { t } = useTranslation();
    // PT-08: "Muat Lagi" adds the page after the rows shown on a partial reload; any other visit shows its first page.
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

    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        router.reload({ only: ['rows', 'more'], data: { setelah: next }, preserveUrl: true });
    };
    const state = (row: Row) => <StatusBadge family="quotation_revision" state={revisionState(row.state)} size="sm" />;

    return (
        <AppShell title={t('pages.pg_037.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_037.title')} />
                {branches.length > 1 && (
                    <FormField label={t('documents.field.branch')} className="min-w-0 sm:w-80">
                        {(control) => (
                            <NativeSelect {...control} value={branch ?? ''} onChange={(event) => { router.get(`/dokumen/penawaran?${new URLSearchParams({ perusahaan: event.target.value }).toString()}`, {}, { preserveScroll: true, preserveState: true }); }}>
                                {branches.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {`${option.code} · ${option.name}`}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>
                )}
                {shown.length === 0 ? (
                    <RegionEmpty text={branches.length === 0 ? t('documents.empty_scope') : t('quotations.list.empty')} />
                ) : (
                    <>
                        <DataList<Row>
                            caption={t('pages.pg_037.title')}
                            rows={shown}
                            rowKey={(row) => row.id}
                            rowHref={(row) => `/proyek/${row.project}/penawaran`}
                            columns={[
                                { key: 'project', header: t('quotations.field.project'), cell: (row) => (
                                    <span className="flex min-w-0 flex-col">
                                        <Code>{row.project_number}</Code>
                                        <span className="truncate text-body text-text-secondary">{row.title}</span>
                                    </span>
                                ) },
                                { key: 'client', header: t('projects.field.client'), priority: 'low', cell: (row) => row.client },
                                { key: 'revision', header: t('quotations.field.revision'), numeric: true, cell: (row) => row.no },
                                { key: 'state', header: t('documents.field.state'), cell: state },
                                { key: 'valid', header: t('quotations.field.valid_until'), cell: (row) => (row.valid_until === null ? '—' : <DateText value={row.valid_until} />) },
                                { key: 'number', header: t('documents.field.number'), priority: 'low', cell: (row) => (row.number === null ? '—' : <Code>{row.number}</Code>) },
                                ...(money ? [{ key: 'value', header: t('quotations.field.value'), numeric: true, cell: (row: Row) => (row.value === undefined ? '—' : <Money value={row.value} />) }] : []),
                            ]}
                            dense={(row) => ({
                                title: `${row.project_number} · ${t('quotations.revision', { n: row.no })}`,
                                secondary: row.client,
                                status: state(row),
                            })}
                        />
                        <ListContinuation state={next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={loadMore} />
                    </>
                )}
            </div>
        </AppShell>
    );
}
