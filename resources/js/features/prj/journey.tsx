// The project journey's shared shapes and wording (the P7 gate record's journey model PL-1–PL-3; TASK-032): each item
// — a step, a blocker, an unmet condition — is a catalogue key with its parameters, derived by the server each time and
// never stored (L-01); a link only where its page exists and the account holds its capability, the server deciding.
import { TriangleAlert } from 'lucide-react';

import { Icon } from '@/components/ui/icon';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type JourneyItem = { key: string; params: Record<string, string>; stage: string | null; blocker: boolean; href: string | null };
export type JourneyStage = { key: string; state: 'complete' | 'current' | 'upcoming' | 'attention' };
export type Journey = {
    outcome: string | null;
    stages: JourneyStage[];
    not_needed: string[];
    current: string | null;
    position: number | null;
    blocked: boolean;
    blockers: JourneyItem[];
    next: JourneyItem | null;
    remaining: { predicate: string; items: JourneyItem[] }[];
};
export type JourneySummary = { outcome: string | null; stage: string | null; position: number | null; count: number; blocked: boolean; next: JourneyItem | null };
export type Failed = { failed: true };

export const isFailed = (value: unknown): value is Failed => typeof value === 'object' && value !== null && (value as { failed?: unknown }).failed === true;

export const stageName = (key: string): TranslationKey => `journey.stage.${key}` as TranslationKey;
export const predicateName = (key: string): TranslationKey => `journey.pred.${key}` as TranslationKey;

/** An item in words, its link where the server gave one. */
export function ItemText({ item }: { item: JourneyItem }) {
    const { t } = useTranslation();
    const text = t(item.key as TranslationKey, item.params);

    return item.href === null ? <>{text}</> : <a href={item.href} className="text-accent-link underline underline-offset-2">{text}</a>;
}

type Segment = 'complete' | 'current' | 'upcoming' | 'attention';

const SEGMENT: Record<Segment, string> = {
    complete: 'bg-text-secondary',
    current: 'bg-accent-olive',
    attention: 'bg-warning-dot',
    upcoming: 'bg-border',
};

/** The segments of a stage bar, drawn only: the words beside them say the stage and its number (CR-002 K-6). */
function Segments({ states, size }: { states: Segment[]; size: 'sm' | 'md' }) {
    return (
        <span aria-hidden="true" data-slot="stage-segments" className={cn('inline-flex min-w-0', size === 'md' ? 'w-full gap-1' : 'gap-0.5')}>
            {states.map((state, index) => (
                <span key={index} className={cn('rounded-full', size === 'md' ? 'h-2 min-w-0 flex-1' : 'h-1.5 w-2.5', SEGMENT[state])} />
            ))}
        </span>
    );
}

/**
 * APPR-042's pilot (CR-002 K-6; DIR-134 E4 — the pilot only, no normative behaviour changed before its approval): the
 * project's place in its journey as "Tahap n dari N · stage", the number always written, over one segment per
 * applicable stage. The server derives the stages and the position; nothing here computes a stage or a percentage.
 */
export function StageBar({ stages, position, current, blocked }: { stages: JourneyStage[]; position: number | null; current: string | null; blocked: boolean }) {
    const { t } = useTranslation();
    const index = stages.findIndex((stage) => stage.state === 'current' || stage.state === 'attention');
    const place = position ?? (index >= 0 ? index + 1 : null);

    if (place === null || current === null || stages.length === 0) {
        return null;
    }

    return (
        <div data-slot="stage-bar" className="flex min-w-0 flex-col gap-2">
            <p className="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1">
                <span className="text-label font-medium text-text-secondary tabular-nums">{t('journey.stage_of', { n: place, total: stages.length })}</span>
                <span className="text-heading-sm font-semibold text-text-primary">{t(stageName(current))}</span>
                {blocked && (
                    <span className="inline-flex items-center gap-1 text-label font-medium text-warning-fg">
                        <Icon icon={TriangleAlert} size="xs" />
                        {t('journey.blocked')}
                    </span>
                )}
            </p>
            <Segments states={stages.map((stage) => stage.state)} size="md" />
        </div>
    );
}

/** A list row's journey: its stage with its place among the applicable stages, and the blocker marker beside it. */
export function StageSummary({ summary }: { summary: JourneySummary }) {
    const { t } = useTranslation();

    if (summary.stage === null) {
        return <span className="text-text-secondary">—</span>;
    }

    const place = summary.position;
    // The pilot's row bar (K-6): the stages before the current one done, the current one marked, the rest to come.
    const states: Segment[] =
        place === null ? [] : Array.from({ length: summary.count }, (_, index) => (index < place - 1 ? 'complete' : index === place - 1 ? (summary.blocked ? 'attention' : 'current') : 'upcoming'));

    return (
        <span className="inline-flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
            {states.length > 0 && <Segments states={states} size="sm" />}
            <span className="font-medium text-text-primary">{t(stageName(summary.stage))}</span>
            {place !== null && <span className="text-caption text-text-tertiary tabular-nums">{t('journey.stage_of', { n: place, total: summary.count })}</span>}
            {summary.blocked && (
                <span className="inline-flex items-center gap-1 text-caption font-medium text-warning-fg">
                    <Icon icon={TriangleAlert} size="xs" />
                    {t('journey.blocked')}
                </span>
            )}
        </span>
    );
}
