import type { ComponentProps } from 'react';

import * as format from '@/lib/format';
import { cn } from '@/lib/utils';

// PT-23's money, quantity and date presentation (DESIGN_SYSTEM §16; LOCALIZATION §3.3): one way in both
// languages, the server's exact decimal strings never rounded, tabular figures; a figure never breaks inside
// itself (GF-20); a business number exactly as issued with a slashed zero, never truncated (§6).
export function Money({ value, withSen, className, ...props }: Omit<ComponentProps<'data'>, 'value'> & { value: string; withSen?: boolean }) {
    return (
        <data value={value} data-figure className={cn('whitespace-nowrap tabular-nums', className)} {...props}>
            {format.money(value, withSen)}
        </data>
    );
}

export function Quantity({ value, unit, alternate, className }: { value: string; unit: string; alternate?: { value: string; unit: string }; className?: string }) {
    return (
        <data value={value} data-figure className={cn('whitespace-nowrap tabular-nums', className)}>
            {format.quantity(value, unit, alternate?.value, alternate?.unit)}
        </data>
    );
}

export function Percent({ value, className }: { value: string; className?: string }) {
    return (
        <data value={value} data-figure className={cn('whitespace-nowrap tabular-nums', className)}>
            {format.percent(value)}
        </data>
    );
}

export function DateText({ value, long = false, className }: { value: string; long?: boolean; className?: string }) {
    return (
        <time dateTime={value} className={cn('whitespace-nowrap tabular-nums', className)}>
            {long ? format.dateLong(value) : format.date(value)}
        </time>
    );
}

export function DateTimeText({ value, className }: { value: string; className?: string }) {
    return (
        <time dateTime={value} className={cn('whitespace-nowrap tabular-nums', className)}>
            {format.dateTime(value)}
        </time>
    );
}

/** A business number or code exactly as issued (§16): tabular figures, a slashed zero, never broken or cut. */
export function Code({ children, className, ...props }: ComponentProps<'span'>) {
    return (
        <span data-code className={cn('whitespace-nowrap', className)} {...props}>
            {children}
        </span>
    );
}
