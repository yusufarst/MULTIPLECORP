import { Link } from '@inertiajs/react';
import { Check, ChevronDown, type LucideIcon, TriangleAlert } from 'lucide-react';
import { type ComponentProps, type ReactNode, useState } from 'react';

import { type StatusState, StatusText } from '@/components/status-badge';
import { DateText, DateTimeText, Quantity } from '@/components/values';
import { Card } from '@/components/ui/card';
import { Collapsible, CollapsiblePanel, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Icon } from '@/components/ui/icon';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

// The record workspace's blocks (DESIGN_SYSTEM §9.1, §10, §14). Every figure, state and label is the server's;
// none is derived, summed or rounded here (UXI-14). A bordered panel only where a group needs its own boundary —
// never a panel inside a panel (PT-12; SLOP-01, SLOP-02).

export type Fact = { label: ReactNode; value: ReactNode; numeric?: boolean };

/** PT-12's facts summary: a definition list in two to four columns, stacked on the phone. */
export function FactsList({ facts, columns = 3, className }: { facts: Fact[]; columns?: 2 | 3 | 4; className?: string }) {
    return (
        <dl
            data-slot="facts"
            className={cn('grid min-w-0 grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2', columns >= 3 && 'lg:grid-cols-3', columns === 4 && 'xl:grid-cols-4', className)}
        >
            {facts.map((fact, index) => (
                <div key={index} className="flex min-w-0 flex-col gap-0.5">
                    <dt className="text-label text-text-tertiary">{fact.label}</dt>
                    <dd className={cn('min-w-0 text-body text-text-primary [overflow-wrap:anywhere]', fact.numeric === true && 'tabular-nums')}>{fact.value}</dd>
                </div>
            ))}
        </dl>
    );
}

/** The metric strip: key figures in one row separated by hairlines, wrapping on narrow widths (§10). */
export function MetricStrip({ metrics, className }: { metrics: Fact[]; className?: string }) {
    return (
        <dl data-slot="metric-strip" className={cn('flex min-w-0 flex-wrap divide-x divide-border-subtle rounded-lg border border-border bg-surface', className)}>
            {metrics.map((metric, index) => (
                <div key={index} className="flex min-w-36 flex-1 flex-col gap-1 px-4 py-3">
                    <dt className="text-label text-text-tertiary">{metric.label}</dt>
                    <dd className="text-heading-sm font-semibold whitespace-nowrap text-text-primary tabular-nums">{metric.value}</dd>
                </div>
            ))}
        </dl>
    );
}

const DISC_TONE = {
    olive: 'bg-tint-olive text-tint-olive-ink',
    blue: 'bg-tint-blue text-tint-blue-ink',
    peach: 'bg-tint-peach text-tint-peach-ink',
    mint: 'bg-tint-mint text-tint-mint-ink',
    red: 'bg-tint-red text-tint-red-ink',
    neutral: 'bg-tint-neutral text-tint-neutral-ink',
} as const;

export type DiscTone = keyof typeof DISC_TONE;

/** The icon disc (§10 "Progress bar, keycap, avatar, icon disc, link"): a decorative tinted circle; the text says the rest. */
export function IconDisc({ icon, tone = 'neutral', size = 'md', className }: { icon: LucideIcon; tone?: DiscTone; size?: 'sm' | 'md' | 'row' | 'kpi' | 'lg'; className?: string }) {
    const box = { sm: 'size-disc-sm', md: 'size-disc-md', row: 'size-disc-row', kpi: 'size-disc-kpi', lg: 'size-disc-lg' }[size];

    return (
        <span aria-hidden data-slot="icon-disc" className={cn('inline-flex shrink-0 items-center justify-center rounded-full', box, DISC_TONE[tone], className)}>
            <Icon icon={icon} size={size === 'sm' ? 'xs' : size === 'lg' || size === 'kpi' ? 'md' : 'sm'} />
        </span>
    );
}

/** The KPI tile: disc, label and value, the whole card a link — no delta, sparkline or comparison (VP-3; SLOP-18). */
export function KpiTile({ icon, tone, label, value, href }: { icon: LucideIcon; tone?: DiscTone; label: ReactNode; value: ReactNode; href: string }) {
    return (
        <Link href={href} className="group/kpi block rounded-lg outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">
            <Card className="flex flex-row items-center gap-3 p-4 transition-colors duration-(--mc-dur-hover) group-hover/kpi:border-border-strong">
                <IconDisc icon={icon} tone={tone} size="kpi" />
                <span className="flex min-w-0 flex-col gap-0.5">
                    <span className="text-label text-text-tertiary">{label}</span>
                    <span className="text-heading font-semibold whitespace-nowrap text-text-primary tabular-nums">{value}</span>
                </span>
            </Card>
        </Link>
    );
}

export type PositionLine = { label: ReactNode; value: ReactNode; emphasis?: boolean; note?: ReactNode };

/** PT-24's financial position block (§14.1): the server's lines with right-aligned figures, the last in 600 weight;
    a contra or correction is its own line, never a silently changed figure. */
export function PositionBlock({ title, lines }: { title: ReactNode; lines: PositionLine[] }) {
    return (
        <section aria-label={typeof title === 'string' ? title : undefined} className="flex min-w-0 flex-col gap-2">
            <h3 className="text-heading-sm font-semibold text-text-primary">{title}</h3>
            <dl className="flex min-w-0 flex-col divide-y divide-border-subtle">
                {lines.map((line, index) => (
                    <div key={index} className={cn('flex min-w-0 items-baseline justify-between gap-4 py-2', line.emphasis === true && 'font-semibold')}>
                        <dt className="min-w-0 text-body text-text-secondary">
                            {line.label}
                            {line.note !== undefined && <span className="block text-caption text-text-tertiary">{line.note}</span>}
                        </dt>
                        <dd className={cn('shrink-0 text-body whitespace-nowrap text-text-primary tabular-nums', line.emphasis === true && 'font-semibold')}>{line.value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

export type StockFigures = { physical: string; reserved: string; unusable: string; available: string; unit: string; alternate?: { physical: string; reserved: string; unusable: string; available: string; unit: string } };

/** PT-17's quantity block (§14.2): *Stok fisik* · *Direservasi* · *Tidak layak pakai* · **Tersedia**, the formula in
    muted text, base units with the alternate unit as a hint; restock advice in ink after the warning icon. */
export function InventoryBlock({ figures, belowMinimum }: { figures: StockFigures; belowMinimum?: string }) {
    const { t } = useTranslation();
    const alt = figures.alternate;
    const line = (key: 'physical' | 'reserved' | 'unusable' | 'available') => (
        <Quantity value={figures[key]} unit={figures.unit} alternate={alt === undefined ? undefined : { value: alt[key], unit: alt.unit }} />
    );

    return (
        <div data-slot="inventory-block" className="flex min-w-0 flex-col gap-2">
            <PositionBlock
                title={t('glossary.gl_079')}
                lines={[
                    { label: t('glossary.gl_076'), value: line('physical') },
                    { label: t('glossary.gl_077'), value: line('reserved') },
                    { label: t('glossary.gl_078'), value: line('unusable') },
                    { label: t('glossary.gl_079'), value: line('available'), emphasis: true },
                ]}
            />
            <p className="text-caption text-text-tertiary">{t('inventory.available_formula')}</p>
            {belowMinimum !== undefined && (
                <p className="flex items-center gap-2 text-body text-text-primary">
                    <Icon icon={TriangleAlert} size="sm" className="text-warning-fg" />
                    {t('inventory.below_minimum', { jumlah: belowMinimum })}
                </p>
            )}
        </div>
    );
}

export type TimelineEntry = {
    id: string;
    /** The entry's place in the record's chronology, 1 for the first (§10 "Timeline"). */
    sequence: number;
    recordedAt: string;
    /** The business date, only when it differs from the recording day. */
    businessDate?: string;
    /** The acting account where the viewer may see it; null for an entry no account caused ("Sistem"). */
    actor: string | null;
    sentence: ReactNode;
    reason?: ReactNode;
    changes?: { field: string; before: string; after: string }[];
    corrects?: { label: string; href: string };
    correctedBy?: { label: string; href: string };
};

/** PT-18's activity timeline, newest first; projected by the server (PJ-11) — absent keys stay absent. No avatars,
    reactions, comments or bubbles (§18). "Muat Lagi" is the caller's ListContinuation. */
export function Timeline({ label, entries }: { label: string; entries: TimelineEntry[] }) {
    const { t } = useTranslation();

    return (
        <ol aria-label={label} data-slot="timeline" className="flex min-w-0 flex-col">
            {entries.map((entry) => (
                <li key={entry.id} className="relative flex min-w-0 gap-3 pb-4 last:pb-0">
                    <span aria-hidden className="relative flex w-disc-sm shrink-0 flex-col items-center">
                        <span className="inline-flex size-disc-sm items-center justify-center rounded-full border border-border bg-surface text-caption text-text-tertiary tabular-nums">{entry.sequence}</span>
                        <span className="mt-1 w-px flex-1 bg-border-subtle" />
                    </span>
                    <div className="flex min-w-0 flex-1 flex-col gap-1 pt-0.5">
                        <p className="text-caption text-text-tertiary">
                            {entry.businessDate !== undefined ? (
                                <>
                                    {t('pt25.label')} <DateText value={entry.businessDate} /> · <DateTimeText value={entry.recordedAt} />
                                </>
                            ) : (
                                <DateTimeText value={entry.recordedAt} />
                            )}
                            {' · '}
                            <span className="text-text-secondary">{entry.actor ?? t('timeline.system')}</span>
                        </p>
                        <p className="text-body text-text-primary">{entry.sentence}</p>
                        {entry.reason !== undefined && <p className="text-body text-text-secondary">{entry.reason}</p>}
                        {entry.changes !== undefined && entry.changes.length > 0 && (
                            <ul role="list" className="flex flex-col gap-0.5 text-caption text-text-secondary">
                                {entry.changes.map((change) => (
                                    <li key={change.field}>{t('timeline.change', { kolom: change.field, lama: change.before, baru: change.after })}</li>
                                ))}
                            </ul>
                        )}
                        {(entry.corrects !== undefined || entry.correctedBy !== undefined) && (
                            <p className="flex flex-wrap gap-x-4 text-caption">
                                {entry.corrects !== undefined && (
                                    <span>
                                        {t('timeline.corrects')}: <TextLink href={entry.corrects.href}>{entry.corrects.label}</TextLink>
                                    </span>
                                )}
                                {entry.correctedBy !== undefined && (
                                    <span>
                                        {t('timeline.corrected_by')}: <TextLink href={entry.correctedBy.href}>{entry.correctedBy.label}</TextLink>
                                    </span>
                                )}
                            </p>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}

export type Part = { name: string; state: StatusState<'part'>; fact: ReactNode };

/** PT-31's derived part strip (D-IA-09): each part's name, its state's shape and label, one derived fact; at most
    eight parts, wrapping rather than scrolling; never a stored stage, progress bar or percentage (SLOP-44). */
export function PartStrip({ parts }: { parts: Part[] }) {
    return (
        <ul role="list" data-slot="part-strip" className="flex min-w-0 flex-wrap gap-x-6 gap-y-2 max-md:hidden">
            {parts.slice(0, 8).map((part) => (
                <li key={part.name} className="flex min-w-0 flex-col gap-0.5">
                    <span className="text-label text-text-tertiary">{part.name}</span>
                    <StatusText family="part" state={part.state} />
                    <span className="text-caption text-text-secondary tabular-nums">{part.fact}</span>
                </li>
            ))}
        </ul>
    );
}

export type Stage = { key: string; label: string; state: 'complete' | 'current' | 'upcoming' | 'attention' };

/** The stage track (§10): read-only derived steps on 30 px discs, the current one ringed in olive with
    `aria-current="step"` and "(langkah n dari N)"; applicable stages only, no percentage (PL-1; VP-4). The
    navigator variant makes each step a native button steering its card. */
export function StageTrack({ stages, label, onSelect }: { stages: Stage[]; label: string; onSelect?: (key: string) => void }) {
    const { t } = useTranslation();

    return (
        <ol aria-label={label} data-slot="stage-track" className="flex min-w-0 flex-wrap items-start gap-x-2 gap-y-3">
            {stages.map((stage, index) => {
                const current = stage.state === 'current';
                const body = (
                    <>
                        <span
                            aria-hidden
                            className={cn(
                                'inline-flex size-stepper shrink-0 items-center justify-center rounded-full border text-caption font-semibold tabular-nums',
                                stage.state === 'complete' && 'border-transparent bg-step-done-dot text-text-on-primary',
                                current && 'border-accent-olive bg-surface text-text-primary ring-2 ring-accent-olive ring-offset-2 ring-offset-surface',
                                stage.state === 'upcoming' && 'border-border bg-surface text-text-tertiary',
                                stage.state === 'attention' && 'border-warning-dot bg-warning-bg text-warning-fg',
                            )}
                        >
                            {stage.state === 'complete' ? <Icon icon={Check} size="xs" /> : stage.state === 'attention' ? <Icon icon={TriangleAlert} size="xs" /> : index + 1}
                        </span>
                        <span className={cn('text-label', current ? 'font-semibold text-text-primary' : 'text-text-secondary')}>
                            {stage.label}
                            {current && <span className="sr-only"> {t('stage.step_of', { n: index + 1, total: stages.length })}</span>}
                        </span>
                    </>
                );

                return (
                    <li key={stage.key} aria-current={current ? 'step' : undefined} className="flex min-w-0 items-center gap-2">
                        {onSelect === undefined ? (
                            <span className="flex items-center gap-2">{body}</span>
                        ) : (
                            <button
                                type="button"
                                className="flex min-h-touch items-center gap-2 rounded-sm px-1 outline-none hover:bg-row-hover focus-visible:outline-2 focus-visible:outline-focus"
                                onClick={() => {
                                    onSelect(stage.key);
                                }}
                            >
                                {body}
                            </button>
                        )}
                        {index < stages.length - 1 && <span aria-hidden className="h-px w-4 bg-border" />}
                    </li>
                );
            })}
        </ol>
    );
}

/** The segmented stage indicator of a list row: the applicable stages as segments, the done ones filled, the
    stage named in words beside it — never a percentage or weighted bar (VP-4; SLOP-44). */
export function SegmentedStage({ stages, current }: { stages: { key: string; done: boolean }[]; current: string }) {
    return (
        <span data-slot="segmented-stage" className="inline-flex min-w-0 items-center gap-2">
            <span aria-hidden className="inline-flex gap-0.5">
                {stages.map((stage) => (
                    <span key={stage.key} className={cn('h-1.5 w-3 rounded-full', stage.done ? 'bg-step-done-dot' : 'bg-border')} />
                ))}
            </span>
            <span className="text-label text-text-secondary">{current}</span>
        </span>
    );
}

/** The now strip: *Sekarang*, *Berikutnya* and *Hambatan* side by side on the desktop, one card on the phone; the
    one primary action with *Berikutnya*, which is doable now (C1A-1); a reason only under *Hambatan*. */
export function NowStrip({ now, next, action, blockers }: { now: ReactNode; next: ReactNode; action?: ReactNode; blockers?: ReactNode }) {
    const { t } = useTranslation();
    const part = (title: string, body: ReactNode, extra?: ReactNode) => (
        <section className="flex min-w-0 flex-1 flex-col gap-1 p-4">
            <h3 className="text-label font-semibold text-text-tertiary">{title}</h3>
            <div className="text-body text-text-primary">{body}</div>
            {extra}
        </section>
    );

    return (
        <Card className="flex min-w-0 flex-col divide-y divide-border-subtle p-0 lg:flex-row lg:divide-x lg:divide-y-0">
            {part(t('journey.now'), now)}
            {part(t('journey.next'), next, action !== undefined && <div className="mt-2 flex">{action}</div>)}
            {part(t('journey.blockers'), blockers ?? <span className="text-text-secondary">{t('journey.no_blockers')}</span>)}
        </Card>
    );
}

/** The disclosure (§10; GR-21): a real button with `aria-expanded`, at least 44 px, the chevron on the right; a
    section holding an invalid field opens and stays open. */
export function Disclosure({ title, children, defaultOpen = false, invalid = false }: { title: ReactNode; children: ReactNode; defaultOpen?: boolean; invalid?: boolean }) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <Collapsible open={open || invalid} onOpenChange={setOpen}>
            <CollapsibleTrigger className="group/disclosure flex min-h-touch w-full items-center justify-between gap-3 rounded-sm text-left text-body font-medium text-text-primary outline-none hover:bg-row-hover focus-visible:outline-2 focus-visible:outline-focus">
                {title}
                <Icon icon={ChevronDown} size="sm" className="text-text-tertiary transition-transform duration-(--mc-dur-fast) group-data-[panel-open]/disclosure:rotate-180" />
            </CollapsibleTrigger>
            <CollapsiblePanel>{children}</CollapsiblePanel>
        </Collapsible>
    );
}

/** Long text: one line with an ellipsis and its full text on hover, focus or press — dismissible, hoverable and
    persistent (WCAG 1.4.13). Record numbers never go through it (§6). */
export function LongText({ text, className }: { text: string; className?: string }) {
    return (
        <Popover>
            <PopoverTrigger openOnHover delay={300} className={cn('block max-w-full min-w-0 cursor-default truncate rounded-xs text-left outline-none focus-visible:outline-2 focus-visible:outline-focus', className)}>
                {text}
            </PopoverTrigger>
            <PopoverContent className="max-w-sheet-sm text-body [overflow-wrap:anywhere]">{text}</PopoverContent>
        </Popover>
    );
}

/** The link: ink with an underline on hover and focus, the olive focus outline (§10). */
export function TextLink({ className, ...props }: ComponentProps<typeof Link>) {
    return (
        <Link
            className={cn('rounded-xs font-medium text-text-primary underline decoration-border-strong underline-offset-2 outline-none hover:decoration-text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus', className)}
            {...props}
        />
    );
}
