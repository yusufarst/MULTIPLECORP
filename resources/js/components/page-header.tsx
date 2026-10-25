import { ArrowLeft, Ellipsis } from 'lucide-react';
import type { ReactNode } from 'react';

import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList } from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Icon } from '@/components/ui/icon';
import { useTranslation } from '@/lib/i18n';

// PT-03's page header (DESIGN_SYSTEM §9.1, §10 "Page header"): the ancestors-only breadcrumb — at most three levels
// —, the page's one `h1`, one line of record facts — the number, the company label, the status badge —, one
// primary action on the right and the secondary ones in "Tindakan Lain"; the business-date control on a command
// form. On the phone the back arrow and the title lead and the primary action moves to the bottom action bar.
// No description paragraph, hero, greeting or illustration (SLOP-19, SLOP-27).
export type Crumb = { label: string; href: string };
export type MenuAction = { label: string; onSelect: () => void; tone?: 'default' | 'danger' };

export function PageHeader({
    title,
    crumbs = [],
    back,
    facts,
    primary,
    actions = [],
    date,
}: {
    title: ReactNode;
    crumbs?: Crumb[];
    back?: Crumb;
    facts?: ReactNode;
    primary?: ReactNode;
    actions?: MenuAction[];
    date?: ReactNode;
}) {
    const { t } = useTranslation();
    const ancestors = crumbs.slice(-3);
    // The destructive or correcting action sits last in the menu (§10 "Menu").
    const ordered = [...actions.filter((action) => action.tone !== 'danger'), ...actions.filter((action) => action.tone === 'danger')];

    return (
        <header data-slot="page-header" className="flex min-w-0 flex-col gap-3 pb-4">
            {ancestors.length > 0 && (
                <Breadcrumb label={t('nav.breadcrumb')} className="max-md:hidden">
                    <BreadcrumbList>
                        {ancestors.map((crumb) => (
                            <BreadcrumbItem key={crumb.href}>
                                <BreadcrumbLink href={crumb.href}>{crumb.label}</BreadcrumbLink>
                            </BreadcrumbItem>
                        ))}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
            <div className="flex min-w-0 flex-wrap items-start gap-3">
                {back !== undefined && (
                    <Button variant="ghost" size="icon-md" render={<a href={back.href} />} aria-label={back.label} className="md:hidden">
                        <Icon icon={ArrowLeft} size="md" />
                    </Button>
                )}
                <div className="flex min-w-0 flex-1 flex-col gap-2">
                    <h1 className="text-m-title font-semibold tracking-tight text-text-primary md:text-title">{title}</h1>
                    {facts !== undefined && <div className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 text-body text-text-secondary">{facts}</div>}
                    {date}
                </div>
                {(primary !== undefined || ordered.length > 0) && (
                    <div className="flex shrink-0 items-center gap-2">
                        {ordered.length > 0 && (
                            <DropdownMenu>
                                <DropdownMenuTrigger render={<Button variant="outline" size="lg" />}>
                                    <Icon icon={Ellipsis} size="sm" />
                                    <span>{t('actions.more')}</span>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    {ordered.map((action) => (
                                        <DropdownMenuItem key={action.label} tone={action.tone} onClick={action.onSelect}>
                                            {action.label}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                        {primary !== undefined && <div className="max-md:hidden">{primary}</div>}
                    </div>
                )}
            </div>
        </header>
    );
}
