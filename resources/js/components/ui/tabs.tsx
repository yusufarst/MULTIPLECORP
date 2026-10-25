import { Tabs as TabsPrimitive } from '@base-ui/react/tabs';

import { cn } from '@/lib/utils';

// Real tabs (DESIGN_SYSTEM §10 "Tabs"): the selected tab underlined in primary, arrows between tabs (Base UI);
// a row that overflows on the phone scrolls with the reference's edge mask (GF-14). The address keeps the
// tab — `?tab=` — through the record page's own state (PT-12).
export const Tabs = TabsPrimitive.Root;

export function TabsList({ className, ...props }: TabsPrimitive.List.Props) {
    return (
        <TabsPrimitive.List
            data-slot="tabs-list"
            className={cn(
                'relative flex min-w-0 gap-6 overflow-x-auto border-b border-border-subtle [scrollbar-width:none] max-md:[mask-image:linear-gradient(to_right,var(--color-text-primary)_calc(100%-var(--space-6)),transparent)]',
                className,
            )}
            {...props}
        />
    );
}

export function TabsTab({ className, ...props }: TabsPrimitive.Tab.Props) {
    return (
        <TabsPrimitive.Tab
            data-slot="tabs-tab"
            className={cn(
                'relative inline-flex min-h-touch shrink-0 items-center text-body font-medium whitespace-nowrap text-text-secondary outline-none select-none hover:text-text-primary data-selected:font-semibold data-selected:text-text-primary data-selected:after:absolute data-selected:after:inset-x-0 data-selected:after:-bottom-px data-selected:after:h-0.5 data-selected:after:bg-primary md:min-h-control-lg',
                className,
            )}
            {...props}
        />
    );
}

export function TabsPanel({ className, ...props }: TabsPrimitive.Panel.Props) {
    return <TabsPrimitive.Panel data-slot="tabs-panel" className={cn('pt-4 outline-none', className)} {...props} />;
}
