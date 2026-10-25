import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// The Empty composition (DESIGN_SYSTEM PT-19, IP-21): one sentence and at most one action; no illustration,
// mascot or decorative empty state (§18).
export function Empty({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="empty" className={cn('flex min-w-0 flex-col items-start gap-3 py-6', className)} {...props} />;
}

export function EmptyTitle({ className, ...props }: ComponentProps<'p'>) {
    return <p data-slot="empty-title" className={cn('text-body-lg font-semibold text-text-primary', className)} {...props} />;
}

export function EmptyDescription({ className, ...props }: ComponentProps<'p'>) {
    return <p data-slot="empty-description" className={cn('text-body text-text-secondary', className)} {...props} />;
}
