import { Select as SelectPrimitive } from '@base-ui/react/select';
import { Check, ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';

import { Icon } from '@/components/ui/icon';
import { fieldBoxClass } from '@/components/ui/input';
import { cn } from '@/lib/utils';

// The reference's custom select (DESIGN_SYSTEM §10 "Select and lookup"; §5.3): a combobox trigger on the
// field box with its chevron, a listbox popover — 8 px of padding, 42 px rows of 6 px radius, an olive check,
// the 10 px menu radius, at most 320 px high — and on the phone a bottom sheet of 52 px rows. Arrows, Home,
// End, typeahead and Esc are the primitive's (Base UI Select). A value never truncates: it wraps (D-DS-18).
export const Select = SelectPrimitive.Root;

export function SelectTrigger({ className, children, ...props }: SelectPrimitive.Trigger.Props & { children?: ReactNode }) {
    return (
        <SelectPrimitive.Trigger
            data-slot="select-trigger"
            className={cn(fieldBoxClass, 'flex h-auto min-h-control-md cursor-pointer items-center justify-between gap-2 py-1 text-left data-popup-open:border-accent-olive', className)}
            {...props}
        >
            {children}
            <SelectPrimitive.Icon className="shrink-0 text-text-tertiary transition-transform duration-(--mc-dur-fast) data-popup-open:rotate-180 motion-reduce:transition-none motion-reduce:data-popup-open:rotate-0">
                <Icon icon={ChevronDown} size="sm" />
            </SelectPrimitive.Icon>
        </SelectPrimitive.Trigger>
    );
}

export function SelectValue({ className, ...props }: SelectPrimitive.Value.Props) {
    return <SelectPrimitive.Value data-slot="select-value" className={cn('min-w-0 flex-1 break-words', className)} {...props} />;
}

export function SelectContent({ className, children, ...props }: SelectPrimitive.Popup.Props & { children?: ReactNode }) {
    return (
        <SelectPrimitive.Portal>
            <SelectPrimitive.Backdrop className="fixed inset-0 z-(--z-popover) md:hidden" />
            <SelectPrimitive.Positioner
                sideOffset={4}
                alignItemWithTrigger={false}
                className="z-(--z-popover) outline-none max-md:fixed! max-md:inset-x-0! max-md:top-auto! max-md:bottom-0! max-md:translate-none! max-md:w-full!"
            >
                <SelectPrimitive.Popup
                    data-slot="select-content"
                    className={cn(
                        'max-h-(--available-height) w-(--anchor-width) min-w-36 origin-(--transform-origin) overflow-y-auto rounded-lg border border-border-subtle bg-surface p-2 text-body text-text-primary shadow-menu outline-none transition-[opacity,scale] duration-(--mc-dur-fast) ease-enter data-starting-style:scale-97 data-starting-style:opacity-0 data-ending-style:opacity-0 md:max-h-select-menu',
                        'max-md:w-full max-md:rounded-b-none max-md:rounded-t-sheet max-md:pb-[calc(var(--space-2)+var(--safe-bottom))] max-md:shadow-drawer',
                        className,
                    )}
                    {...props}
                >
                    <SelectPrimitive.List>{children}</SelectPrimitive.List>
                </SelectPrimitive.Popup>
            </SelectPrimitive.Positioner>
        </SelectPrimitive.Portal>
    );
}

export function SelectItem({ className, children, ...props }: SelectPrimitive.Item.Props & { children?: ReactNode }) {
    return (
        <SelectPrimitive.Item
            data-slot="select-item"
            className={cn(
                'relative flex min-h-sheet-row cursor-default items-center gap-2 rounded-sm px-3 pr-8 text-body outline-none select-none data-highlighted:bg-row-hover data-selected:bg-lime-150 data-disabled:opacity-48 md:min-h-select-row',
                className,
            )}
            {...props}
        >
            <SelectPrimitive.ItemText className="min-w-0 flex-1 break-words">{children}</SelectPrimitive.ItemText>
            <SelectPrimitive.ItemIndicator className="absolute right-3 inline-flex text-accent-olive">
                <Icon icon={Check} size="sm" />
            </SelectPrimitive.ItemIndicator>
        </SelectPrimitive.Item>
    );
}

export function SelectGroup(props: SelectPrimitive.Group.Props) {
    return <SelectPrimitive.Group data-slot="select-group" {...props} />;
}

export function SelectGroupLabel({ className, ...props }: SelectPrimitive.GroupLabel.Props) {
    return <SelectPrimitive.GroupLabel data-slot="select-label" className={cn('px-3 pt-2 pb-1 text-caption font-medium text-text-tertiary', className)} {...props} />;
}
