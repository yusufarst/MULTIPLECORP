import { Menu as MenuPrimitive } from '@base-ui/react/menu';
import { Check } from 'lucide-react';
import type { ReactNode } from 'react';

import { Icon } from '@/components/ui/icon';
import { cn } from '@/lib/utils';

// The reference's menu — "Tindakan Lain", a row's "⋯", the account menu (DESIGN_SYSTEM §10): a body-level
// popover, never clipped, 42 px rows, the destructive item last; on the phone a bottom action sheet with 48 px
// rows. Base UI Menu: modal by default, the arrows move, Esc closes and returns focus to the trigger,
// typeahead (`loopFocus`, `finalFocus`).
export const DropdownMenu = MenuPrimitive.Root;
export const DropdownMenuTrigger = MenuPrimitive.Trigger;
export const DropdownMenuGroup = MenuPrimitive.Group;

export function DropdownMenuContent({
    className,
    children,
    align = 'end',
    ...props
}: MenuPrimitive.Popup.Props & Pick<MenuPrimitive.Positioner.Props, 'align'> & { children?: ReactNode }) {
    return (
        <MenuPrimitive.Portal>
            <MenuPrimitive.Backdrop className="fixed inset-0 z-(--z-popover) bg-scrim md:hidden" />
            <MenuPrimitive.Positioner
                align={align}
                sideOffset={4}
                className="z-(--z-popover) outline-none max-md:fixed! max-md:inset-x-0! max-md:top-auto! max-md:bottom-0! max-md:translate-none! max-md:w-full!"
            >
                <MenuPrimitive.Popup
                    data-slot="dropdown-menu-content"
                    data-motion
                    className={cn(
                        'min-w-48 origin-(--transform-origin) rounded-lg border border-border-subtle bg-surface p-2 text-body text-text-primary shadow-menu outline-none transition-[opacity,scale] duration-(--mc-dur-fast) ease-enter data-starting-style:scale-97 data-starting-style:opacity-0 data-ending-style:opacity-0',
                        'max-md:w-full max-md:rounded-b-none max-md:rounded-t-sheet max-md:pb-[calc(var(--space-2)+var(--safe-bottom))] max-md:shadow-drawer',
                        className,
                    )}
                    {...props}
                >
                    {children}
                </MenuPrimitive.Popup>
            </MenuPrimitive.Positioner>
        </MenuPrimitive.Portal>
    );
}

const itemClass =
    'relative flex min-h-floor cursor-default items-center gap-2 rounded-sm px-3 text-body outline-none select-none data-highlighted:bg-row-hover data-disabled:opacity-48 md:min-h-select-row';

export function DropdownMenuItem({ className, tone = 'default', ...props }: MenuPrimitive.Item.Props & { tone?: 'default' | 'danger' }) {
    return <MenuPrimitive.Item data-slot="dropdown-menu-item" className={cn(itemClass, tone === 'danger' && 'text-danger-fg', className)} {...props} />;
}

export function DropdownMenuCheckboxItem({ className, children, ...props }: MenuPrimitive.CheckboxItem.Props & { children?: ReactNode }) {
    return (
        <MenuPrimitive.CheckboxItem data-slot="dropdown-menu-checkbox-item" className={cn(itemClass, 'pr-8', className)} {...props}>
            {children}
            <MenuPrimitive.CheckboxItemIndicator className="absolute right-3 inline-flex text-accent-olive">
                <Icon icon={Check} size="sm" />
            </MenuPrimitive.CheckboxItemIndicator>
        </MenuPrimitive.CheckboxItem>
    );
}

export function DropdownMenuSeparator({ className, ...props }: MenuPrimitive.Separator.Props) {
    return <MenuPrimitive.Separator data-slot="dropdown-menu-separator" className={cn('-mx-2 my-2 h-px bg-border-subtle', className)} {...props} />;
}

export function DropdownMenuGroupLabel({ className, ...props }: MenuPrimitive.GroupLabel.Props) {
    return <MenuPrimitive.GroupLabel data-slot="dropdown-menu-label" className={cn('px-3 pt-2 pb-1 text-caption font-medium text-text-tertiary', className)} {...props} />;
}
