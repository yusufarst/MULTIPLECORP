import { Combobox as ComboboxPrimitive } from '@base-ui/react/combobox';
import { Check } from 'lucide-react';
import type { ReactNode } from 'react';

import { Icon } from '@/components/ui/icon';
import { InputGroup } from '@/components/ui/input-group';
import { cn } from '@/lib/utils';

// The type-ahead lookup (DESIGN_SYSTEM §10 "Select and lookup"; PT-27; IP-17): the field with its listbox
// popover of 42 px rows — 52 px on the phone —, results grouped by type, ↑ ↓ and Enter to choose (KB-11),
// Esc to close. The results are the server's; the primitive never filters them by itself.
export const Combobox = ComboboxPrimitive.Root;

export function ComboboxInput({ className, children, ...props }: Omit<ComboboxPrimitive.Input.Props, 'className'> & { className?: string; children?: ReactNode }) {
    return (
        <InputGroup className={className}>
            {children}
            <ComboboxPrimitive.Input
                data-slot="input-group-control"
                className="h-full min-w-0 flex-1 bg-transparent px-3 outline-none placeholder:text-text-placeholder disabled:cursor-not-allowed"
                {...props}
            />
        </InputGroup>
    );
}

export function ComboboxContent({ className, children, ...props }: ComboboxPrimitive.Popup.Props & { children?: ReactNode }) {
    return (
        <ComboboxPrimitive.Portal>
            <ComboboxPrimitive.Positioner sideOffset={4} align="start" className="z-(--z-popover)">
                <ComboboxPrimitive.Popup
                    data-slot="combobox-content"
                    data-motion
                    className={cn(
                        'max-h-(--available-height) w-(--anchor-width) max-w-(--available-width) min-w-64 origin-(--transform-origin) overflow-hidden rounded-lg border border-border-subtle bg-surface text-body text-text-primary shadow-menu outline-none transition-[opacity,scale] duration-(--mc-dur-fast) ease-enter data-starting-style:scale-97 data-starting-style:opacity-0 data-ending-style:opacity-0',
                        className,
                    )}
                    {...props}
                >
                    {children}
                </ComboboxPrimitive.Popup>
            </ComboboxPrimitive.Positioner>
        </ComboboxPrimitive.Portal>
    );
}

export function ComboboxList({ className, ...props }: ComboboxPrimitive.List.Props) {
    return <ComboboxPrimitive.List data-slot="combobox-list" className={cn('max-h-select-menu overflow-y-auto overscroll-contain p-2', className)} {...props} />;
}

export function ComboboxItem({ className, children, ...props }: ComboboxPrimitive.Item.Props & { children?: ReactNode }) {
    return (
        <ComboboxPrimitive.Item
            data-slot="combobox-item"
            className={cn(
                'relative flex min-h-sheet-row cursor-default items-center gap-2 rounded-sm px-3 pr-8 text-body outline-none select-none data-highlighted:bg-row-hover data-selected:bg-lime-150 md:min-h-select-row',
                className,
            )}
            {...props}
        >
            {children}
            <ComboboxPrimitive.ItemIndicator className="absolute right-3 inline-flex text-accent-olive">
                <Icon icon={Check} size="sm" />
            </ComboboxPrimitive.ItemIndicator>
        </ComboboxPrimitive.Item>
    );
}

export function ComboboxGroup({ className, ...props }: ComboboxPrimitive.Group.Props) {
    return <ComboboxPrimitive.Group data-slot="combobox-group" className={cn('not-first:mt-2', className)} {...props} />;
}

export function ComboboxGroupLabel({ className, ...props }: ComboboxPrimitive.GroupLabel.Props) {
    return <ComboboxPrimitive.GroupLabel data-slot="combobox-group-label" className={cn('px-3 pt-1 pb-1 text-caption font-medium text-text-tertiary', className)} {...props} />;
}

export function ComboboxEmpty({ className, ...props }: ComboboxPrimitive.Empty.Props) {
    return <ComboboxPrimitive.Empty data-slot="combobox-empty" className={cn('px-3 py-2 text-body text-text-secondary empty:hidden', className)} {...props} />;
}

export const ComboboxCollection = ComboboxPrimitive.Collection;
