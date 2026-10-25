import { Separator as SeparatorPrimitive } from '@base-ui/react/separator';

import { cn } from '@/lib/utils';

// The hairline rule (DESIGN_SYSTEM §5.1 `--color-border-subtle`): sections separated by space and rules, not
// by a card each (SLOP-01).
export function Separator({ className, orientation = 'horizontal', ...props }: SeparatorPrimitive.Props) {
    return (
        <SeparatorPrimitive
            data-slot="separator"
            orientation={orientation}
            className={cn('shrink-0 bg-border-subtle', orientation === 'horizontal' ? 'h-px w-full' : 'h-full w-px', className)}
            {...props}
        />
    );
}
