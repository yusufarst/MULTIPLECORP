import type { ComponentProps, ReactNode } from 'react';
import { CircleAlert } from 'lucide-react';

import { Icon } from '@/components/ui/icon';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

// PT-09's field (DESIGN_SYSTEM §9.3, §10): the label above, an 8 px gap, the control, the supporting text;
// helper text only where it prevents an error; the error under the field in ink after the danger tone's icon,
// tied to the control by `aria-describedby` (IP-14; WCAG 3.3.1). Copy arrives as catalogue strings.
export function Field({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="field" className={cn('flex min-w-0 flex-col gap-2', className)} {...props} />;
}

export function FieldLabel({ required, requiredText, children, className, ...props }: ComponentProps<'label'> & { required?: boolean; requiredText?: string }) {
    return (
        <Label data-slot="field-label" className={className} {...props}>
            {children}
            {required === true && requiredText !== undefined && (
                <>
                    <span aria-hidden="true" data-label-extra className="text-required">
                        *
                    </span>
                    <span data-label-extra className="text-text-tertiary">
                        {requiredText}
                    </span>
                </>
            )}
        </Label>
    );
}

export function FieldDescription({ className, ...props }: ComponentProps<'p'>) {
    return <p data-slot="field-description" className={cn('text-caption text-text-tertiary', className)} {...props} />;
}

export function FieldError({ className, children, ...props }: ComponentProps<'p'> & { children?: ReactNode }) {
    if (children === undefined || children === null || children === '') {
        return null;
    }

    return (
        <p data-slot="field-error" className={cn('flex items-start gap-1 text-caption text-text-primary', className)} {...props}>
            <Icon icon={CircleAlert} size="status" className="mt-px text-danger-fg" />
            <span>{children}</span>
        </p>
    );
}

export function FieldSet({ className, ...props }: ComponentProps<'fieldset'>) {
    return <fieldset data-slot="field-set" className={cn('flex min-w-0 flex-col gap-4 border-0 p-0', className)} {...props} />;
}

export function FieldLegend({ className, ...props }: ComponentProps<'legend'>) {
    return <legend data-slot="field-legend" className={cn('mb-2 text-heading-sm font-semibold text-text-primary', className)} {...props} />;
}

/** Related fields in pairs, two columns within the form width from the tablet band; one column on phones. */
export function FieldGroup({ className, columns = 1, ...props }: ComponentProps<'div'> & { columns?: 1 | 2 }) {
    return <div data-slot="field-group" className={cn('grid min-w-0 gap-4', columns === 2 && 'md:grid-cols-2', className)} {...props} />;
}
