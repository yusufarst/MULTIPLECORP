import { Tooltip as TooltipPrimitive } from '@base-ui/react/tooltip';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

// A tooltip (DESIGN_SYSTEM §10): only what is also on the screen or behind a tap — touch has no hover (§11);
// hoverable, dismissible with Esc and persistent while hovered (WCAG 1.4.13).
export const TooltipProvider = TooltipPrimitive.Provider;
export const Tooltip = TooltipPrimitive.Root;
export const TooltipTrigger = TooltipPrimitive.Trigger;

export function TooltipContent({ className, children, side = 'top', ...props }: TooltipPrimitive.Popup.Props & { side?: TooltipPrimitive.Positioner.Props['side']; children?: ReactNode }) {
    return (
        <TooltipPrimitive.Portal>
            <TooltipPrimitive.Positioner side={side} sideOffset={6} className="z-(--z-popover)">
                <TooltipPrimitive.Popup
                    data-slot="tooltip-content"
                    className={cn(
                        'max-w-(--available-width) rounded-sm bg-text-primary px-2 py-1 text-caption text-text-on-primary shadow-pop transition-opacity duration-(--mc-dur-fast) data-starting-style:opacity-0 data-ending-style:opacity-0',
                        className,
                    )}
                    {...props}
                >
                    {children}
                </TooltipPrimitive.Popup>
            </TooltipPrimitive.Positioner>
        </TooltipPrimitive.Portal>
    );
}
