import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronsLeft, ChevronsRight, Menu, Search } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Collapsible, CollapsiblePanel, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Icon } from '@/components/ui/icon';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useTranslation } from '@/lib/i18n';
import { type AccountHints, bottomEntries, BUILT, currentKey, type Entry, MODULES, present, primaryEntries, workEntries } from '@/lib/navigation';
import { cn } from '@/lib/utils';

// PT-01 and PT-02 (INFORMATION_ARCHITECTURE §6; DESIGN_SYSTEM §6, §9.1, §10 "Shell", "Phone app bar and bottom
// bar"; GR-02, GR-11, GR-22): from 1,025 px the 264 px sidebar — the persona's primary entries, then the folded
// *Menu lengkap* — collapsing to the 80 px rail with Ctrl B (KB-03), the rail at the tablet band; below 768 px the
// 56 px app bar and the bottom bar of four items, each at least 60 × 60 px, *Lainnya* a drawer of *Kerja*, *Menu
// lengkap* and *Akun*. The current entry is marked by 600 weight, the lime fill and the olive rule, never colour
// alone, with `aria-current="page"`. No entry carries a badge or a count (D-IA-06).

function NavLink({ entry, current, rail }: { entry: Entry; current: boolean; rail: boolean }) {
    const { t } = useTranslation();
    const link = (
        <Link
            href={entry.href}
            aria-current={current ? 'page' : undefined}
            className={cn(
                'relative flex h-(--nav-item-height) min-w-0 items-center gap-3 rounded-md px-(--nav-icon-inset) text-body font-medium text-text-secondary outline-none transition-colors duration-(--mc-dur-hover) hover:bg-row-hover hover:text-text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                current &&
                    'bg-sidebar-accent font-semibold text-text-primary before:absolute before:inset-y-(--nav-indicator-inset-y) before:left-0 before:w-(--nav-indicator-width) before:rounded-full before:bg-sidebar-primary hover:bg-sidebar-accent',
                rail && 'justify-center px-0',
            )}
        >
            <Icon icon={entry.icon} size="md" />
            <span className={cn('min-w-0 truncate', rail && 'sr-only')}>{t(entry.label)}</span>
        </Link>
    );

    if (!rail) {
        return link;
    }

    return (
        <Tooltip>
            <TooltipTrigger render={link} />
            <TooltipContent side="right">{t(entry.label)}</TooltipContent>
        </Tooltip>
    );
}

function EntryList({ entries, pathname, rail = false }: { entries: Entry[]; pathname: string; rail?: boolean }) {
    const current = currentKey(entries, pathname);

    return (
        <ul role="list" className="flex flex-col gap-(--nav-item-gap)">
            {entries.map((entry) => (
                <li key={entry.key}>
                    <NavLink entry={entry} current={entry.key === current} rail={rail} />
                </li>
            ))}
        </ul>
    );
}

/** The desktop sidebar and its rail; the expanded or rail state is the shell's own, never stored (D-DS; §6.1). */
export function Sidebar({ account, pathname, rail, onToggle, built = BUILT }: { account: AccountHints; pathname: string; rail: boolean; onToggle: () => void; built?: ReadonlySet<string> }) {
    const { t } = useTranslation();
    const primary = primaryEntries(account, built);
    const modules = present(MODULES, account, built);
    const [open, setOpen] = useState(currentKey(modules, pathname) !== null);

    return (
        <nav
            aria-label={t('shell.nav.label')}
            data-rail={rail || undefined}
            className={cn(
                'sticky top-0 flex h-dvh shrink-0 flex-col gap-4 overflow-y-auto border-r border-sidebar-border bg-sidebar py-4 max-md:hidden',
                rail ? 'w-20 px-3' : 'w-(--sidebar-width) px-(--sidebar-inset-x)',
            )}
        >
            <EntryList entries={primary} pathname={pathname} rail={rail} />
            {modules.length > 0 &&
                (rail ? (
                    <EntryList entries={modules} pathname={pathname} rail />
                ) : (
                    <Collapsible open={open} onOpenChange={setOpen}>
                        <CollapsibleTrigger className="group/menu flex h-(--nav-item-height) w-full items-center justify-between rounded-md px-(--nav-icon-inset) text-label font-semibold text-text-tertiary outline-none hover:bg-row-hover focus-visible:outline-2 focus-visible:outline-focus">
                            {t('glossary.gl_194')}
                            <Icon icon={ChevronDown} size="sm" className="transition-transform duration-(--mc-dur-fast) group-data-[panel-open]/menu:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsiblePanel className="pt-1">
                            <EntryList entries={modules} pathname={pathname} />
                        </CollapsiblePanel>
                    </Collapsible>
                ))}
            <Button variant="ghost" size="icon-md" className={cn('mt-auto max-lg:hidden', !rail && 'self-end')} aria-label={t(rail ? 'shell.expand' : 'shell.collapse')} aria-keyshortcuts="Control+B" onClick={onToggle}>
                <Icon icon={rail ? ChevronsRight : ChevronsLeft} size="sm" />
            </Button>
        </nav>
    );
}

/** The phone's bottom bar: the persona's entries, *Cari* and *Lainnya*; hidden during a focused task (§6.2). */
export function BottomBar({
    account,
    pathname,
    onSearch,
    onMore,
    built = BUILT,
}: {
    account: AccountHints;
    pathname: string;
    onSearch: () => void;
    onMore: () => void;
    built?: ReadonlySet<string>;
}) {
    const { t } = useTranslation();
    const entries = bottomEntries(account, built);
    const current = currentKey(entries, pathname);
    const item = 'flex min-h-bottom-nav min-w-bottom-nav flex-1 flex-col items-center justify-center gap-1 text-pill font-medium text-text-secondary outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-focus';

    return (
        <nav aria-label={t('shell.nav.label')} data-print="hide" className="fixed inset-x-0 bottom-0 z-(--z-bar) flex border-t border-border-subtle bg-surface pb-(--safe-bottom) md:hidden">
            {entries.map((entry) => (
                <Link key={entry.key} href={entry.href} aria-current={entry.key === current ? 'page' : undefined} className={cn(item, entry.key === current && 'font-semibold text-text-primary')}>
                    <span className={cn('inline-flex h-control-sm w-14 items-center justify-center rounded-full', entry.key === current && 'bg-sidebar-accent')}>
                        <Icon icon={entry.icon} size="nav" />
                    </span>
                    {t(entry.label)}
                </Link>
            ))}
            <button type="button" className={item} onClick={onSearch}>
                <span className="inline-flex h-control-sm w-14 items-center justify-center rounded-full">
                    <Icon icon={Search} size="nav" />
                </span>
                {t('shell.nav.cari')}
            </button>
            <button type="button" className={item} onClick={onMore}>
                <span className="inline-flex h-control-sm w-14 items-center justify-center rounded-full">
                    <Icon icon={Menu} size="nav" />
                </span>
                {t('shell.nav.lainnya')}
            </button>
        </nav>
    );
}

/** *Lainnya*: *Kerja*, *Menu lengkap* and *Akun*, grouped and ordered as on the desktop (§6.2; GR-11). */
export function MoreDrawer({
    account,
    pathname,
    open,
    onOpenChange,
    accountItems,
    built = BUILT,
}: {
    account: AccountHints;
    pathname: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    accountItems: React.ReactNode;
    built?: ReadonlySet<string>;
}) {
    const { t } = useTranslation();
    const work = workEntries(account, built);
    const modules = present(MODULES, account, built);
    const group = (title: string, body: React.ReactNode) => (
        <section className="flex flex-col gap-2">
            <h2 className="text-label font-semibold text-text-tertiary">{title}</h2>
            {body}
        </section>
    );

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent closeLabel={t('shell.drawer.close')} aria-label={t('shell.drawer.label')}>
                <SheetHeader>
                    <SheetTitle>{t('shell.nav.lainnya')}</SheetTitle>
                </SheetHeader>
                <nav aria-label={t('shell.drawer.nav')} className="flex flex-col gap-6">
                    {work.length > 0 && group(t('glossary.gl_195'), <EntryList entries={work} pathname={pathname} />)}
                    {modules.length > 0 && group(t('glossary.gl_194'), <EntryList entries={modules} pathname={pathname} />)}
                    {group(t('shell.drawer.account'), accountItems)}
                </nav>
            </SheetContent>
        </Sheet>
    );
}
