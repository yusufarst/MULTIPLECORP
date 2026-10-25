import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// The reference's pill (DESIGN_SYSTEM §12.1): full radius, a tone's tint with its same-hue ink, 24 px with
// 12 px at 500, 28 px with 13 px at 600 for the record in view, 22 px with 11 px in compact lists; the
// outline form for a company code or a count (§10). A state is never colour alone: the status badge puts
// its shape and its word inside (StatusBadge).
const badgeVariants = cva('inline-flex w-fit max-w-full shrink-0 items-center gap-1 rounded-full border px-2 font-medium whitespace-nowrap', {
    variants: {
        tone: {
            neutral: 'border-transparent bg-neutral-bg text-neutral-fg',
            info: 'border-transparent bg-info-bg text-info-fg',
            success: 'border-transparent bg-success-bg text-success-fg',
            warning: 'border-transparent bg-warning-bg text-warning-fg',
            danger: 'border-transparent bg-danger-bg text-danger-fg',
            pending: 'border-transparent bg-pending-bg text-pending-fg',
            outline: 'border-border-strong bg-surface text-text-strong',
        },
        size: {
            sm: 'h-badge-sm text-pill-sm',
            md: 'h-badge text-pill',
            lg: 'h-badge-lg px-3 text-(length:--text-pill-lg-size) font-semibold',
        },
    },
    defaultVariants: { tone: 'neutral', size: 'md' },
});

export type BadgeTone = NonNullable<VariantProps<typeof badgeVariants>['tone']>;

export function Badge({ className, tone, size, ...props }: ComponentProps<'span'> & VariantProps<typeof badgeVariants>) {
    return <span data-slot="badge" className={cn(badgeVariants({ tone, size }), className)} {...props} />;
}

export { badgeVariants };
