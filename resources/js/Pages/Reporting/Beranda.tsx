// PG-006 *Beranda* (ROUTE-006; NAV-006; SCR-05; D-IA-07; INFORMATION_ARCHITECTURE §9; TASK-058): the Owner's landing
// page — decisions and exceptions first, *Perlu keputusan Anda* (two rows on the phone, then "Lihat semua keputusan"),
// the four KPIs, *Angka Periode* with the receivables position and, in one company's view, the ageing chart with its
// table; *Proyek yang perlu perhatian*, *Penagihan & Kas* and the operational queues folded into *Antrean operasional*
// with "Buka Pekerjaan". Five deferred groups, each its own spinner and error (PT-19); refreshed by "Muat Ulang" or a
// background poll (PF-26). No command, KPI delta, sparkline, banner or greeting (IA §9; SLOP-18; SLOP-28; SLOP-38).
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Bar, BarChart, LabelList, XAxis, YAxis } from 'recharts';

import { PageHeader } from '@/components/page-header';
import { MetricStrip, TextLink } from '@/components/record-blocks';
import { RegionEmpty, RegionError, RegionLoading } from '@/components/region-state';
import { Button } from '@/components/ui/button';
import { ChartContainer } from '@/components/ui/chart';
import { Collapsible, CollapsiblePanel, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Icon } from '@/components/ui/icon';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { failed, type Failed, Refresh, type SignalRead, SignalSection } from '@/features/home/signals';
import AppShell from '@/layouts/AppShell';
import { dateTime, money, time } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { BUILT } from '@/lib/navigation';
import { cn } from '@/lib/utils';

type Notice = { key: string; kind: string; at: string; account: string | null; href: string };
type Attention = { notices: Notice[]; review: { since: string; counts: { company: string; count: string }[]; href: string }; signals: SignalRead[] };
type Figures = {
    company: string | null;
    revenue: string;
    direct_cost: string;
    project_profit: string;
    profit: string;
    cash_in: string;
    cash_out: string;
    receivable: { outstanding: string; disputed: string; buckets: Record<string, string> };
};
type Period = { as_of: string; from: string; to: string; companies: Figures[]; consolidated: Figures | null };
type Props = {
    frame: { views: { id: string; code: string }[]; view: string | null; consolidated: boolean };
    attention?: Attention | Failed;
    operations?: SignalRead[] | Failed;
    billing?: SignalRead[] | Failed;
    completion?: SignalRead[] | Failed;
    figures?: Period | Failed;
};

const GROUPS = ['attention', 'operations', 'billing', 'completion', 'figures'];
const BUCKETS: [string, TranslationKey, string][] = [
    ['CURRENT', 'pages.pg_006.bucket_current', 'var(--color-chart-age-1)'],
    ['DAYS_1_30', 'pages.pg_006.bucket_1_30', 'var(--color-chart-age-2)'],
    ['DAYS_31_60', 'pages.pg_006.bucket_31_60', 'var(--color-chart-age-3)'],
    ['DAYS_61_90', 'pages.pg_006.bucket_61_90', 'var(--color-chart-age-4)'],
    ['OVER_90', 'pages.pg_006.bucket_90', 'var(--color-chart-age-5)'],
    ['MIGRASI', 'pages.pg_006.bucket_migrasi', 'var(--color-chart-track)'],
];

const reloadOf = (group: string) => () => {
    router.reload({ only: [group] });
};

function Region({ title, children, className }: { title: string; children: React.ReactNode; className?: string }) {
    return (
        <section aria-label={title} className={cn('flex min-w-0 flex-col gap-3', className)}>
            <h2 className="text-heading-sm font-semibold text-text-primary">{title}</h2>
            {children}
        </section>
    );
}

/** *Perlu keputusan Anda*: the security notices, the new review items, then the Owner's signals — two rows on the phone. */
function Decisions({ data }: { data: Attention | Failed | undefined }) {
    const { t, has } = useTranslation();
    const [all, setAll] = useState(false);

    if (data === undefined) {
        return <RegionLoading />;
    }

    if (failed(data)) {
        return <RegionError reference="" onReload={reloadOf('attention')} />;
    }

    const reviewed = data.review.counts.map((count) => `${count.company === '' ? t('pages.pg_008.no_company') : count.company} ${count.count}`).join(' · ');
    const above = data.notices.length + (reviewed === '' ? 0 : 1);
    const signalled = data.signals.reduce((sum, signal) => sum + signal.rows.length, 0);
    const total = above + signalled;
    // On the phone two rows show first: the signals wait behind "Lihat semua keputusan" only when two rows already stand above them.
    const folded = !all && above >= 2 && signalled > 0;

    return (
        <div className="flex min-w-0 flex-col gap-4">
            {data.notices.length > 0 && (
                <ul role="list" aria-label={t('pages.pg_006.notices')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                    {data.notices.map((notice) => {
                        const kind = `security_log.kind.${notice.kind}`;
                        const text = notice.kind === 'LINK_USED' || notice.kind === 'NEW_ADDRESS' ? t(`pages.pg_006.notice.${notice.kind}`, { v: notice.account ?? '' }) : has(kind) ? t(kind) : notice.kind;

                        return (
                        <li key={notice.key} className="flex min-h-row min-w-0 flex-wrap items-center gap-x-3 gap-y-1 py-2">
                            <TextLink href={notice.href} className="min-w-0">
                                {text}
                            </TextLink>
                            <span className="ml-auto text-caption whitespace-nowrap text-text-tertiary">{dateTime(notice.at)}</span>
                        </li>
                        );
                    })}
                </ul>
            )}
            <div className="flex min-h-row min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                {reviewed === '' ? (
                    <span className="text-body text-text-secondary">{t('signals.qs_14_empty')}</span>
                ) : (
                    <>
                        <TextLink href={data.review.href}>{t('pages.pg_006.row_qs14')}</TextLink>
                        <span className="text-label text-text-tertiary tabular-nums">{reviewed}</span>
                    </>
                )}
            </div>
            <div className={cn('min-w-0', folded && 'max-md:hidden')}>
                <SignalSection data={data.signals} reload={reloadOf('attention')} owner />
            </div>
            {folded && (
                <Button variant="ghost" size="sm" className="self-start md:hidden" onClick={() => { setAll(true); }}>
                    {t('pages.pg_006.all_decisions_tpl', { v: String(total) })}
                </Button>
            )}
        </div>
    );
}

/** The four KPIs and *Angka Periode*: per company, *Gabungan* only with `reports.consolidated` (CS-08); one company's ageing chart with its table. */
function PeriodFigures({ data, view }: { data: Period | Failed | undefined; view: string | null }) {
    const { t } = useTranslation();

    if (data === undefined) {
        return <RegionLoading />;
    }

    if (failed(data)) {
        return <RegionError reference="" onReload={reloadOf('figures')} />;
    }

    const focus = data.consolidated ?? (data.companies.length === 1 ? (data.companies[0] ?? null) : null);
    const chart = view !== null && focus !== null ? BUCKETS.map(([bucket, label, color]) => ({ bucket, label: t(label), amount: Number(focus.receivable.buckets[bucket] ?? '0'), text: money(focus.receivable.buckets[bucket] ?? '0'), fill: color })) : [];

    return (
        <div className="flex min-w-0 flex-col gap-5">
            {focus !== null && (
                <MetricStrip
                    metrics={[
                        { label: t('pages.pg_006.kpi_sales'), value: money(focus.revenue), numeric: true },
                        { label: t('pages.pg_006.kpi_cash_in'), value: money(focus.cash_in), numeric: true },
                        { label: t('pages.pg_006.kpi_receivable'), value: money(focus.receivable.outstanding), numeric: true },
                        { label: t('pages.pg_006.kpi_profit'), value: money(focus.profit), numeric: true },
                    ]}
                />
            )}
            <p className="text-caption text-text-tertiary">{t('pages.pg_006.as_of_tpl', { v: time(data.as_of) })}</p>
            <Table aria-label={t('pages.pg_006.figures')}>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        <TableHead />
                        <TableHead numeric>{t('pages.pg_006.kpi_sales')}</TableHead>
                        <TableHead numeric>{t('pages.pg_006.direct_cost')}</TableHead>
                        <TableHead numeric>{t('pages.pg_006.kpi_profit')}</TableHead>
                        <TableHead numeric>{t('pages.pg_006.kpi_cash_in')}</TableHead>
                        <TableHead numeric>{t('pages.pg_006.cash_out')}</TableHead>
                        <TableHead numeric>{t('pages.pg_006.kpi_receivable')}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {[...data.companies, ...(data.consolidated === null ? [] : [data.consolidated])].map((row) => (
                        <TableRow key={row.company ?? 'gabungan'}>
                            <TableHead scope="row" className="font-normal">{row.company ?? t('pages.pg_006.view.gabungan')}</TableHead>
                            <TableCell numeric>{money(row.revenue)}</TableCell>
                            <TableCell numeric>{money(row.direct_cost)}</TableCell>
                            <TableCell numeric>{money(row.profit)}</TableCell>
                            <TableCell numeric>{money(row.cash_in)}</TableCell>
                            <TableCell numeric>{money(row.cash_out)}</TableCell>
                            <TableCell numeric>
                                {money(row.receivable.outstanding)}
                                {row.receivable.disputed !== '0.00' && <span className="block text-caption text-text-tertiary">{t('pages.pg_006.disputed_tpl', { v: money(row.receivable.disputed) })}</span>}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            {chart.length > 0 && (
                <section aria-label={t('pages.pg_006.ageing')} data-slot="ageing" className="flex min-w-0 flex-col gap-3">
                    <h3 className="text-body font-semibold">{t('pages.pg_006.ageing')}</h3>
                    <ChartContainer config={{}} className="aspect-auto h-56 w-full" aria-hidden="true">
                        <BarChart data={chart} layout="vertical" margin={{ left: 8, right: 96 }}>
                            <XAxis type="number" hide />
                            <YAxis type="category" dataKey="label" width={150} tickLine={false} axisLine={false} />
                            <Bar dataKey="amount" isAnimationActive={false} radius={3}>
                                <LabelList dataKey="text" position="right" className="fill-text-secondary" />
                            </Bar>
                        </BarChart>
                    </ChartContainer>
                    <Table aria-label={t('pages.pg_006.ageing_table')}>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>{t('pages.pg_006.col_bucket')}</TableHead>
                                <TableHead numeric>{t('pages.pg_006.col_amount')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {chart.map((entry) => (
                                <TableRow key={entry.bucket}>
                                    <TableCell>{entry.label}</TableCell>
                                    <TableCell numeric>{entry.text}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            )}
            {BUILT.has('PG-052') && <TextLink href="/laporan/gabungan">{t('pages.pg_006.details')}</TextLink>}
        </div>
    );
}

export default function Beranda() {
    const { t } = useTranslation();
    const { frame, attention, operations, billing, completion, figures } = usePage<Props>().props;
    const [open, setOpen] = useState(false);
    const views = [...(frame.consolidated ? [{ id: null, code: t('pages.pg_006.view.gabungan') }] : []), ...frame.views.map((view) => ({ id: view.id, code: view.code }))];
    const queued = Array.isArray(operations) ? operations.filter((signal) => signal.rows.length > 0) : [];

    return (
        <AppShell title={t('pages.pg_006.title')}>
            <div className="flex min-w-0 flex-col gap-8">
                <PageHeader title={t('pages.pg_006.title')} facts={<Refresh groups={GROUPS} />} />
                {views.length > 1 && (
                    <nav aria-label={t('pages.pg_006.view_label')} className="flex min-w-0 flex-wrap gap-2">
                        {views.map((view) => {
                            const current = view.id === null ? frame.view === null : frame.view === view.code;

                            return (
                                <Link
                                    key={view.id ?? 'gabungan'}
                                    href={view.id === null ? '/beranda' : `/beranda?perusahaan=${view.id}`}
                                    aria-current={current ? 'page' : undefined}
                                    className={cn('inline-flex min-h-touch items-center rounded-md px-3 text-label outline-none focus-visible:outline-2 focus-visible:outline-focus', current ? 'bg-surface-sunken font-semibold text-text-primary' : 'text-text-secondary hover:text-text-primary')}
                                >
                                    {view.code}
                                </Link>
                            );
                        })}
                    </nav>
                )}
                <Region title={t('pages.pg_006.decisions')}>
                    <Decisions data={attention} />
                </Region>
                <Region title={t('pages.pg_006.figures')}>
                    <PeriodFigures data={figures} view={frame.view} />
                </Region>
                <Region title={t('pages.pg_006.attention')}>
                    <SignalSection data={completion} reload={reloadOf('completion')} />
                </Region>
                <Region title={t('pages.pg_006.billing')}>
                    <SignalSection data={billing} reload={reloadOf('billing')} />
                </Region>
                <section aria-label={t('pages.pg_006.ops')} className="flex min-w-0 flex-col gap-3">
                    <Collapsible open={open} onOpenChange={setOpen}>
                        <div className="flex min-w-0 flex-wrap items-center justify-between gap-3">
                            <CollapsibleTrigger className="group/ops flex min-h-touch items-center gap-2 rounded-sm text-left text-heading-sm font-semibold outline-none focus-visible:outline-2 focus-visible:outline-focus">
                                {t('pages.pg_006.ops')}
                                {Array.isArray(operations) && <span className="text-label font-normal text-text-tertiary tabular-nums">{String(queued.length)}</span>}
                                <Icon icon={ChevronDown} size="sm" className="text-text-tertiary group-data-[panel-open]/ops:rotate-180" />
                            </CollapsibleTrigger>
                            <TextLink href="/pekerjaan">{t('pages.pg_006.open_work')}</TextLink>
                        </div>
                        <CollapsiblePanel>
                            {Array.isArray(operations) && operations.length === 0 ? <RegionEmpty text={t('pages.pg_007.docs_empty')} /> : <SignalSection data={operations} reload={reloadOf('operations')} />}
                        </CollapsiblePanel>
                    </Collapsible>
                </section>
            </div>
        </AppShell>
    );
}
