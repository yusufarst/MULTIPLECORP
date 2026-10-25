import { Dialog as DialogPrimitive } from '@base-ui/react/dialog';
import { X } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { cn } from '@/lib/utils';

// PT-16's dialog and sheet (DESIGN_SYSTEM §9.4, §10, §11): the reference's application-level dialog, centred,
// 400 px, radius 12, the modal shadow over the scrim, from the tablet band; on the phone the same dialog is a
// sheet — `bottom` for a short confirmation with its button at the bottom, `full` for a form, a filter or an
// irreversible confirmation. Base UI traps focus while modal and returns it to the trigger (`finalFocus`);
// Esc and the scrim dismiss it unless the caller forbids it (the re-authentication dialog, KB-04). The
// dialog's motion is the reference's — a fade and a 0.97 scale, none under reduced motion (D-DS-17).
export const Dialog = DialogPrimitive.Root;
export const DialogTrigger = DialogPrimitive.Trigger;
export const DialogClose = DialogPrimitive.Close;

export function DialogBackdrop({ className, opaque = false, ...props }: DialogPrimitive.Backdrop.Props & { opaque?: boolean }) {
    return (
        <DialogPrimitive.Backdrop
            data-slot="dialog-backdrop"
            className={cn(
                'fixed inset-0 z-(--z-modal) transition-opacity duration-(--mc-dur-moderate) ease-standard data-starting-style:opacity-0 data-ending-style:opacity-0',
                opaque ? 'bg-canvas' : 'bg-scrim',
                className,
            )}
            {...props}
        />
    );
}

export type DialogPhone = 'bottom' | 'full';

export function DialogContent({
    className,
    children,
    phone = 'bottom',
    opaqueBackdrop = false,
    closeLabel,
    ...props
}: DialogPrimitive.Popup.Props & { phone?: DialogPhone; opaqueBackdrop?: boolean; closeLabel?: string; children?: ReactNode }) {
    return (
        <DialogPrimitive.Portal>
            <DialogBackdrop opaque={opaqueBackdrop} />
            <DialogPrimitive.Popup
                data-slot="dialog-content"
                data-phone={phone}
                data-motion
                className={cn(
                    'fixed z-(--z-modal) flex flex-col gap-4 bg-surface text-body text-text-primary shadow-modal outline-none transition-[opacity,scale,translate] duration-(--mc-dur-moderate) ease-enter data-ending-style:opacity-0 data-starting-style:opacity-0',
                    'inset-x-0 bottom-0 max-h-[calc(100dvh-var(--safe-top))] overflow-y-auto rounded-t-sheet p-m-card pb-[calc(var(--m-card-pad)+var(--safe-bottom))] data-starting-style:translate-y-4 data-[phone=full]:top-(--safe-top) data-[phone=full]:max-h-none',
                    'md:inset-auto md:top-1/2 md:left-1/2 md:max-h-[calc(100dvh-var(--space-8))] md:w-dialog md:max-w-[calc(100vw-var(--space-8))] md:-translate-1/2 md:rounded-xl md:p-6 md:data-starting-style:-translate-1/2 md:data-starting-style:scale-97 md:data-[phone=full]:top-1/2',
                    className,
                )}
                {...props}
            >
                {children}
                {closeLabel !== undefined && (
                    <DialogPrimitive.Close render={<Button variant="ghost" size="icon-sm" className="absolute top-3 right-3" aria-label={closeLabel} />}>
                        <Icon icon={X} size="sm" />
                    </DialogPrimitive.Close>
                )}
            </DialogPrimitive.Popup>
        </DialogPrimitive.Portal>
    );
}

export function DialogHeader({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="dialog-header" className={cn('flex flex-col gap-2 pr-8', className)} {...props} />;
}

export function DialogTitle({ className, ...props }: DialogPrimitive.Title.Props) {
    return <DialogPrimitive.Title data-slot="dialog-title" className={cn('text-heading-sm font-semibold text-text-primary md:text-heading', className)} {...props} />;
}

export function DialogDescription({ className, ...props }: DialogPrimitive.Description.Props) {
    return <DialogPrimitive.Description data-slot="dialog-description" className={cn('text-body text-text-secondary', className)} {...props} />;
}

/** The dialog's buttons: "Kembali" before the confirming verb, the confirming button last; full-width and
    stacked at the bottom of a phone sheet (PT-16). */
export function DialogFooter({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="dialog-footer"
            className={cn('mt-2 flex flex-col-reverse gap-2 *:w-full md:flex-row md:justify-end md:*:w-auto', className)}
            {...props}
        />
    );
}
