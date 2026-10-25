import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// The reference's card (DESIGN_SYSTEM §10): white, the subtle border, radius 10, the barely visible resting
// shadow, 20 px of padding (18 on phones); a text-first header with one action slot. A card only for a
// distinct object, a group scanned as one or a region with its own state — never a card in a card
// (SLOP-01, SLOP-02); compact and content-driven (D-DS-15).
export function Card({ className, tone = 'surface', ...props }: ComponentProps<'section'> & { tone?: 'surface' | 'warm' }) {
    return (
        <section
            data-slot="card"
            className={cn(
                'relative min-w-0 rounded-lg border border-border-subtle p-m-card shadow-card md:p-card',
                tone === 'warm' ? 'bg-surface-warm' : 'bg-surface',
                className,
            )}
            {...props}
        />
    );
}

export function CardHeader({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="card-header" className={cn('flex min-w-0 items-start gap-3', className)} {...props} />;
}

const titleVariants = cva('min-w-0 font-semibold text-text-primary', {
    variants: {
        size: {
            sm: 'text-heading-sm',
            md: 'text-heading-md',
            lg: 'text-heading-lg',
            default: 'text-heading',
        },
        bold: { true: 'font-bold' },
    },
    defaultVariants: { size: 'default' },
});

export function CardTitle({ className, size, bold, level = 2, ...props }: ComponentProps<'h2'> & VariantProps<typeof titleVariants> & { level?: 2 | 3 | 4 }) {
    const Heading = `h${String(level)}` as 'h2';

    return <Heading data-slot="card-title" className={cn(titleVariants({ size, bold }), className)} {...props} />;
}

export function CardDescription({ className, ...props }: ComponentProps<'p'>) {
    return <p data-slot="card-description" className={cn('text-body text-text-secondary', className)} {...props} />;
}

export function CardAction({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="card-action" className={cn('ml-auto flex shrink-0 items-center gap-2', className)} {...props} />;
}

export function CardContent({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="card-content" className={cn('mt-4 min-w-0', className)} {...props} />;
}

export function CardFooter({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="card-footer" className={cn('mt-4 flex items-center gap-2', className)} {...props} />;
}
