import { Lock, type LucideIcon, SearchX, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';

import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription, EmptyTitle } from '@/components/ui/empty';
import { Icon } from '@/components/ui/icon';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';

// PT-19's states of a region (DESIGN_SYSTEM §9.4, §10 "States of a region", §12.3): loading with a spinner after
// 300 ms, never a skeleton; empty — one sentence and at most one action, a search's naming its term; a part's
// error — "Bagian Ini Tidak Dapat Dimuat", the reference code and "Muat Ulang Bagian Ini", the rest of the page
// working —; one region never blanks the page. Each change is announced (WCAG 4.1.3).
export function RegionLoading() {
    const { t } = useTranslation();

    return (
        <div data-region-state="loading" className="flex min-h-control-xl items-center justify-center py-6">
            <Spinner label={t('states.loading')} size="md" />
        </div>
    );
}

export function RegionEmpty({ text, action }: { text: ReactNode; action?: ReactNode }) {
    return (
        <Empty data-region-state="empty" role="status">
            <EmptyDescription>{text}</EmptyDescription>
            {action}
        </Empty>
    );
}

/** A search or filter that matches nothing says so, names the term and offers clearing it (FA-22; PT-19). */
export function RegionNoMatch({ term, onClear, clearLabel }: { term: string; onClear: () => void; clearLabel: string }) {
    const { t } = useTranslation();

    return (
        <Empty data-region-state="no-match" role="status">
            <EmptyDescription>{t('states.no_match', { q: term })}</EmptyDescription>
            <Button variant="outline" size="sm" onClick={onClear}>
                {clearLabel}
            </Button>
        </Empty>
    );
}

export function RegionError({ reference, onReload }: { reference: string; onReload: () => void }) {
    const { t } = useTranslation();

    return (
        <div data-region-state="error" role="alert">
            <Alert
                tone="warning"
                icon={TriangleAlert}
                title={t('states.group_error')}
                action={
                    <Button variant="outline" size="sm" onClick={onReload}>
                        {t('states.group_reload')}
                    </Button>
                }
            >
                <span data-code className="text-caption text-text-tertiary">
                    {t('outcome.reference', { kode: reference })}
                </span>
            </Alert>
        </div>
    );
}

// IP-21's system page (DESIGN_SYSTEM §10 "System page"; PG-005's content, TASK-016's route): not found, forbidden
// or error — nothing of the record rendered, the same answer for an unknown, an out-of-scope and an Owner-only
// address (AZ-11), the reference code and the way back.
const SYSTEM: Record<'not_found' | 'forbidden' | 'error', { title: 'outcome.not_found' | 'outcome.forbidden' | 'outcome.error'; icon: LucideIcon }> = {
    not_found: { title: 'outcome.not_found', icon: SearchX },
    forbidden: { title: 'outcome.forbidden', icon: Lock },
    error: { title: 'outcome.error', icon: TriangleAlert },
};

export function SystemPage({ kind, message, reference, back }: { kind: keyof typeof SYSTEM; message: ReactNode; reference: string; back: ReactNode }) {
    const { t } = useTranslation();
    const face = SYSTEM[kind];

    return (
        <Empty data-system-page={kind} className="mx-auto max-w-page-focused py-16">
            <Icon icon={face.icon} size="card" className="text-text-tertiary" />
            <EmptyTitle className="text-title font-semibold">{t(face.title)}</EmptyTitle>
            <EmptyDescription>{message}</EmptyDescription>
            <span data-code className="text-caption text-text-tertiary">
                {t('outcome.reference', { kode: reference })}
            </span>
            {back}
        </Empty>
    );
}
