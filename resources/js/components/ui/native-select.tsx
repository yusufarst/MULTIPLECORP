import { ChevronDown } from 'lucide-react';
import type { ComponentProps } from 'react';

import { Icon } from '@/components/ui/icon';
import { fieldBoxClass } from '@/components/ui/input';
import { cn } from '@/lib/utils';

// The native select on the field box (DESIGN_SYSTEM §10): the platform's own list, which a phone shows as its
// own sheet; the reference's chevron drawn over it.
export function NativeSelect({ className, ...props }: ComponentProps<'select'>) {
    return (
        <span data-slot="native-select" className="relative inline-flex w-full min-w-0 items-center">
            <select className={cn(fieldBoxClass, 'cursor-pointer appearance-none pr-10', className)} {...props} />
            <Icon icon={ChevronDown} size="sm" className="pointer-events-none absolute right-3 text-text-tertiary" />
        </span>
    );
}
