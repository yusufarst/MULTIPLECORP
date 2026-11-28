import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// K-5 (DESIGN_SYSTEM §8, §9.4, §10 "States of a region"): a placeholder in the proportions of the content it stands for, pulsing
// once every 1.4 s and still under reduced motion. Written by hand, not by the shadcn/ui command, so no customised primitive is
// touched. Decoration only, hidden from assistive technology: the region holding it announces the loading once, in words. A
// block-level span, valid inside a table cell or a line of text as well as in a block.
export function Skeleton({ className, ...props }: ComponentProps<'span'>) {
    return <span data-slot="skeleton" aria-hidden="true" className={cn('block animate-pulse rounded-md bg-border motion-reduce:animate-none', className)} {...props} />;
}
