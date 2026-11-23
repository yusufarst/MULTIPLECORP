import { Lock, type LucideIcon, SearchX, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';

import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription } from '@/components/ui/empty';
import { Icon } from '@/components/ui/icon';
import { Skeleton } from '@/components/ui/skeleton';
import { useDelayed } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

// PT-19's states of a region (DESIGN_SYSTEM §9.4, §10 "States of a region", §12.3): loading with a spinner after
// 300 ms, never a skeleton; empty — one sentence and at most one action, a search's naming its term; a part's
// error — "Bagian Ini Tidak Dapat Dimuat", the reference code and "Muat Ulang Bagian Ini", the rest of the page
// working —; one region never blanks the page. Each change is announced (WCAG 4.1.3).
// APPR-042's pilot (CR-002 K-2): a region loads behind lines in the shape of its content, shown after the same 300 ms,
// and says "Memuat" in a live region that exists before the words arrive, so the change is heard.
export function RegionLoading({ lines = 3 }: { lines?: number }) {
    const { t } = useTranslation();
    const shown = useDelayed(true);

    return (
        <div data-region-state="loading" role="status" className="flex min-h-control-xl flex-col justify-center gap-3 py-6">
            {shown && (
                <>
                    <span className="sr-only">{t('states.loading')}</span>
                    {Array.from({ length: lines }, (_, line) => (
                        <Skeleton key={line} className={cn('h-4', line === lines - 1 ? 'w-3/5' : 'w-full')} />
                    ))}
                </>
            )}
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

/**
 * A search or filter that matches nothing says so, names the term and offers clearing it (FA-22; PT-19); a lookup's own message,
 * MSG-25's (IP-17), in `text`; `announce` false where the page holds its own live region from its load, so it is said once.
 */
export function RegionNoMatch({ term, text, announce = true, onClear, clearLabel }: { term: string; text?: ReactNode; announce?: boolean; onClear: () => void; clearLabel: string }) {
    const { t } = useTranslation();

    return (
        <Empty data-region-state="no-match" role={announce ? 'status' : undefined}>
            <EmptyDescription>{text ?? t('states.no_match', { q: term })}</EmptyDescription>
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
                {/* "Kode referensi" only with a code the server gave (TASK-097's defect). */}
                {reference.trim() !== '' && (
                    <span data-code className="text-caption text-text-tertiary">
                        {t('outcome.reference', { kode: reference })}
                    </span>
                )}
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

export function SystemPage({ kind, message, reference, back, page = false }: { kind: keyof typeof SYSTEM; message: ReactNode; reference: string; back: ReactNode; page?: boolean }) {
    const { t } = useTranslation();
    const face = SYSTEM[kind];
    // As the page itself — PG-005, which has no page header of its own — the title is its heading (§15; WCAG 1.3.1); as a
    // specimen inside another page it stays a title of that page's content.
    const Title = page ? 'h1' : 'p';

    return (
        <Empty data-system-page={kind} className="mx-auto max-w-page-focused py-16">
            <Icon icon={face.icon} size="card" className="text-text-tertiary" />
            <Title data-slot="empty-title" className="text-title font-semibold text-text-primary">
                {t(face.title)}
            </Title>
            <EmptyDescription>{message}</EmptyDescription>
            {reference.trim() !== '' && (
                <span data-code className="text-caption text-text-tertiary">
                    {t('outcome.reference', { kode: reference })}
                </span>
            )}
            {back}
        </Empty>
    );
}
