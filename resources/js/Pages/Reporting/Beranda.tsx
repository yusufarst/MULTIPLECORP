// PG-006 *Beranda* (ROUTE-006; NAV-006; SCR-05; D-IA-07; INFORMATION_ARCHITECTURE §9; TASK-058): the Owner's landing
// page — decisions and exceptions first, *Perlu keputusan Anda* (two rows on the phone, then "Lihat semua keputusan"),
// the four KPIs, *Angka Periode* with the receivables position and, in one company's view, the ageing; *Proyek yang
// perlu perhatian*, *Penagihan & Kas* and the operational queues folded into *Antrean operasional* with "Buka
// Pekerjaan". Five deferred groups, each its own loading and error (PT-19); refreshed by "Muat Ulang" or a background
// poll (PF-26). No command, KPI delta, sparkline, banner or greeting (IA §9; SLOP-18; SLOP-28; SLOP-38).
// APPR-042's pilot (CR-002 K-2, K-6; DIR-134 E4, E5): the same groups, data and order, as panels on the canvas. The four
// KPIs are cards that also carry the direct cost, the cash out and the disputed part, so one company's view needs no
// table and the consolidated view's table compares the companies alone. The ageing is labelled bars in one list read as
// text. A group's signals with no row are named in one line. An Owner with no company in scope first sees where to
// start. Every figure is the server's: nothing here adds, rounds or derives an amount.
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { type ReactNode, useId, useState } from 'react';

import { PageHeader } from '@/components/page-header';
import { TextLink } from '@/components/record-blocks';
import { RegionError, RegionLoading } from '@/components/region-state';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsiblePanel, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Icon } from '@/components/ui/icon';
import { Skeleton } from '@/components/ui/skeleton';
import { useDelayed } from '@/components/ui/spinner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { failed, type Failed, Refresh, type SignalRead, SignalSection } from '@/features/home/signals';
import AppShell from '@/layouts/AppShell';
import { clock, date, dateTime, money } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { type AccountHints, BUILT, isOwner } from '@/lib/navigation';
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
    auth: AccountHints;
    frame: { views: { id: string; code: string }[]; view: string | null; consolidated: boolean };
    attention?: Attention | Failed;
    operations?: SignalRead[] | Failed;
    billing?: SignalRead[] | Failed;
    completion?: SignalRead[] | Failed;
    figures?: Period | Failed;
};

const GROUPS = ['attention', 'operations', 'billing', 'completion', 'figures'];
// The ages from the newest to the oldest, a cool-to-warm ramp, the migrated opening balance last in the track's grey.
const BUCKETS: [string, TranslationKey, string][] = [
    ['CURRENT', 'pages.pg_006.bucket_current', 'bg-chart-age-1'],
    ['DAYS_1_30', 'pages.pg_006.bucket_1_30', 'bg-chart-age-2'],
    ['DAYS_31_60', 'pages.pg_006.bucket_31_60', 'bg-chart-age-3'],
    ['DAYS_61_90', 'pages.pg_006.bucket_61_90', 'bg-chart-age-4'],
    ['OVER_90', 'pages.pg_006.bucket_90', 'bg-chart-age-5'],
    ['MIGRASI', 'pages.pg_006.bucket_migrasi', 'bg-chart-track'],
];

const reloadOf = (group: string) => () => {
    router.reload({ only: [group] });
};

const negative = (amount: string): boolean => amount.trim().startsWith('-');

/** A region of the page: its heading, an optional count and action, its body — one surface on the canvas. */
function Panel({ title, count, action, children, className }: { title: string; count?: number; action?: ReactNode; children: ReactNode; className?: string }) {
    const id = useId();

    return (
        <section aria-labelledby={id} className={cn('flex min-w-0 flex-col gap-4 rounded-xl border border-border bg-surface p-4 md:p-5', className)}>
            <div className="flex min-w-0 flex-wrap items-center justify-between gap-x-3 gap-y-1">
                <h2 id={id} className="flex min-w-0 items-center gap-2 text-heading-sm font-semibold text-text-primary">
                    {title}
                    {count !== undefined && count > 0 && <span className="rounded-xs bg-sidebar-accent px-2 text-label font-semibold text-text-primary tabular-nums">{String(count)}</span>}
                </h2>
                {action}
            </div>
            {children}
        </section>
    );
}

/** An Owner with no company in scope: the first step, before any figure or queue can exist (blueprint D1, without K-6). */
function StartHere({ account }: { account: AccountHints }) {
    const { t } = useTranslation();
    const id = useId();

    if (!isOwner(account) || account.companies.length > 0) {
        return null;
    }

    return (
        <section aria-labelledby={id} className="flex min-w-0 flex-col items-start gap-4 rounded-xl border border-border bg-surface p-4 md:flex-row md:items-center md:justify-between md:p-5">
            <div className="flex min-w-0 flex-col gap-1">
                <h2 id={id} className="text-heading-sm font-semibold text-text-primary">
                    {t('pages.pg_006.start_title')}
                </h2>
                <p className="text-body text-text-secondary">{t('pages.pg_006.start_line')}</p>
            </div>
            {account.capabilities.includes('companies.manage') && BUILT.has('PG-059') && (
                <Button variant="primary" size="lg" className="shrink-0" render={<Link href="/pengaturan/perusahaan" />}>
                    {t('pages.pg_006.start_action')}
                </Button>
            )}
        </section>
    );
}

/** *Perlu keputusan Anda*: the security notices, the new review items, then the Owner's signals — two rows on the phone. */
function Decisions({ data }: { data: Attention | Failed | undefined }) {
    const { t, has } = useTranslation();
    const [all, setAll] = useState(false);

    if (data === undefined) {
        return (
            <Panel title={t('pages.pg_006.decisions')}>
                <RegionLoading />
            </Panel>
        );
    }

    if (failed(data)) {
        return (
            <Panel title={t('pages.pg_006.decisions')}>
                <RegionError reference="" onReload={reloadOf('attention')} />
            </Panel>
        );
    }

    const reviewed = data.review.counts.map((count) => `${count.company === '' ? t('pages.pg_008.no_company') : count.company} ${count.count}`).join(' · ');
    const signalled = data.signals.reduce((sum, signal) => sum + signal.rows.length, 0);
    const total = data.notices.length + (reviewed === '' ? 0 : 1) + signalled;
    // On the phone the first two decision rows show — the notices, then the review row, then the signals' rows in their
    // order —, the rest behind "Lihat semua keputusan" (IA §9; R1 b). The desktop shows them all.
    const folded = !all && total > 2;
    const notices = folded ? Math.min(data.notices.length, 2) : data.notices.length;
    const reviewShown = !folded || notices < 2;
    const budget = folded ? Math.max(0, 2 - notices - (reviewShown && reviewed !== '' ? 1 : 0)) : Number.POSITIVE_INFINITY;
    const phoneSignals = data.signals.reduce<{ left: number; list: SignalRead[] }>(
        (acc, signal) => {
            const rows = signal.rows.slice(0, acc.left);

            return rows.length === 0 ? acc : { left: acc.left - rows.length, list: [...acc.list, { ...signal, rows }] };
        },
        { left: budget, list: [] },
    ).list;

    return (
        <Panel title={t('pages.pg_006.decisions')} count={total}>
            {total === 0 ? (
                // DIR-134 E5's summary: one sentence for the whole region instead of one per empty part.
                <p className="text-body text-text-secondary motion-safe:animate-fade-in">{t('pages.pg_006.decisions_empty')}</p>
            ) : (
                <div className="flex min-w-0 flex-col gap-4 motion-safe:animate-fade-in">
                    {data.notices.length > 0 && (
                        <ul role="list" aria-label={t('pages.pg_006.notices')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {data.notices.map((notice, index) => {
                                const kind = `security_log.kind.${notice.kind}`;
                                const text = notice.kind === 'LINK_USED' || notice.kind === 'NEW_ADDRESS' ? t(`pages.pg_006.notice.${notice.kind}`, { v: notice.account ?? '' }) : has(kind) ? t(kind) : notice.kind;

                                return (
                                    <li key={notice.key} className={cn('flex min-h-row min-w-0 flex-wrap items-center gap-x-3 gap-y-1 py-2 md:min-h-control-lg', index >= notices && 'max-md:hidden')}>
                                        <TextLink href={notice.href} className="min-w-0">
                                            {text}
                                        </TextLink>
                                        <span className="ml-auto text-caption whitespace-nowrap text-text-tertiary">{dateTime(notice.at)}</span>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    {reviewed !== '' && (
                        <div className={cn('flex min-h-row min-w-0 flex-wrap items-center gap-x-3 gap-y-1 md:min-h-control-lg', !reviewShown && 'max-md:hidden')}>
                            <TextLink href={data.review.href}>{t('pages.pg_006.row_qs14')}</TextLink>
                            <span className="text-label text-text-tertiary tabular-nums">{reviewed}</span>
                        </div>
                    )}
                    <div className={cn('min-w-0', folded && 'max-md:hidden')}>
                        <SignalSection data={data.signals} reload={reloadOf('attention')} owner summarizeEmpty />
                    </div>
                    {folded && phoneSignals.length > 0 && (
                        <div className="min-w-0 md:hidden" data-slot="decisions-phone">
                            <SignalSection data={phoneSignals} reload={reloadOf('attention')} owner />
                        </div>
                    )}
                    {folded && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="self-start md:hidden"
                            onClick={() => {
                                setAll(true);
                            }}
                        >
                            {t('pages.pg_006.all_decisions_tpl', { v: String(total) })}
                        </Button>
                    )}
                </div>
            )}
        </Panel>
    );
}

/** The four KPI cards in a shape the loading cards keep, so nothing moves when the figures arrive. */
const KPI_GRID = 'grid min-w-0 grid-cols-1 gap-3 max-md:gap-0 max-md:divide-y max-md:divide-border max-md:rounded-xl max-md:border max-md:border-border max-md:bg-surface md:grid-cols-2 xl:grid-cols-4';
const KPI_CARD = 'flex min-w-0 flex-wrap items-baseline justify-between gap-x-3 gap-y-1 px-4 py-3 md:flex-col md:flex-nowrap md:items-start md:justify-start md:gap-2 md:rounded-xl md:border md:border-border md:bg-surface md:p-5';

function FiguresLoading() {
    const { t } = useTranslation();
    const shown = useDelayed(true);

    return (
        <div role="status" className={KPI_GRID}>
            {shown && (
                <>
                    <span className="sr-only">{t('states.loading')}</span>
                    {[0, 1, 2, 3].map((card) => (
                        <div key={card} className={cn(KPI_CARD, 'md:gap-3')}>
                            <Skeleton className="h-3 w-1/2" />
                            <Skeleton className="h-6 w-2/5 md:w-3/4" />
                        </div>
                    ))}
                </>
            )}
        </div>
    );
}

/** The four KPIs (IA §9), each with the figure the per-company table carried beside it. */
function KpiCards({ figures }: { figures: Figures }) {
    const { t } = useTranslation();
    const cards: { key: string; label: TranslationKey; value: string; detail: string | null }[] = [
        { key: 'sales', label: 'pages.pg_006.kpi_sales', value: figures.revenue, detail: `${t('pages.pg_006.direct_cost')} ${money(figures.direct_cost)}` },
        { key: 'cash', label: 'pages.pg_006.kpi_cash_in', value: figures.cash_in, detail: `${t('pages.pg_006.cash_out')} ${money(figures.cash_out)}` },
        { key: 'receivable', label: 'pages.pg_006.kpi_receivable', value: figures.receivable.outstanding, detail: figures.receivable.disputed === '0.00' ? null : t('pages.pg_006.disputed_tpl', { v: money(figures.receivable.disputed) }) },
        { key: 'profit', label: 'pages.pg_006.kpi_profit', value: figures.profit, detail: null },
    ];

    return (
        <dl className={cn(KPI_GRID, 'motion-safe:animate-fade-in')}>
            {cards.map((card) => (
                <div key={card.key} className={KPI_CARD}>
                    <dt className="min-w-0 text-label text-text-secondary">{t(card.label)}</dt>
                    <dd className={cn('text-heading-sm font-semibold whitespace-nowrap text-text-primary tabular-nums md:text-figure-lg', negative(card.value) && 'text-danger-fg')}>{money(card.value)}</dd>
                    {card.detail !== null && <dd className="basis-full text-caption text-text-tertiary tabular-nums md:basis-auto">{card.detail}</dd>}
                </div>
            ))}
        </dl>
    );
}

/** The companies side by side, the consolidated figures being the cards above (CS-08); on the phone one block per company. */
function CompanyTable({ rows }: { rows: Figures[] }) {
    const { t } = useTranslation();
    const fields = (row: Figures): [TranslationKey, string][] => [
        ['pages.pg_006.kpi_sales', row.revenue],
        ['pages.pg_006.direct_cost', row.direct_cost],
        ['pages.pg_006.kpi_profit', row.profit],
        ['pages.pg_006.kpi_cash_in', row.cash_in],
        ['pages.pg_006.cash_out', row.cash_out],
        ['pages.pg_006.kpi_receivable', row.receivable.outstanding],
    ];

    return (
        <>
            <ul role="list" aria-label={t('pages.pg_006.figures')} className="flex min-w-0 flex-col divide-y divide-border md:hidden">
                {rows.map((row) => (
                    <li key={row.company ?? 'gabungan'} className="flex min-w-0 flex-col gap-2 py-3 first:pt-0 last:pb-0">
                        <p data-code className="text-body font-semibold text-text-primary">
                            {row.company ?? t('pages.pg_006.view.gabungan')}
                        </p>
                        <dl className="flex min-w-0 flex-col gap-1">
                            {fields(row).map(([label, value]) => (
                                <div key={label} className="flex min-w-0 items-baseline justify-between gap-3">
                                    <dt className="min-w-0 text-label text-text-secondary">{t(label)}</dt>
                                    <dd className={cn('text-label font-semibold whitespace-nowrap text-text-primary tabular-nums', negative(value) && 'text-danger-fg')}>{money(value)}</dd>
                                </div>
                            ))}
                        </dl>
                        {row.receivable.disputed !== '0.00' && <p className="text-caption text-text-tertiary">{t('pages.pg_006.disputed_tpl', { v: money(row.receivable.disputed) })}</p>}
                    </li>
                ))}
            </ul>
            <div className="min-w-0 max-md:hidden">
                <Table aria-label={t('pages.pg_006.figures')} scrollLabel={t('pages.pg_006.figures')}>
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
                        {rows.map((row) => (
                            <TableRow key={row.company ?? 'gabungan'}>
                                <TableHead scope="row" data-code className="font-semibold">
                                    {row.company ?? t('pages.pg_006.view.gabungan')}
                                </TableHead>
                                <TableCell numeric>{money(row.revenue)}</TableCell>
                                <TableCell numeric>{money(row.direct_cost)}</TableCell>
                                <TableCell numeric className={cn(negative(row.profit) && 'text-danger-fg')}>
                                    {money(row.profit)}
                                </TableCell>
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
            </div>
        </>
    );
}

/** One company's receivables by age: each age a line with its bar and its amount, read as text; the bar is drawn only. */
function AgeingBars({ figures }: { figures: Figures }) {
    const { t } = useTranslation();
    const id = useId();
    const ages = BUCKETS.map(([bucket, label, colour]) => ({ bucket, label, colour, amount: figures.receivable.buckets[bucket] ?? '0' }));
    // The longest bar is the largest age; the lengths are drawn in proportion and never shown as a number.
    const longest = Math.max(0, ...ages.map((age) => Number(age.amount)));

    return (
        <section aria-labelledby={id} data-slot="ageing" className="flex min-w-0 flex-col gap-3">
            <h3 id={id} className="text-body font-semibold text-text-primary">
                {t('pages.pg_006.ageing')}
            </h3>
            <ul role="list" className="flex min-w-0 flex-col gap-3">
                {ages.map((age) => (
                    <li key={age.bucket} className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                        <span className="min-w-0 flex-1 text-label text-text-secondary md:w-44 md:flex-none">{t(age.label)}</span>
                        <span aria-hidden="true" className="h-2 basis-full overflow-hidden rounded-full bg-surface-sunken max-md:order-last md:min-w-0 md:flex-1 md:basis-0">
                            <span className={cn('block h-full rounded-full', age.colour)} style={{ inlineSize: `${String(longest > 0 ? (Number(age.amount) / longest) * 100 : 0)}%` }} />
                        </span>
                        <span className="shrink-0 text-label font-semibold whitespace-nowrap text-text-primary tabular-nums md:w-36 md:text-right">{money(age.amount)}</span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/** *Angka Periode*: the period and its time, the four KPIs, the companies compared or one company's ageing, "Rincian angka". */
function PeriodFigures({ data, view }: { data: Period | Failed | undefined; view: string | null }) {
    const { t } = useTranslation();
    const id = useId();
    const ready = data !== undefined && !failed(data);
    const focus = ready ? (data.consolidated ?? (data.companies.length === 1 ? (data.companies[0] ?? null) : null)) : null;
    const compared = ready && data.companies.length > 1;
    const ageing = view !== null && focus !== null;

    // No company in scope yet: "Mulai di sini" says where to begin, and an empty period says nothing more.
    if (ready && data.companies.length === 0 && data.consolidated === null) {
        return null;
    }

    return (
        <section aria-labelledby={id} className="flex min-w-0 flex-col gap-3">
            <div className="flex min-w-0 flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h2 id={id} className="text-heading-sm font-semibold text-text-primary">
                    {t('pages.pg_006.figures')}
                </h2>
                {ready && (
                    <p className="text-caption text-text-tertiary tabular-nums">
                        {t('pages.pg_006.period_name')} {date(data.from)} – {date(data.to)} · {t('pages.pg_006.as_of_tpl', { v: clock(data.as_of) })}
                    </p>
                )}
            </div>
            {data === undefined && <FiguresLoading />}
            {data !== undefined && failed(data) && <RegionError reference="" onReload={reloadOf('figures')} />}
            {focus !== null && <KpiCards figures={focus} />}
            {(compared || ageing || BUILT.has('PG-052')) && ready && (
                <div className="flex min-w-0 flex-col gap-4 rounded-xl border border-border bg-surface p-4 motion-safe:animate-fade-in md:p-5">
                    {compared && <CompanyTable rows={data.companies} />}
                    {ageing && <AgeingBars figures={focus} />}
                    {BUILT.has('PG-052') && (
                        <TextLink href="/laporan/gabungan" className="self-start">
                            {t('pages.pg_006.details')}
                        </TextLink>
                    )}
                </div>
            )}
        </section>
    );
}

export default function Beranda() {
    const { t } = useTranslation();
    const { auth, frame, attention, operations, billing, completion, figures } = usePage<Props>().props;
    const [open, setOpen] = useState(false);
    const views = [...(frame.consolidated ? [{ id: null, code: t('pages.pg_006.view.gabungan') }] : []), ...frame.views.map((view) => ({ id: view.id, code: view.code }))];
    const queued = Array.isArray(operations) ? operations.filter((signal) => signal.rows.length > 0) : [];

    return (
        <AppShell title={t('pages.pg_006.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_006.title')} facts={<Refresh groups={GROUPS} />} />
                {views.length > 1 && (
                    <nav aria-label={t('pages.pg_006.view_label')} className="-mt-2 flex min-w-0 flex-wrap gap-1 self-start rounded-lg border border-border bg-surface p-1">
                        {views.map((view) => {
                            const current = view.id === null ? frame.view === null : frame.view === view.code;

                            return (
                                <Link
                                    key={view.id ?? 'gabungan'}
                                    href={view.id === null ? '/beranda' : `/beranda?perusahaan=${view.id}`}
                                    aria-current={current ? 'page' : undefined}
                                    className={cn(
                                        'inline-flex min-h-10 items-center rounded-md px-3 text-label font-medium text-text-secondary outline-none transition-colors duration-(--mc-dur-hover) hover:bg-nav-hover hover:text-text-primary focus-visible:outline-2 focus-visible:outline-solid focus-visible:outline-focus pointer-coarse:min-h-touch',
                                        current && 'bg-sidebar-accent font-semibold text-text-primary hover:bg-sidebar-accent',
                                    )}
                                >
                                    {view.code}
                                </Link>
                            );
                        })}
                    </nav>
                )}
                <StartHere account={auth} />
                <Decisions data={attention} />
                <PeriodFigures data={figures} view={frame.view} />
                <div className="grid min-w-0 grid-cols-1 items-start gap-6 xl:grid-cols-2">
                    {/* A group whose signals the account reads none of is absent, heading included (PJ-13). */}
                    {!(Array.isArray(completion) && completion.length === 0) && (
                        <Panel title={t('pages.pg_006.attention')}>
                            <SignalSection data={completion} reload={reloadOf('completion')} summarizeEmpty />
                        </Panel>
                    )}
                    {!(Array.isArray(billing) && billing.length === 0) && (
                        <Panel title={t('pages.pg_006.billing')}>
                            <SignalSection data={billing} reload={reloadOf('billing')} summarizeEmpty />
                        </Panel>
                    )}
                </div>
                <section aria-label={t('pages.pg_006.ops')} className="flex min-w-0 flex-col rounded-xl border border-border bg-surface p-4 md:p-5">
                    <Collapsible open={open} onOpenChange={setOpen}>
                        <div className="flex min-w-0 flex-wrap items-center justify-between gap-3">
                            <h2 className="min-w-0 text-heading-sm font-semibold text-text-primary">
                                <CollapsibleTrigger className="group/ops flex min-h-touch items-center gap-2 rounded-sm text-left outline-none focus-visible:outline-2 focus-visible:outline-solid focus-visible:outline-focus">
                                    {t('pages.pg_006.ops')}
                                    {Array.isArray(operations) && queued.length > 0 && <span className="rounded-xs bg-sidebar-accent px-2 text-label font-semibold text-text-primary tabular-nums">{String(queued.length)}</span>}
                                    <Icon icon={ChevronDown} size="sm" className="text-text-tertiary transition-transform duration-(--mc-dur-fast) group-data-[panel-open]/ops:rotate-180 motion-reduce:transition-none" />
                                </CollapsibleTrigger>
                            </h2>
                            <TextLink href="/pekerjaan">{t('pages.pg_006.open_work')}</TextLink>
                        </div>
                        <CollapsiblePanel hiddenUntilFound>
                            <div className="pt-4">
                                {Array.isArray(operations) && operations.length === 0 ? (
                                    <p className="text-body text-text-secondary">{t('pages.pg_007.docs_empty')}</p>
                                ) : (
                                    <SignalSection data={operations} reload={reloadOf('operations')} summarizeEmpty />
                                )}
                            </div>
                        </CollapsiblePanel>
                    </Collapsible>
                </section>
            </div>
        </AppShell>
    );
}
