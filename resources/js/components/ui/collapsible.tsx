import { Collapsible as CollapsiblePrimitive } from '@base-ui/react/collapsible';

import { cn } from '@/lib/utils';

// The disclosure's primitive (DESIGN_SYSTEM §10 "Disclosure"): a real button with `aria-expanded` and
// `aria-controls` (Base UI's trigger), the panel's height animated on the reference's reveal, its body not
// rising (GF-42); no movement under reduced motion.
export const Collapsible = CollapsiblePrimitive.Root;
export const CollapsibleTrigger = CollapsiblePrimitive.Trigger;

export function CollapsiblePanel({ className, ...props }: CollapsiblePrimitive.Panel.Props) {
    return (
        <CollapsiblePrimitive.Panel
            data-slot="collapsible-panel"
            className={cn(
                'h-(--collapsible-panel-height) overflow-hidden transition-[height] duration-(--mc-dur-moderate) ease-reveal data-ending-style:h-0 data-starting-style:h-0 motion-reduce:transition-none',
                className,
            )}
            {...props}
        />
    );
}
