import { Input as InputPrimitive } from '@base-ui/react/input';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// The reference's field box (DESIGN_SYSTEM §10 "Field and number entry"): 36 px with a fine pointer, 44 px with
// a coarse one, 16 px text on touch (D-DS-02, D-DS-19); the control edge `--color-border-input`, 3:1 on its
// surface (GR-01); focus turns the edge olive under its 3 px halo; invalid in the danger tone with
// `aria-invalid` (IP-14); disabled sunken. Numbers right-aligned in tabular figures (OC-09).
export const fieldBoxClass =
    'h-control-md w-full min-w-0 rounded-sm border border-border-input bg-surface px-3 text-(length:--text-input-size) text-text-primary shadow-xs transition-[border-color,box-shadow,background-color] duration-(--mc-dur-fast) ease-exit outline-none placeholder:text-text-placeholder focus-visible:border-accent-olive focus-visible:shadow-(--focus-ring) focus-visible:duration-0 aria-invalid:border-danger-fg aria-invalid:focus-visible:shadow-(--focus-ring-danger) disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-text-disabled disabled:shadow-none read-only:bg-surface-sunken read-only:shadow-none';

export function Input({ className, numeric = false, ...props }: ComponentProps<'input'> & { numeric?: boolean }) {
    return (
        <InputPrimitive
            data-slot="input"
            className={cn(fieldBoxClass, numeric && 'text-right tabular-nums', className)}
            {...props}
        />
    );
}
