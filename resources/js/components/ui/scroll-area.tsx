import { ScrollArea as ScrollAreaPrimitive } from '@base-ui/react/scroll-area';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

// A scrolling frame reachable by keyboard (DESIGN_SYSTEM §14.3; WCAG 2.1.1): the document preview, a wide table.
export function ScrollArea({ className, children, label, ...props }: ScrollAreaPrimitive.Root.Props & { label?: string; children?: ReactNode }) {
    return (
        <ScrollAreaPrimitive.Root data-slot="scroll-area" className={cn('relative min-w-0 overflow-hidden', className)} {...props}>
            <ScrollAreaPrimitive.Viewport tabIndex={0} aria-label={label} className="size-full rounded-[inherit] outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-focus">
                {children}
            </ScrollAreaPrimitive.Viewport>
            <ScrollAreaPrimitive.Scrollbar orientation="vertical" className="flex w-2 justify-center p-0.5">
                <ScrollAreaPrimitive.Thumb className="w-full rounded-full bg-border-strong" />
            </ScrollAreaPrimitive.Scrollbar>
            <ScrollAreaPrimitive.Scrollbar orientation="horizontal" className="flex h-2 items-center p-0.5">
                <ScrollAreaPrimitive.Thumb className="h-full rounded-full bg-border-strong" />
            </ScrollAreaPrimitive.Scrollbar>
        </ScrollAreaPrimitive.Root>
    );
}
