// The project journey's shared shapes and wording (the P7 gate record's journey model PL-1–PL-3; TASK-032): each item
// — a step, a blocker, an unmet condition — is a catalogue key with its parameters, derived by the server each time and
// never stored (L-01); a link only where its page exists and the account holds its capability, the server deciding.
import { TriangleAlert } from 'lucide-react';

import { Icon } from '@/components/ui/icon';
import { type TranslationKey, useTranslation } from '@/lib/i18n';

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

/** A list row's journey: its stage with its place among the applicable stages, and the blocker marker beside it. */
export function StageSummary({ summary }: { summary: JourneySummary }) {
    const { t } = useTranslation();

    if (summary.stage === null) {
        return <span className="text-text-secondary">—</span>;
    }

    return (
        <span className="inline-flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
            <span className="font-medium text-text-primary">{t(stageName(summary.stage))}</span>
            {summary.position !== null && <span className="text-caption text-text-tertiary">{t('stage.step_of', { n: summary.position, total: summary.count })}</span>}
            {summary.blocked && (
                <span className="inline-flex items-center gap-1 text-caption font-medium text-warning-fg">
                    <Icon icon={TriangleAlert} size="xs" />
                    {t('journey.blocked')}
                </span>
            )}
        </span>
    );
}
