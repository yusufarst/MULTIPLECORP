import type { ComponentProps } from 'react';

import { useDelayed } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

// K-5 (DESIGN_SYSTEM §8, §9.4, §10 "States of a region"): a placeholder in the proportions of the content it stands for, pulsing
// once every 1.4 s and still under reduced motion. Written by hand, not by the shadcn/ui command, so no customised primitive is
// touched. Decoration only, hidden from assistive technology: the region holding it announces the loading once, in words. A
// block-level span, valid inside a table cell or a line of text as well as in a block.
export function Skeleton({ className, ...props }: ComponentProps<'span'>) {
    return <span data-slot="skeleton" aria-hidden="true" className={cn('block animate-pulse rounded-md bg-border motion-reduce:animate-none', className)} {...props} />;
}

/**
 * A skeleton for a cell that is not in a region of its own — the stage of a project row —: its place kept from the first
 * render, its shape drawn only after the same 300 ms as every skeleton (K-5, for all), so a fast answer never flashes one.
 * The words ("Memuat tahap…") are said once for all such cells by the status line of their list.
 */
export function DelayedSkeleton({ className, ...props }: ComponentProps<'span'>) {
    const shown = useDelayed(true);

    return shown ? <Skeleton className={className} {...props} /> : <span aria-hidden="true" className={cn('invisible block', className)} />;
}
