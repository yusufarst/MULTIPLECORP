import { ChevronDown, ImageOff } from 'lucide-react';
import { type ReactNode, useState } from 'react';

import { CompanyTag } from '@/components/company';
import { TextLink } from '@/components/record-blocks';
import { RegionEmpty, RegionError, RegionLoading } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Collapsible, CollapsiblePanel, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Icon } from '@/components/ui/icon';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type SignalRow = { id: string; condition: ReactNode; company: string; subject: ReactNode; age: ReactNode; href: string };
export type SignalState = { phase: 'loading' } | { phase: 'error'; reference: string } | { phase: 'ready'; rows: SignalRow[] };

// PT-21's signal group and queue row (DESIGN_SYSTEM §9.5): the heading with its bounded per-company counts ("20+")
// as inline text and "Lihat Semua"; at most five rows in the statement's order — condition · company · subject ·
// age · link —; the group's own spinner, error and empty sentence. On the phone the group collapses with its
// counts in the heading. No KPI tile, chart, trend arrow, sparkline or ring (SLOP-18, SLOP-34).
export function SignalGroup({
    title,
    counts,
    state,
    empty,
    seeAll,
    onReload,
}: {
    title: string;
    /** The server's bounded counts per company, e.g. [{ company: 'ARJ', count: '20+' }]. */
    counts: { company: string; count: string }[];
    state: SignalState;
    empty: string;
    seeAll: string;
    onReload: () => void;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(true);
    const countText = counts.map((count) => `${count.company} ${count.count}`).join(' · ');

    return (
        <section aria-label={title} data-slot="signal-group" className="flex min-w-0 flex-col gap-2">
            <Collapsible open={open} onOpenChange={setOpen}>
                <div className="flex min-w-0 items-center justify-between gap-3">
                    <h3 className="min-w-0 text-body font-semibold text-text-primary">
                        <CollapsibleTrigger className="group/signal flex min-h-touch min-w-0 items-center gap-2 rounded-sm text-left outline-none focus-visible:outline-2 focus-visible:outline-focus md:pointer-events-none">
                            {title}
                            {countText !== '' && <span className="text-label font-normal text-text-tertiary tabular-nums">{countText}</span>}
                            <Icon icon={ChevronDown} size="sm" className="text-text-tertiary group-data-[panel-open]/signal:rotate-180 md:hidden" />
                        </CollapsibleTrigger>
                    </h3>
                    <TextLink href={seeAll} className="shrink-0 text-label">
                        {t('actions.see_all')}
                    </TextLink>
                </div>
                <CollapsiblePanel keepMounted className="max-md:data-[closed]:hidden md:block!">
                    {state.phase === 'loading' && <RegionLoading />}
                    {state.phase === 'error' && <RegionError reference={state.reference} onReload={onReload} />}
                    {state.phase === 'ready' && state.rows.length === 0 && <RegionEmpty text={empty} />}
                    {state.phase === 'ready' && state.rows.length > 0 && (
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {state.rows.slice(0, 5).map((row) => (
                                <li key={row.id} className="flex min-h-row min-w-0 flex-wrap items-center gap-x-3 gap-y-1 py-2 md:min-h-control-lg">
                                    <span className="text-body text-text-primary">{row.condition}</span>
                                    <CompanyTag code={row.company} />
                                    <TextLink href={row.href} className="min-w-0 truncate">
                                        {row.subject}
                                    </TextLink>
                                    <span className="ml-auto text-caption whitespace-nowrap text-text-tertiary">{row.age}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </CollapsiblePanel>
            </Collapsible>
        </section>
    );
}

export type ReadinessItem = { key: string; label: ReactNode; complete: boolean; missing?: ReactNode; href?: string; linkLabel?: string };

/** PT-29's derived readiness list: missing items first, each *Lengkap* or *Belum*, what is missing and its link —
    derived each time, no fraction, progress bar, percentage or checkmark animation (SLOP-44). */
export function ReadinessList({ label, items }: { label: string; items: ReadinessItem[] }) {
    const { t } = useTranslation();
    const ordered = [...items.filter((item) => !item.complete), ...items.filter((item) => item.complete)];

    return (
        <ul role="list" aria-label={label} data-slot="readiness" className="flex min-w-0 flex-col divide-y divide-border-subtle">
            {ordered.map((item) => (
                <li key={item.key} className="flex min-h-row min-w-0 flex-wrap items-center gap-x-3 gap-y-1 py-2 md:min-h-control-lg">
                    <span className="min-w-0 flex-1 text-body text-text-primary">{item.label}</span>
                    <StatusBadge family="readiness_item" state={item.complete ? 'COMPLETE' : 'NOT_COMPLETE'} size="sm" />
                    {item.missing !== undefined && !item.complete && (
                        <span className="basis-full text-caption text-text-secondary md:basis-auto">
                            {t('readiness.missing')}: {item.missing}
                        </span>
                    )}
                    {item.href !== undefined && item.linkLabel !== undefined && (
                        <TextLink href={item.href} className="text-label">
                            {item.linkLabel}
                        </TextLink>
                    )}
                </li>
            ))}
        </ul>
    );
}

export type CapabilityMark = 'kept' | 'added' | 'removed' | 'inactive' | null;
export type CapabilityRow = { key: string; area: string; label: string; prerequisite?: string; marks: Record<string, CapabilityMark> };

const MARK_CLASS: Record<Exclude<CapabilityMark, null>, string> = {
    kept: 'text-text-primary',
    added: 'font-semibold text-success-fg',
    removed: 'text-danger-fg line-through decoration-1',
    inactive: 'text-text-tertiary',
};

/** PT-30's capability-by-company preview "Pratinjau Akses": capabilities grouped by area as rows, the granted
    companies as columns, each cell *Dapat*, *Baru*, *Dicabut* or empty; a dependant of a removed prerequisite reads
    "Nonaktif — prasyarat dicabut"; a summary per company. The server decides what the grants allow. */
export function CapabilityPreview({ companies, rows, summaries }: { companies: string[]; rows: CapabilityRow[]; summaries: Record<string, ReactNode> }) {
    const { t } = useTranslation();
    const areas = [...new Set(rows.map((row) => row.area))];
    const word = (mark: Exclude<CapabilityMark, null>) => t(mark === 'kept' ? 'capability.kept' : mark === 'added' ? 'capability.added' : mark === 'removed' ? 'capability.removed' : 'capability.inactive');

    return (
        <section aria-label={t('capability.preview')} data-slot="capability-preview" className="flex min-w-0 flex-col gap-4">
            <div className="max-md:hidden">
                <Table aria-label={t('capability.preview')}>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead />
                            {companies.map((company) => (
                                <TableHead key={company}>
                                    <CompanyTag code={company} />
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    {areas.map((area) => (
                        <TableBody key={area}>
                            <TableRow className="hover:bg-transparent">
                                <TableHead scope="rowgroup" colSpan={companies.length + 1} className="bg-surface-sunken text-text-secondary">
                                    {area}
                                </TableHead>
                            </TableRow>
                            {rows
                                .filter((row) => row.area === area)
                                .map((row) => (
                                    <TableRow key={row.key}>
                                        <TableHead scope="row" className="font-normal text-text-primary">
                                            {row.label}
                                            {row.prerequisite !== undefined && <span className="block text-caption text-text-tertiary">{row.prerequisite}</span>}
                                        </TableHead>
                                        {companies.map((company) => {
                                            const mark = row.marks[company] ?? null;

                                            return (
                                                <TableCell key={company} className={cn(mark !== null && MARK_CLASS[mark])}>
                                                    {mark === null ? '' : word(mark)}
                                                </TableCell>
                                            );
                                        })}
                                    </TableRow>
                                ))}
                        </TableBody>
                    ))}
                </Table>
            </div>
            <div className="flex flex-col gap-4 md:hidden">
                {companies.map((company) => (
                    <section key={company} className="flex flex-col gap-2">
                        <h3 className="flex items-center gap-2 text-body font-semibold">
                            <CompanyTag code={company} />
                        </h3>
                        <ul role="list" className="flex flex-col divide-y divide-border-subtle">
                            {rows
                                .filter((row) => (row.marks[company] ?? null) !== null)
                                .map((row) => {
                                    const mark = row.marks[company] as Exclude<CapabilityMark, null>;

                                    return (
                                        <li key={row.key} className="flex min-h-row items-center justify-between gap-3 py-2">
                                            <span className="text-m-row">{row.label}</span>
                                            <span className={cn('text-m-meta', MARK_CLASS[mark])}>{word(mark)}</span>
                                        </li>
                                    );
                                })}
                        </ul>
                    </section>
                ))}
            </div>
            <ul role="list" className="flex flex-col gap-1">
                {companies.map((company) => (
                    <li key={company} className="text-body text-text-secondary">
                        <CompanyTag code={company} className="mr-2" />
                        {summaries[company]}
                    </li>
                ))}
            </ul>
        </section>
    );
}

/** PT-22's thumbnail: 40 px in lists only, loaded lazily, a neutral placeholder while the derivative is not ready;
    served from the application's origin (PF-31, PF-32; WS-08). */
export function Thumbnail({ src, alt, className }: { src: string | null; alt: string; className?: string }) {
    const [failed, setFailed] = useState(false);

    return (
        <span data-slot="thumbnail" className={cn('inline-flex size-control-lg shrink-0 items-center justify-center overflow-hidden rounded-sm border border-border-subtle bg-surface-sunken', className)}>
            {src === null || failed ? (
                <Icon icon={ImageOff} size="sm" className="text-text-tertiary" />
            ) : (
                <img
                    src={src}
                    alt={alt}
                    loading="lazy"
                    decoding="async"
                    width={40}
                    height={40}
                    className="size-full object-cover"
                    onError={() => {
                        setFailed(true);
                    }}
                />
            )}
        </span>
    );
}
