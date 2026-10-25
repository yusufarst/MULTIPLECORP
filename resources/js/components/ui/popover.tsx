import { Popover as PopoverPrimitive } from '@base-ui/react/popover';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

// A body-level popover (DESIGN_SYSTEM §10 "Popover, tooltip and long text"): never clipped, the menu radius and
// shadow, dismissible and persistent (WCAG 1.4.13); the company filter's checklist and the business date's
// calendar sit in one (PT-04, PT-25).
export const Popover = PopoverPrimitive.Root;
export const PopoverTrigger = PopoverPrimitive.Trigger;
export const PopoverClose = PopoverPrimitive.Close;

export function PopoverContent({
    className,
    children,
    side = 'bottom',
    align = 'start',
    sideOffset = 6,
    ...props
}: PopoverPrimitive.Popup.Props & Pick<PopoverPrimitive.Positioner.Props, 'side' | 'align' | 'sideOffset'> & { children?: ReactNode }) {
    return (
        <PopoverPrimitive.Portal>
            <PopoverPrimitive.Positioner side={side} align={align} sideOffset={sideOffset} className="z-(--z-popover)">
                <PopoverPrimitive.Popup
                    data-slot="popover-content"
                    data-motion
                    className={cn(
                        'max-h-(--available-height) max-w-(--available-width) origin-(--transform-origin) overflow-y-auto rounded-lg border border-border-subtle bg-surface p-3 text-body text-text-primary shadow-menu outline-none transition-[opacity,scale] duration-(--mc-dur-fast) ease-enter data-starting-style:scale-97 data-starting-style:opacity-0 data-ending-style:opacity-0',
                        className,
                    )}
                    {...props}
                >
                    {children}
                </PopoverPrimitive.Popup>
            </PopoverPrimitive.Positioner>
        </PopoverPrimitive.Portal>
    );
}

export const PopoverTitle = PopoverPrimitive.Title;
export const PopoverDescription = PopoverPrimitive.Description;
