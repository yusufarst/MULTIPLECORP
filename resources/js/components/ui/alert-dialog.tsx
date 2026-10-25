import { AlertDialog as AlertDialogPrimitive } from '@base-ui/react/alert-dialog';
import type { ComponentProps, ReactNode } from 'react';

import { cn } from '@/lib/utils';

// PT-16's AlertDialog for short confirmations — irreversible, destructive, step-up, re-authentication
// (DESIGN_SYSTEM §9.4): never dismissed by the scrim; centred at 400 px from the tablet band, a full-height
// sheet on the phone, its confirming button at the bottom (§11). Focus moves to its first input or, when
// there is none, to "Kembali" — the caller's `initialFocus` —, never to the confirming button (PT-26).
export const AlertDialog = AlertDialogPrimitive.Root;
export const AlertDialogTrigger = AlertDialogPrimitive.Trigger;
export const AlertDialogClose = AlertDialogPrimitive.Close;

export function AlertDialogContent({
    className,
    children,
    opaqueBackdrop = false,
    ...props
}: AlertDialogPrimitive.Popup.Props & { opaqueBackdrop?: boolean; children?: ReactNode }) {
    return (
        <AlertDialogPrimitive.Portal>
            <AlertDialogPrimitive.Backdrop
                data-slot="alert-dialog-backdrop"
                className={cn(
                    'fixed inset-0 z-(--z-modal) transition-opacity duration-(--mc-dur-moderate) ease-standard data-starting-style:opacity-0 data-ending-style:opacity-0',
                    opaqueBackdrop ? 'bg-canvas' : 'bg-scrim',
                )}
            />
            <AlertDialogPrimitive.Popup
                data-slot="alert-dialog-content"
                data-motion
                className={cn(
                    'fixed inset-x-0 top-(--safe-top) bottom-0 z-(--z-modal) flex flex-col gap-4 overflow-y-auto rounded-t-sheet bg-surface p-m-card pb-[calc(var(--m-card-pad)+var(--safe-bottom))] text-body text-text-primary shadow-modal outline-none transition-[opacity,scale,translate] duration-(--mc-dur-moderate) ease-enter data-starting-style:translate-y-4 data-starting-style:opacity-0 data-ending-style:opacity-0',
                    'md:inset-auto md:top-1/2 md:left-1/2 md:max-h-[calc(100dvh-var(--space-8))] md:w-dialog md:max-w-[calc(100vw-var(--space-8))] md:-translate-1/2 md:rounded-xl md:p-6 md:data-starting-style:-translate-1/2 md:data-starting-style:scale-97',
                    className,
                )}
                {...props}
            >
                {children}
            </AlertDialogPrimitive.Popup>
        </AlertDialogPrimitive.Portal>
    );
}

export function AlertDialogHeader({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="alert-dialog-header" className={cn('flex flex-col gap-2', className)} {...props} />;
}

export function AlertDialogTitle({ className, ...props }: AlertDialogPrimitive.Title.Props) {
    return <AlertDialogPrimitive.Title data-slot="alert-dialog-title" className={cn('text-heading-sm font-semibold text-text-primary md:text-heading', className)} {...props} />;
}

export function AlertDialogDescription({ className, ...props }: AlertDialogPrimitive.Description.Props) {
    return <AlertDialogPrimitive.Description data-slot="alert-dialog-description" className={cn('text-body text-text-secondary', className)} {...props} />;
}

export function AlertDialogFooter({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="alert-dialog-footer"
            className={cn('mt-auto flex flex-col-reverse gap-2 pt-2 *:w-full md:mt-2 md:flex-row md:justify-end md:*:w-auto', className)}
            {...props}
        />
    );
}
