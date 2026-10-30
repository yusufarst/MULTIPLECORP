// PG-064 *Log Keamanan* (ROUTE-064; NAV-064; SCR-60; IA §8.6; TASK-061): the Owner's security events, newest first —
// kind, time, the account concerned and the acting account when different, the reason code, the route and target, the
// identifier and the address only as their keyed hashes, the user-agent summary and the whitelisted details (LG-02;
// LG-03) —, filtered by kind, period and company, all in the address (IP-18); "Muat Lagi", no total (PF-14). A security
// notice opens its event here, above the list, and "Kembali" returns to the origin (INT-020; NAV-064). Read-only (C-53).
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { type Continuation, DataList, ListContinuation } from '@/components/data-list';
import { DateField } from '@/components/date-field';
import { FormField } from '@/components/form';
import { type MenuAction, PageHeader } from '@/components/page-header';
import { type Fact, FactsList } from '@/components/record-blocks';
import { RegionEmpty } from '@/components/region-state';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { DateTimeText } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';
import { type AccountHints } from '@/lib/navigation';
import { returnTarget } from '@/lib/previous-page';

type Row = {
    key: string;
    at: string;
    kind: string;
    account: string | null;
    actor: string | null;
    reason: string | null;
    route: string | null;
    target: { type: string | null; id: string } | null;
    identifier: string | null;
    address: string | null;
    agent: string | null;
    companies: string[];
    details: Record<string, string>;
};
type Filters = { jenis: string | null; dari: string | null; sampai: string | null; perusahaan: string | null };
type Props = {
    kinds: string[];
    companies: { code: string; name: string }[];
    filters: Filters;
    focus: Row | null;
    rows: Row[];
    more: string | null;
    cursor_failed: boolean;
    from_notice: boolean;
};

const LIST = '/aktivitas/log-keamanan';

export default function SecurityLog({ kinds, companies, filters, focus, rows, more, cursor_failed: cursorFailed, from_notice: fromNotice }: Props) {
    const { t, has } = useTranslation();
    const account = usePage<{ auth: AccountHints }>().props.auth;
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
    const kind = (value: string): string => {
        const key = `security_log.kind.${value}`;

        return has(key) ? t(key) : value;
    };
    const who = (row: Row): string => [row.account, row.actor === null ? null : `${t('security_log.field.actor')} ${row.actor}`].filter((part) => part !== null).join(' · ');
    const facts = (row: Row): Fact[] => [
        { label: t('activity.field.time'), value: <DateTimeText value={row.at} /> },
        { label: t('security_log.field.kind'), value: kind(row.kind) },
        { label: t('security_log.field.account'), value: row.account ?? '—' },
        ...(row.actor !== null ? [{ label: t('security_log.field.actor'), value: row.actor }] : []),
        { label: t('security_log.field.reason'), value: row.reason ?? '—' },
        { label: t('security_log.field.route'), value: row.route ?? '—' },
        ...(row.target !== null ? [{ label: t('security_log.field.target'), value: `${row.target.type ?? ''} ${row.target.id}`.trim() }] : []),
        { label: t('security_log.field.identifier'), value: row.identifier ?? '—' },
        { label: t('security_log.field.address'), value: row.address ?? '—' },
        { label: t('security_log.field.agent'), value: row.agent ?? '—' },
        ...(row.companies.length > 0 ? [{ label: t('security_log.field.companies'), value: row.companies.join(', ') }] : []),
        ...Object.entries(row.details).map(([name, value]) => ({ label: `${t('security_log.field.details')} · ${name}`, value })),
    ];
    const actions = (row: Row): MenuAction[] => [{ label: t('security_log.show'), onSelect: () => { setOpened(row); } }];
    const state: Continuation = cursorFailed ? 'cursor_failed' : next === null ? 'end' : 'more';

    return (
        <AppShell title={t('pages.pg_064.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_064.title')} actions={[{ label: t('actions.print'), onSelect: () => { window.print(); } }]} />
                {fromNotice && (
                    <div className="print:hidden">
                        <Button variant="outline" size="sm" render={<a href={returnTarget(account)} />}>
                            {t('dialogs.back')}
                        </Button>
                    </div>
                )}
                {focus !== null && (
                    <section aria-label={t('security_log.focus')} className="flex min-w-0 flex-col gap-3 rounded-md border border-border p-4">
                        <h2 className="text-label text-text-secondary">{t('security_log.focus')}</h2>
                        <FactsList facts={facts(focus)} />
                    </section>
                )}
                <div className="flex min-w-0 flex-col gap-4 md:flex-row md:flex-wrap md:items-end print:hidden">
                    <FormField label={t('security_log.filter.kind')} className="min-w-0 md:w-72">
                        {(control) => (
                            <NativeSelect {...control} value={filters.jenis ?? ''} onChange={(event) => { visit({ jenis: event.target.value === '' ? null : event.target.value }); }}>
                                <option value="">{t('security_log.filter.kind_all')}</option>
                                {kinds.map((value) => <option key={value} value={value}>{kind(value)}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
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
                            </NativeSelect>
                        )}
                    </FormField>
                </div>
                {shown.length === 0 && !cursorFailed ? (
                    <RegionEmpty text={t('security_log.empty')} />
                ) : (
                    <>
                        <DataList<Row>
                            caption={t('pages.pg_064.title')}
                            rows={shown}
                            rowKey={(row) => row.key}
                            rowHref={() => null}
                            rowActions={actions}
                            columns={[
                                { key: 'time', header: t('activity.field.time'), cell: (row) => <DateTimeText value={row.at} /> },
                                { key: 'kind', header: t('security_log.field.kind'), cell: (row) => kind(row.kind) },
                                { key: 'account', header: t('security_log.field.account'), cell: who },
                                { key: 'reason', header: t('security_log.field.reason'), priority: 'low', cell: (row) => row.reason ?? '' },
                                { key: 'route', header: t('security_log.field.route'), priority: 'low', cell: (row) => row.route ?? '' },
                                { key: 'companies', header: t('security_log.field.companies'), priority: 'low', cell: (row) => row.companies.join(', ') },
                            ]}
                            dense={(row) => ({
                                title: kind(row.kind),
                                secondary: who(row),
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
                    <DialogTitle>{opened === null ? '' : kind(opened.kind)}</DialogTitle>
                    {opened !== null && <FactsList facts={facts(opened)} columns={2} />}
                </DialogContent>
            </Dialog>
        </AppShell>
    );
}
