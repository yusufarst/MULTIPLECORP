import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// The keycap (DESIGN_SYSTEM §5.3; §13): 22 px high, at least 23 px wide, the faint keycap edge, the mono face
// at 12.5 px; a shortcut hint after a label on the desktop only (PT-11).
export function Kbd({ className, ...props }: ComponentProps<'kbd'>) {
    return (
        <kbd
            data-slot="kbd"
            className={cn(
                'inline-flex h-keycap min-w-keycap-min items-center justify-center rounded-xs border border-border-kbd bg-surface px-1 font-mono text-(length:--text-kbd-size) font-medium text-text-secondary',
                className,
            )}
            {...props}
        />
    );
}

export function KbdGroup({ className, ...props }: ComponentProps<'span'>) {
    return <span data-slot="kbd-group" className={cn('inline-flex items-center gap-1', className)} {...props} />;
}
