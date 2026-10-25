import { Switch as SwitchPrimitive } from '@base-ui/react/switch';

import { cn } from '@/lib/utils';

// A switch (DESIGN_SYSTEM §10): a 36 × 20 track with the control edge, the thumb moving to the primary fill when
// on — a position as well as a colour —; no movement under reduced motion (D-DS-17).
export function Switch({ className, ...props }: SwitchPrimitive.Root.Props) {
    return (
        <SwitchPrimitive.Root
            data-slot="switch"
            className={cn(
                'peer inline-flex h-5 w-9 shrink-0 items-center rounded-full border border-border-input bg-surface-sunken p-0.5 outline-none transition-colors duration-(--mc-dur-fast) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus data-checked:border-primary data-checked:bg-primary data-disabled:cursor-not-allowed data-disabled:opacity-48',
                className,
            )}
            {...props}
        >
            <SwitchPrimitive.Thumb
                data-slot="switch-thumb"
                data-motion
                className="size-3.5 rounded-full bg-surface shadow-xs transition-[translate] duration-(--mc-dur-fast) ease-standard data-checked:translate-x-4"
            />
        </SwitchPrimitive.Root>
    );
}
