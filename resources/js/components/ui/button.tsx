import { Button as ButtonPrimitive } from '@base-ui/react/button';
import { cva, type VariantProps } from 'class-variance-authority';
import type { ReactNode } from 'react';

import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

// PT-11 on the reference's button (DESIGN_SYSTEM §9.4, §10): primary, outline, ghost, sunken and accent, the
// destructive fill only as the confirming button of a destructive dialog, a danger-text outline for a
// destructive or correcting action's trigger, the sign-in CTA; heights 24/32/36/40/42/56 with a radius about
// 0.18 × the height; the label at 600; a press scale of 0.97 (§5.4); disabled at 48 % with its reason beside
// it; pending keeping its width and label, a spinner after 300 ms (IP-02). A coarse pointer gets 44 px (GR-07).
const buttonVariants = cva(
    'group/button relative inline-flex min-w-0 shrink-0 items-center justify-center border font-semibold leading-none whitespace-nowrap select-none transition-[background-color,border-color,color,box-shadow,scale] duration-(--mc-dur-fast) ease-exit hover:duration-(--mc-dur-instant) motion-safe:not-disabled:not-aria-disabled:active:scale-97 disabled:cursor-not-allowed disabled:opacity-48 aria-disabled:cursor-not-allowed aria-disabled:opacity-48',
    {
        variants: {
            variant: {
                primary: 'border-primary-rim bg-primary text-text-on-primary not-disabled:hover:bg-primary-hover not-disabled:active:bg-primary-press',
                outline: 'border-border-strong bg-surface text-text-primary shadow-xs not-disabled:hover:border-border-control-hover not-disabled:hover:bg-canvas',
                ghost: 'border-transparent bg-transparent text-text-primary not-disabled:hover:bg-surface-sunken',
                sunken: 'border-transparent bg-surface-sunken text-text-primary not-disabled:hover:bg-sunken-hover',
                accent: 'border-transparent bg-lime-100 text-text-on-lime not-disabled:hover:bg-lime-100-hover',
                destructive: 'border-danger-rim bg-danger-solid text-text-on-primary not-disabled:hover:bg-danger-solid-hover',
                danger: 'border-border-strong bg-surface text-danger-fg shadow-xs not-disabled:hover:border-border-control-hover not-disabled:hover:bg-canvas',
                cta: 'border-lime-cta-rim bg-lime-cta text-lime-cta-ink shadow-[inset_0_-2px_0_var(--color-lime-cta-band),var(--shadow-cta)] not-disabled:hover:bg-lime-cta-hover',
            },
            size: {
                xs: 'h-control-xs gap-1 rounded-full px-2 text-caption',
                sm: 'h-control-sm gap-2 rounded-sm px-4 text-(length:--text-btn-sm-size)',
                md: 'h-control-md gap-2 rounded-sm px-4 text-(length:--text-btn-md-size)',
                lg: 'h-control-lg gap-3 rounded-md px-6 text-(length:--text-btn-lg-size)',
                header: 'h-control-header gap-3 rounded-md px-5 text-(length:--text-btn-header-size)',
                xl: 'h-control-xl gap-3 rounded-lg px-6 text-(length:--text-auth-button-size)',
                floor: 'h-floor gap-3 rounded-md px-6 text-m-body',
                'icon-xs': 'size-control-xs rounded-full',
                'icon-sm': 'size-control-sm rounded-sm',
                'icon-md': 'size-control-md rounded-sm',
                'icon-lg': 'size-control-lg rounded-md',
            },
            block: { true: 'flex w-full' },
        },
        defaultVariants: { variant: 'outline', size: 'md' },
    },
);

export type ButtonProps = ButtonPrimitive.Props &
    VariantProps<typeof buttonVariants> & {
        /** IP-02: one execution in flight — the label kept, a spinner after 300 ms, the control inert. */
        pending?: boolean;
        /** The spinner's announced text while pending, a catalogue string. */
        pendingLabel?: string;
        children?: ReactNode;
    };

export function Button({ className, variant, size, block, pending = false, pendingLabel, disabled, children, ...props }: ButtonProps) {
    return (
        <ButtonPrimitive
            data-slot="button"
            data-pending={pending || undefined}
            aria-busy={pending || undefined}
            disabled={disabled === true || pending}
            focusableWhenDisabled={pending}
            className={cn(buttonVariants({ variant, size, block }), className)}
            {...props}
        >
            {children}
            {pending && pendingLabel !== undefined && <Spinner label={pendingLabel} size="sm" className="absolute right-1 bg-inherit" />}
        </ButtonPrimitive>
    );
}

export { buttonVariants };
