import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// The Item composition (DESIGN_SYSTEM §10): a row of a list — a signal's queue row, a readiness item, a phone
// row of at least 60 px whose whole surface is the target (PT-06) — on hairline separators, not a card each.
export function ItemGroup({ className, ...props }: ComponentProps<'ul'>) {
    return <ul data-slot="item-group" role="list" className={cn('flex min-w-0 flex-col divide-y divide-border-subtle', className)} {...props} />;
}

export function Item({ className, ...props }: ComponentProps<'li'>) {
    return <li data-slot="item" className={cn('flex min-h-row min-w-0 items-center gap-3 py-2 md:min-h-control-lg', className)} {...props} />;
}

export function ItemContent({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="item-content" className={cn('flex min-w-0 flex-1 flex-col gap-0.5', className)} {...props} />;
}

export function ItemTitle({ className, ...props }: ComponentProps<'p'>) {
    return <p data-slot="item-title" className={cn('text-m-row font-medium text-text-primary md:text-body', className)} {...props} />;
}

export function ItemDescription({ className, ...props }: ComponentProps<'p'>) {
    return <p data-slot="item-description" className={cn('text-m-meta text-text-tertiary md:text-caption', className)} {...props} />;
}

export function ItemActions({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="item-actions" className={cn('ml-auto flex shrink-0 items-center gap-2', className)} {...props} />;
}
