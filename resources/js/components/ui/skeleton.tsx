import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// APPR-042's pilot (CR-002 K-2): a placeholder in the shape of the content it stands for, pulsing every 1.4 s and still
// under reduced motion. Written here by hand, not by the shadcn/ui command, so no customised primitive is touched. It is
// decoration only: the region holding it announces the loading in words.
export function Skeleton({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="skeleton" aria-hidden="true" className={cn('animate-pulse rounded-md bg-border motion-reduce:animate-none', className)} {...props} />;
}
