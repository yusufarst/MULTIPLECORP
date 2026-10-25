import { Checkbox as CheckboxPrimitive } from '@base-ui/react/checkbox';
import { Check, Minus } from 'lucide-react';

import { Icon } from '@/components/ui/icon';
import { cn } from '@/lib/utils';

// An author-styled checkbox (DESIGN_SYSTEM §10 "Checkbox, radio and switch"): 18 px, the control edge at 3:1
// (GR-01), checked in the primary fill with a white check — a shape, never colour alone —; its target held
// to 24 px, 44 px with a coarse pointer, by its label row.
export function Checkbox({ className, ...props }: CheckboxPrimitive.Root.Props) {
    return (
        <CheckboxPrimitive.Root
            data-slot="checkbox"
            className={cn(
                'peer inline-flex size-4.5 shrink-0 items-center justify-center rounded-xs border border-border-input bg-surface text-text-on-primary transition-colors duration-(--mc-dur-fast) outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus aria-invalid:border-danger-fg data-checked:border-primary data-checked:bg-primary data-indeterminate:border-primary data-indeterminate:bg-primary data-disabled:cursor-not-allowed data-disabled:opacity-48',
                className,
            )}
            {...props}
        >
            <CheckboxPrimitive.Indicator data-slot="checkbox-indicator" className="inline-flex" keepMounted={false}>
                {props.indeterminate === true ? <Icon icon={Minus} size="xs" /> : <Icon icon={Check} size="xs" />}
            </CheckboxPrimitive.Indicator>
        </CheckboxPrimitive.Root>
    );
}
