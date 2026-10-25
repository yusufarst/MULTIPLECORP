import { Toggle as TogglePrimitive } from '@base-ui/react/toggle';
import { ToggleGroup as ToggleGroupPrimitive } from '@base-ui/react/toggle-group';

import { cn } from '@/lib/utils';

// The segmented control (DESIGN_SYSTEM §10 "Segmented control"): views inside one section on a sunken track,
// the selected segment raised on white with an olive mark under its label — never the fill alone (GR-02);
// "Rincian | Pratinjau", the Owner's *Tampilan*, the sign-in's "ID | EN".
export function ToggleGroup({ className, ...props }: ToggleGroupPrimitive.Props) {
    return (
        <ToggleGroupPrimitive
            data-slot="toggle-group"
            className={cn('inline-flex min-w-0 items-center gap-0.5 rounded-md border border-border-toggle bg-surface-sunken p-0.5', className)}
            {...props}
        />
    );
}

export function ToggleGroupItem({ className, ...props }: TogglePrimitive.Props) {
    return (
        <TogglePrimitive
            data-slot="toggle-group-item"
            className={cn(
                'relative inline-flex h-control-sm min-w-control-sm items-center justify-center rounded-sm px-3 text-label font-medium text-text-secondary outline-none select-none hover:text-text-primary data-pressed:bg-surface data-pressed:font-semibold data-pressed:text-text-primary data-pressed:shadow-xs data-pressed:after:absolute data-pressed:after:inset-x-3 data-pressed:after:bottom-1 data-pressed:after:h-0.5 data-pressed:after:rounded-full data-pressed:after:bg-accent-olive',
                className,
            )}
            {...props}
        />
    );
}
