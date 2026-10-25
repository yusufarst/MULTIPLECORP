import { ChevronRight } from 'lucide-react';
import type { ComponentProps } from 'react';

import { Icon } from '@/components/ui/icon';
import { cn } from '@/lib/utils';

// The ancestors-only breadcrumb (DESIGN_SYSTEM PT-03): at most three levels, the label style, each ancestor a
// link; the current page is the title, not a crumb.
export function Breadcrumb({ className, label, ...props }: ComponentProps<'nav'> & { label: string }) {
    return <nav data-slot="breadcrumb" aria-label={label} className={className} {...props} />;
}

export function BreadcrumbList({ className, ...props }: ComponentProps<'ol'>) {
    return <ol data-slot="breadcrumb-list" className={cn('flex min-w-0 flex-wrap items-center gap-1 text-label text-text-tertiary', className)} {...props} />;
}

export function BreadcrumbItem({ className, separator = true, children, ...props }: ComponentProps<'li'> & { separator?: boolean }) {
    return (
        <li data-slot="breadcrumb-item" className={cn('inline-flex items-center gap-1', className)} {...props}>
            {children}
            {separator && <Icon icon={ChevronRight} size="xs" className="text-text-tertiary" />}
        </li>
    );
}

export function BreadcrumbLink({ className, ...props }: ComponentProps<'a'>) {
    return <a data-slot="breadcrumb-link" className={cn('text-text-secondary underline decoration-link-underline underline-offset-4 hover:text-text-primary', className)} {...props} />;
}
