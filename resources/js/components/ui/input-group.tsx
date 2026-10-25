import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// The reference's input shell with an addon (DESIGN_SYSTEM §10 "Field and number entry"; PT-20): one bordered
// row holding the control and its addons — an icon cell behind its divider, a unit, a button — so the focus,
// invalid and disabled states belong to the whole box.
export function InputGroup({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="input-group"
            className={cn(
                'flex h-control-md w-full min-w-0 items-center overflow-hidden rounded-sm border border-border-input bg-surface text-(length:--text-input-size) text-text-primary shadow-xs transition-[border-color,box-shadow] duration-(--mc-dur-fast) has-[[data-slot=input-group-control]:focus-visible]:border-accent-olive has-[[data-slot=input-group-control]:focus-visible]:shadow-(--focus-ring) has-[[aria-invalid=true]]:border-danger-fg has-[:disabled]:bg-surface-sunken',
                className,
            )}
            {...props}
        />
    );
}

export function InputGroupInput({ className, ...props }: ComponentProps<'input'>) {
    return (
        <input
            data-slot="input-group-control"
            className={cn('h-full min-w-0 flex-1 bg-transparent px-3 outline-none placeholder:text-text-placeholder disabled:cursor-not-allowed disabled:text-text-disabled', className)}
            {...props}
        />
    );
}

export function InputGroupAddon({ className, divider = false, ...props }: ComponentProps<'span'> & { divider?: boolean }) {
    return (
        <span
            data-slot="input-group-addon"
            className={cn('inline-flex h-full shrink-0 items-center gap-2 px-3 text-icon-field', divider && 'border-r border-addon-divider', className)}
            {...props}
        />
    );
}

export function InputGroupText({ className, ...props }: ComponentProps<'span'>) {
    return <span data-slot="input-group-text" className={cn('inline-flex shrink-0 items-center px-3 text-text-tertiary', className)} {...props} />;
}
