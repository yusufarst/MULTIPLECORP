import { Radio as RadioPrimitive } from '@base-ui/react/radio';
import { RadioGroup as RadioGroupPrimitive } from '@base-ui/react/radio-group';

import { cn } from '@/lib/utils';

// One-line choices are a radio group (DESIGN_SYSTEM §9.3, GF-28): 18 px circles with the control edge at 3:1
// (GR-01), the chosen one ringed in primary with its dot.
export function RadioGroup({ className, ...props }: RadioGroupPrimitive.Props) {
    return <RadioGroupPrimitive data-slot="radio-group" className={cn('flex flex-col gap-3', className)} {...props} />;
}

export function RadioGroupItem({ className, ...props }: RadioPrimitive.Root.Props) {
    return (
        <RadioPrimitive.Root
            data-slot="radio-group-item"
            className={cn(
                'peer inline-flex size-4.5 shrink-0 items-center justify-center rounded-full border border-border-input bg-surface outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus aria-invalid:border-danger-fg data-checked:border-primary data-disabled:cursor-not-allowed data-disabled:opacity-48',
                className,
            )}
            {...props}
        >
            <RadioPrimitive.Indicator data-slot="radio-group-indicator" className="size-2 rounded-full bg-primary" />
        </RadioPrimitive.Root>
    );
}
