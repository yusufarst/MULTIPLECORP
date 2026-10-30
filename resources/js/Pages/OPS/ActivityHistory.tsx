// PG-063 *Riwayat Aktivitas* (ROUTE-063; NAV-063; SCR-59; IA §8.6; TASK-061): the Owner's search of the business
// audit of the whole workspace — every company and the events without a company, newest first —, each row the time
// recorded and, when it differs, the business date (BR-DT-05), the acting account or "Sistem" or the operator's recorded
// identity, the company or *Tanpa perusahaan*, the action in one plain sentence, the record with its link, the reason
// and the authority class (BR-XC-01; LG-01; PT-18); an event's before and after on demand (PF-06). The period, company,
// actor and record in the address (IP-18); "Muat Lagi", no total (PF-14). Read-only: no control edits, hides or
// deletes an event (C-53). `[desk]`, dense, readable on the phone; the print view prints the list (§17).
import { router } from '@inertiajs/react';
import { useState } from 'react';

import { type Continuation, DataList, ListContinuation } from '@/components/data-list';
import { DateField } from '@/components/date-field';
import { FormField } from '@/components/form';
import { type MenuAction, PageHeader } from '@/components/page-header';
import { FactsList } from '@/components/record-blocks';
import { RegionEmpty } from '@/components/region-state';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { DateText, DateTimeText } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';

type Row = {
    key: string;
    at: string;
    business_date: string | null;
    actor: string | null;
    operator: string | null;
    company: string | null;
    action: string;
    record: { type: string; href: string | null; filter: string | null } | null;
    reason: string | null;
    authority: 'ADM' | 'ADM_PLUS' | 'OWNER_ONLY' | 'SYSTEM';
};
type Filters = { dari: string | null; sampai: string | null; perusahaan: string | null; pelaku: string | null; catatan: string | null };
type Detail = { key: string; before: Record<string, string>; after: Record<string, string>; metadata: Record<string, string> };
type Props = {
    companies: { code: string; name: string }[];
    actors: { id: string; name: string }[];
    filters: Filters;
    record: { type: string; id: string } | null;
    rows: Row[];
    more: string | null;
    cursor_failed: boolean;
    detail?: Detail | null;
};

const LIST = '/aktivitas/riwayat';
const NO_COMPANY = 'tanpa';

export default function ActivityHistory({ companies, actors, filters, record, rows, more, cursor_failed: cursorFailed, detail }: Props) {
    const { t, has } = useTranslation();
    // PT-08: "Muat Lagi" adds the page after the rows shown on a partial reload; any other visit shows its first page.
    const [base, setBase] = useState(rows);
    const [shown, setShown] = useState(rows);
    const [next, setNext] = useState(more);
    const [appending, setAppending] = useState(false);
    const [opened, setOpened] = useState<Row | null>(null);

    if (base !== rows) {
        setBase(rows);
        setShown(appending ? [...shown, ...rows.filter((row) => !shown.some((known) => known.key === row.key))] : rows);
        setNext(more);
        setAppending(false);
    }

    // The address carries the period, company, actor and record; a change starts at the first page (IP-18).
    const visit = (patch: Partial<Filters>) => {
        const query = { ...filters, ...patch };
        const params = new URLSearchParams(Object.entries(query).filter(([, value]) => value !== null && value !== '') as [string, string][]);
        router.get(`${LIST}${params.size > 0 ? `?${params.toString()}` : ''}`, {}, { preserveScroll: true, preserveState: true });
    };
    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        router.reload({ only: ['rows', 'more', 'cursor_failed'], data: { setelah: next }, preserveUrl: true });
    };
    const open = (row: Row) => {
        setOpened(row);
        router.reload({ only: ['detail'], data: { peristiwa: row.key }, preserveUrl: true });
    };
    const sentence = (row: Row): string => {
        const key = `activity.action.${row.action}`;

        return has(key) ? t(key) : t('activity.action.unknown', { kode: row.action });
    };
    const entity = (type: string): string => {
        const key = `activity.entity.${type}`;

        return has(key) ? t(key) : t('activity.entity.other');
    };
    const actor = (row: Row): string => row.actor ?? (row.operator !== null ? t('activity.operator', { nama: row.operator }) : t('timeline.system'));
    const actions = (row: Row): MenuAction[] => [
        { label: t('activity.detail.open'), onSelect: () => { open(row); } },
        ...(row.record !== null && row.record.filter !== null ? [{ label: t('activity.record.history'), onSelect: () => { visit({ catatan: row.record?.filter ?? null }); } }] : []),
    ];
    const when = (row: Row) => (
        <span className="flex min-w-0 flex-col">
            <DateTimeText value={row.at} />
            {row.business_date !== null && (
                <span className="text-caption text-text-tertiary">
                    {t('pt25.label')} <DateText value={row.business_date} />
                </span>
            )}
        </span>
    );
    const state: Continuation = cursorFailed ? 'cursor_failed' : next === null ? 'end' : 'more';
    const shownDetail = detail !== undefined && detail !== null && detail.key === opened?.key ? detail : null;

    return (
        <AppShell title={t('pages.pg_063.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_063.title')} actions={[{ label: t('actions.print'), onSelect: () => { window.print(); } }]} />
                <div className="flex min-w-0 flex-col gap-4 md:flex-row md:flex-wrap md:items-end print:hidden">
                    <FormField label={t('activity.filter.from')} className="min-w-0 md:w-44">
                        {(control) => <DateField {...control} value={filters.dari} onValueChange={(day, text) => { if (day !== null || text === '') visit({ dari: day }); }} />}
                    </FormField>
                    <FormField label={t('activity.filter.to')} className="min-w-0 md:w-44">
                        {(control) => <DateField {...control} value={filters.sampai} onValueChange={(day, text) => { if (day !== null || text === '') visit({ sampai: day }); }} />}
                    </FormField>
                    <FormField label={t('activity.filter.company')} className="min-w-0 md:w-64">
                        {(control) => (
                            <NativeSelect {...control} value={filters.perusahaan ?? ''} onChange={(event) => { visit({ perusahaan: event.target.value === '' ? null : event.target.value }); }}>
                                <option value="">{t('activity.filter.company_all')}</option>
                                {companies.map((company) => <option key={company.code} value={company.code}>{`${company.code} · ${company.name}`}</option>)}
                                <option value={NO_COMPANY}>{t('activity.filter.no_company')}</option>
                            </NativeSelect>
                        )}
                    </FormField>
                    <FormField label={t('activity.filter.actor')} className="min-w-0 md:w-64">
                        {(control) => (
                            <NativeSelect {...control} value={filters.pelaku ?? ''} onChange={(event) => { visit({ pelaku: event.target.value === '' ? null : event.target.value }); }}>
                                <option value="">{t('activity.filter.actor_all')}</option>
                                {actors.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                    {record !== null && (
                        <div className="flex min-w-0 flex-wrap items-center gap-3">
                            <span className="min-w-0 text-body text-text-primary">{t('activity.filter.record', { jenis: entity(record.type) })}</span>
                            <Button variant="outline" size="sm" onClick={() => { visit({ catatan: null }); }}>
                                {t('activity.filter.clear_record')}
                            </Button>
                        </div>
                    )}
                </div>
                {shown.length === 0 && !cursorFailed ? (
                    <RegionEmpty text={t('activity.empty')} />
                ) : (
                    <>
                        <DataList<Row>
                            caption={t('pages.pg_063.title')}
                            rows={shown}
                            rowKey={(row) => row.key}
                            rowHref={(row) => row.record?.href ?? null}
                            rowActions={actions}
                            columns={[
                                { key: 'time', header: t('activity.field.time'), cell: when },
                                { key: 'actor', header: t('activity.field.actor'), cell: actor },
                                { key: 'company', header: t('activity.field.company'), priority: 'low', cell: (row) => row.company ?? t('activity.filter.no_company') },
                                { key: 'action', header: t('activity.field.action'), cell: sentence },
                                { key: 'record', header: t('activity.field.record'), priority: 'low', cell: (row) => (row.record === null ? '' : entity(row.record.type)) },
                                { key: 'reason', header: t('activity.field.reason'), priority: 'low', cell: (row) => row.reason ?? '' },
                                { key: 'authority', header: t('activity.field.authority'), cell: (row) => t(`activity.authority.${row.authority}` as TranslationKey) },
                            ]}
                            dense={(row) => ({
                                title: sentence(row),
                                secondary: `${actor(row)} · ${row.company ?? t('activity.filter.no_company')}`,
                                status: <DateTimeText value={row.at} className="text-m-meta text-text-tertiary" />,
                            })}
                        />
                        <div className="print:hidden">
                            <ListContinuation state={state} loading={appending} onLoadMore={loadMore} onReload={() => { visit({}); }} />
                        </div>
                    </>
                )}
            </div>
            <Dialog open={opened !== null} onOpenChange={(value) => { if (!value) setOpened(null); }}>
                <DialogContent phone="full" closeLabel={t('actions.close')}>
                    <DialogTitle>{t('activity.detail.title')}</DialogTitle>
                    {opened !== null && (
                        <div className="flex min-w-0 flex-col gap-4">
                            <p className="text-body text-text-primary">{sentence(opened)}</p>
                            {shownDetail === null ? (
                                <p className="text-body text-text-secondary">{detail === null ? t('activity.detail.unavailable') : t('states.loading')}</p>
                            ) : (
                                (['before', 'after', 'metadata'] as const).map((part) => (
                                    <section key={part} className="flex min-w-0 flex-col gap-2">
                                        <h3 className="text-label text-text-secondary">{t(`activity.detail.${part}`)}</h3>
                                        {Object.keys(shownDetail[part]).length === 0 ? (
                                            <p className="text-body text-text-tertiary">{t('activity.detail.none')}</p>
                                        ) : (
                                            <FactsList columns={2} facts={Object.entries(shownDetail[part]).map(([name, value]) => ({ label: name, value }))} />
                                        )}
                                    </section>
                                ))
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppShell>
    );
}
