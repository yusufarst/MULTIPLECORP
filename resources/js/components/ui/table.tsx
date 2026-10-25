import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// PT-05's table (DESIGN_SYSTEM §9.2, §10): the reference's frame scrolling only inside itself (WCAG 1.4.10's
// exception), a sticky header row in the label style, 36 px rows on hairline rules, no zebra stripes; numbers
// right-aligned in tabular figures; the hovered and the focused row tinted, the focused row outlined.
export function Table({ className, frameClassName, ...props }: ComponentProps<'table'> & { frameClassName?: string }) {
    return (
        <div data-slot="table-frame" className={cn('relative w-full min-w-0 overflow-x-auto', frameClassName)}>
            <table data-slot="table" className={cn('w-full caption-bottom border-collapse text-body', className)} {...props} />
        </div>
    );
}

export function TableHeader({ className, ...props }: ComponentProps<'thead'>) {
    return <thead data-slot="table-header" className={cn('sticky top-0 z-(--z-raised) bg-surface-sunken', className)} {...props} />;
}

export function TableBody({ className, ...props }: ComponentProps<'tbody'>) {
    return <tbody data-slot="table-body" className={className} {...props} />;
}

export function TableRow({ className, ...props }: ComponentProps<'tr'>) {
    return (
        <tr
            data-slot="table-row"
            className={cn(
                'border-b border-border-subtle transition-colors duration-(--mc-dur-hover) hover:bg-row-hover focus-visible:bg-row-hover focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-focus',
                className,
            )}
            {...props}
        />
    );
}

export function TableHead({ className, numeric = false, ...props }: ComponentProps<'th'> & { numeric?: boolean }) {
    return (
        <th
            data-slot="table-head"
            className={cn('h-control-md px-3 text-left align-middle text-label font-medium whitespace-nowrap text-text-tertiary', numeric && 'text-right', className)}
            {...props}
        />
    );
}

export function TableCell({ className, numeric = false, ...props }: ComponentProps<'td'> & { numeric?: boolean }) {
    return <td data-slot="table-cell" className={cn('h-control-md px-3 py-2 align-middle text-text-primary', numeric && 'text-right tabular-nums', className)} {...props} />;
}

export function TableCaption({ className, ...props }: ComponentProps<'caption'>) {
    return <caption data-slot="table-caption" className={cn('mt-3 text-caption text-text-tertiary', className)} {...props} />;
}
