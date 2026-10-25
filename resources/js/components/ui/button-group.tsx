import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// The Button Group of the split action (DESIGN_SYSTEM §10 "Split action"): segments joined, their inner radii
// squared, one shared edge.
export function ButtonGroup({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="button-group"
            role="group"
            className={cn('inline-flex min-w-0 items-stretch *:not-first:-ml-px *:not-first:rounded-l-none *:not-last:rounded-r-none *:focus-visible:z-(--z-raised)', className)}
            {...props}
        />
    );
}
