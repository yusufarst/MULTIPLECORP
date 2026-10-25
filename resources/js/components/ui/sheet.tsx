import { Dialog as SheetPrimitive } from '@base-ui/react/dialog';
import { X } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { cn } from '@/lib/utils';

// PT-16's Sheet (DESIGN_SYSTEM §9.4): from the right on the desktop, 480–640 px wide, for longer forms, filters
// and record previews beside a list; a full-height sheet on the phone (§11). The drawer shadow; a clip reveal
// from 3 % on the reference's panel recipe, none under reduced motion (D-DS-17).
export const Sheet = SheetPrimitive.Root;
export const SheetTrigger = SheetPrimitive.Trigger;
export const SheetClose = SheetPrimitive.Close;

export function SheetContent({
    className,
    children,
    width = 'sm',
    closeLabel,
    ...props
}: SheetPrimitive.Popup.Props & { width?: 'sm' | 'lg'; closeLabel?: string; children?: ReactNode }) {
    return (
        <SheetPrimitive.Portal>
            <SheetPrimitive.Backdrop className="fixed inset-0 z-(--z-sheet) bg-scrim transition-opacity duration-(--mc-dur-sheet) ease-standard data-starting-style:opacity-0 data-ending-style:opacity-0" />
            <SheetPrimitive.Popup
                data-slot="sheet-content"
                data-motion
                className={cn(
                    'fixed inset-0 z-(--z-sheet) flex flex-col gap-4 overflow-y-auto bg-surface p-m-card pt-[calc(var(--m-card-pad)+var(--safe-top))] pb-[calc(var(--m-card-pad)+var(--safe-bottom))] text-body text-text-primary shadow-drawer outline-none transition-[opacity,translate] duration-(--mc-dur-sheet) ease-reveal data-starting-style:translate-y-4 data-starting-style:opacity-0 data-ending-style:opacity-0',
                    'md:left-auto md:w-full md:p-6 md:data-starting-style:translate-x-6 md:data-starting-style:translate-y-0',
                    width === 'lg' ? 'md:max-w-sheet-lg' : 'md:max-w-sheet-sm',
                    className,
                )}
                {...props}
            >
                {children}
                {closeLabel !== undefined && (
                    <SheetPrimitive.Close render={<Button variant="ghost" size="icon-sm" className="absolute top-[calc(var(--space-3)+var(--safe-top))] right-3 md:top-3" aria-label={closeLabel} />}>
                        <Icon icon={X} size="sm" />
                    </SheetPrimitive.Close>
                )}
            </SheetPrimitive.Popup>
        </SheetPrimitive.Portal>
    );
}

export function SheetHeader({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="sheet-header" className={cn('flex flex-col gap-2 pr-10', className)} {...props} />;
}

export function SheetTitle({ className, ...props }: SheetPrimitive.Title.Props) {
    return <SheetPrimitive.Title data-slot="sheet-title" className={cn('text-heading font-semibold text-text-primary', className)} {...props} />;
}

export function SheetDescription({ className, ...props }: SheetPrimitive.Description.Props) {
    return <SheetPrimitive.Description data-slot="sheet-description" className={cn('text-body text-text-secondary', className)} {...props} />;
}

export function SheetFooter({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="sheet-footer" className={cn('mt-auto flex flex-col-reverse gap-2 pt-4 *:w-full md:flex-row md:justify-end md:*:w-auto', className)} {...props} />;
}
