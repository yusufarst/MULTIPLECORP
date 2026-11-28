import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronsLeft, ChevronsRight, Menu, Search } from 'lucide-react';
import { type MouseEvent, type ReactNode, useCallback, useId, useState, useSyncExternalStore } from 'react';

import { Button } from '@/components/ui/button';
import { Collapsible, CollapsiblePanel, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Icon } from '@/components/ui/icon';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useTranslation } from '@/lib/i18n';
import { type AccountHints, bottomEntries, BUILT, currentKey, currentLeaf, type Entry, type Leaf, navigationTree, primaryEntries, workEntries } from '@/lib/navigation';
import { APPLICATION } from '@/lib/title';
import { cn } from '@/lib/utils';

// APPR-042's pilot shell (CR-002 K-1, K-4; blueprint C; DIR-134 E7 — the hierarchy and the group labels are candidates
// validated with novice users): from 1,025 px the 248 px sidebar — the persona's primary entries, then three labelled
// groups whose modules open into their own pages, the rarely used pages after a rule; the module holding the current
// page opens by itself. From 768 to 1,024 px, and on the desktop with Ctrl B (KB-03), the 80 px rail of the labelled
// primary entries and "Menu", which opens the same tree as a drawer. Below 768 px the bottom bar — the persona's two
// entries, "Cari" and "Menu" — and "Menu" the full-screen tree, "Kerja" first and "Akun" last. The current page is
// marked by weight, the cobalt rule and the tint, never by colour alone, with `aria-current="page"`; no entry carries
// a badge or a count (D-IA-06). Every route and capability is unchanged: the tree only groups what the account opens.

export const PHONE = '(width < 48rem)';
export const TABLET = '(width >= 48rem) and (width < 64.0625rem)';

/** Whether the viewport matches a media query, kept current as the window changes. */
export function useMediaQuery(query: string): boolean {
    const subscribe = useCallback(
        (notify: () => void) => {
            const list = window.matchMedia(query);
            list.addEventListener('change', notify);

            return () => {
                list.removeEventListener('change', notify);
            };
        },
        [query],
    );

    return useSyncExternalStore(subscribe, () => window.matchMedia(query).matches);
}

type Tree = ReturnType<typeof navigationTree>;

const leavesOfTree = (tree: Tree): Leaf[] => tree.flatMap((group) => group.modules.flatMap((item) => item.leaves));

const focusRing = 'outline-none focus-visible:outline-2 focus-visible:outline-solid focus-visible:outline-offset-2 focus-visible:outline-focus';
const row = 'relative flex min-w-0 items-center rounded-md text-body text-text-secondary transition-colors duration-(--mc-dur-hover) hover:bg-nav-hover hover:text-text-primary active:bg-nav-press';
const selected = 'bg-sidebar-accent font-semibold text-text-primary hover:bg-sidebar-accent';
const rule = 'before:absolute before:inset-y-(--nav-indicator-inset-y) before:left-0 before:w-(--nav-indicator-width) before:rounded-full before:bg-sidebar-primary';

function EntryLink({ entry, current }: { entry: Entry; current: boolean }) {
    const { t } = useTranslation();

    return (
        <Link href={entry.href} aria-current={current ? 'page' : undefined} className={cn(row, 'min-h-(--nav-item-height) gap-3 px-(--nav-icon-inset) font-medium', focusRing, current && cn(selected, rule))}>
            <Icon icon={entry.icon} size="md" />
            {/* A label longer than the sidebar's line wraps to a second line, never cut (LOCALIZATION §3.7; TASK-067 A). */}
            <span className="min-w-0 py-1 leading-tight break-words">{t(entry.label)}</span>
        </Link>
    );
}

function EntryList({ entries, pathname, labelledBy }: { entries: Entry[]; pathname: string; labelledBy?: string }) {
    const current = currentKey(entries, pathname);

    return (
        <ul role="list" aria-labelledby={labelledBy} className="flex flex-col gap-(--nav-item-gap)">
            {entries.map((entry) => (
                <li key={entry.key}>
                    <EntryLink entry={entry} current={entry.key === current} />
                </li>
            ))}
        </ul>
    );
}

function GroupLabel({ id, children }: { id: string; children: ReactNode }) {
    return (
        <p id={id} className="px-(--nav-icon-inset) pb-1 text-caption font-semibold text-text-tertiary">
            {children}
        </p>
    );
}

/** A module's pages under a guide line, the current one on the cobalt rule; the rarely used pages after a hairline. */
function ModuleDisclosure({ module, leaves, current }: { module: Entry; leaves: Leaf[]; current: string | null }) {
    const { t } = useTranslation();
    const holdsCurrent = leaves.some((leaf) => leaf.pg === current);
    const [open, setOpen] = useState(holdsCurrent);
    const firstRare = leaves.findIndex((leaf) => leaf.rare === true);

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <CollapsibleTrigger className={cn(row, 'group/module min-h-(--nav-item-height) w-full gap-3 px-(--nav-icon-inset) text-left font-medium', focusRing, holdsCurrent && 'font-semibold text-text-primary')}>
                <Icon icon={module.icon} size="md" />
                <span className="min-w-0 flex-1 py-1 leading-tight break-words">{t(module.label)}</span>
                <Icon icon={ChevronDown} size="sm" className="shrink-0 text-text-tertiary transition-transform duration-(--mc-dur-fast) group-data-[panel-open]/module:rotate-180 motion-reduce:transition-none" />
            </CollapsibleTrigger>
            {/* A closed module's pages stay findable with the browser's find (`hidden="until-found"`), which opens it. */}
            <CollapsiblePanel hiddenUntilFound>
                <ul role="list" className="ml-(--nav-leaf-inset) flex flex-col py-1">
                    {leaves.map((leaf, index) => (
                        <li key={leaf.pg} className={cn(index === firstRare && index > 0 && 'mt-1 border-t border-border pt-1')}>
                            <Link
                                href={leaf.href}
                                aria-current={leaf.pg === current ? 'page' : undefined}
                                className={cn(
                                    row,
                                    'min-h-10 rounded-l-none border-l-2 border-border pr-2 pl-5 pointer-coarse:min-h-(--nav-item-height)',
                                    focusRing,
                                    leaf.pg === current && cn(selected, 'border-sidebar-primary'),
                                )}
                            >
                                <span className="min-w-0 py-1 leading-tight break-words">{t(leaf.label)}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
            </CollapsiblePanel>
        </Collapsible>
    );
}

/** The labelled groups, their modules and pages (blueprint C); a group or module the account opens nothing in is absent. */
function ModuleTree({ tree, pathname }: { tree: Tree; pathname: string }) {
    const { t } = useTranslation();
    const id = useId();
    const current = currentLeaf(leavesOfTree(tree), pathname);

    return (
        <div className="flex flex-col gap-5">
            {tree.map((group) => (
                <div key={group.key} className="flex flex-col">
                    <GroupLabel id={`${id}-${group.key}`}>{t(group.label)}</GroupLabel>
                    <ul role="list" aria-labelledby={`${id}-${group.key}`} className="flex flex-col gap-(--nav-item-gap)">
                        {group.modules.map(({ module, leaves }) => (
                            <li key={module.key}>
                                <ModuleDisclosure module={module} leaves={leaves} current={current} />
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </div>
    );
}

const railItem = 'group/rail flex min-h-16 w-full flex-col items-center justify-center gap-1 rounded-lg px-0 py-2 text-caption font-medium text-text-secondary transition-colors duration-(--mc-dur-hover) hover:text-text-primary';
const railPill = 'inline-flex h-8 w-12 items-center justify-center rounded-full transition-colors duration-(--mc-dur-hover) group-hover/rail:bg-nav-hover group-active/rail:bg-nav-press';

function RailLabel({ children }: { children: ReactNode }) {
    return <span className="max-w-full text-center leading-tight break-words">{children}</span>;
}

/** The desktop sidebar, or the rail at the tablet band and after Ctrl B; the state is the shell's own, never stored (§6.1). */
export function Sidebar({
    account,
    pathname,
    rail,
    onToggle,
    onMenu,
    menuOpen,
    built = BUILT,
}: {
    account: AccountHints;
    pathname: string;
    rail: boolean;
    onToggle: () => void;
    onMenu: () => void;
    menuOpen: boolean;
    built?: ReadonlySet<string>;
}) {
    const { t } = useTranslation();
    const primary = primaryEntries(account, built);
    const tree = navigationTree(account, built);

    if (rail) {
        const current = currentKey(primary, pathname);
        const inMenu = currentLeaf(leavesOfTree(tree), pathname) !== null;

        return (
            <nav aria-label={t('shell.nav.label')} data-rail className="sticky top-0 flex h-dvh w-rail shrink-0 flex-col gap-2 overflow-y-auto border-r border-sidebar-border bg-sidebar px-1 py-3 max-md:hidden">
                <ul role="list" className="flex flex-col gap-1">
                    {primary.map((entry) => (
                        <li key={entry.key}>
                            <Link href={entry.href} aria-current={entry.key === current ? 'page' : undefined} className={cn(railItem, focusRing, entry.key === current && 'font-semibold text-text-primary')}>
                                <span className={cn(railPill, entry.key === current && 'bg-sidebar-accent text-text-primary group-hover/rail:bg-sidebar-accent')}>
                                    <Icon icon={entry.icon} size="md" />
                                </span>
                                <RailLabel>{t(entry.label)}</RailLabel>
                            </Link>
                        </li>
                    ))}
                    {tree.length > 0 && (
                        <li>
                            <button type="button" aria-haspopup="dialog" aria-expanded={menuOpen} className={cn(railItem, focusRing, inMenu && 'font-semibold text-text-primary')} onClick={onMenu}>
                                <span className={cn(railPill, inMenu && 'bg-sidebar-accent text-text-primary group-hover/rail:bg-sidebar-accent')}>
                                    <Icon icon={Menu} size="md" />
                                </span>
                                <RailLabel>{t('shell.nav.menu')}</RailLabel>
                            </button>
                        </li>
                    )}
                </ul>
                <Button variant="ghost" size="icon-md" className="mt-auto self-center max-lg:hidden" aria-label={t('shell.expand')} aria-keyshortcuts="Control+B" onClick={onToggle}>
                    <Icon icon={ChevronsRight} size="sm" />
                </Button>
            </nav>
        );
    }

    return (
        <nav aria-label={t('shell.nav.label')} className="sticky top-0 flex h-dvh w-(--sidebar-width) shrink-0 flex-col overflow-y-auto border-r border-sidebar-border bg-sidebar max-md:hidden">
            <div className="flex h-header shrink-0 items-center border-b border-sidebar-border px-(--sidebar-inset-x)">
                <span className="px-(--nav-icon-inset) text-heading font-semibold tracking-tight text-text-primary">{APPLICATION}</span>
            </div>
            <div className="flex flex-1 flex-col gap-6 px-(--sidebar-inset-x) py-4">
                <EntryList entries={primary} pathname={pathname} />
                {tree.length > 0 && <ModuleTree tree={tree} pathname={pathname} />}
                <Button variant="ghost" size="icon-md" className="mt-auto self-end max-lg:hidden" aria-label={t('shell.collapse')} aria-keyshortcuts="Control+B" onClick={onToggle}>
                    <Icon icon={ChevronsLeft} size="sm" />
                </Button>
            </div>
        </nav>
    );
}

/** The phone's bottom bar: the persona's two entries, "Cari" and "Menu"; hidden during a focused task (§6.2). */
export function BottomBar({
    account,
    pathname,
    onSearch,
    onMenu,
    menuOpen,
    built = BUILT,
}: {
    account: AccountHints;
    pathname: string;
    onSearch: () => void;
    onMenu: () => void;
    menuOpen: boolean;
    built?: ReadonlySet<string>;
}) {
    const { t } = useTranslation();
    const entries = bottomEntries(account, built);
    const current = currentKey(entries, pathname);
    // "Menu" holds the page when the page is one of its own: a module's page or a work entry the bar leaves out.
    const inMenu = currentLeaf(leavesOfTree(navigationTree(account, built)), pathname) !== null || currentKey(workEntries(account, built), pathname) !== null;
    const item = 'flex min-h-bottom-nav min-w-bottom-nav flex-1 flex-col items-center justify-center gap-1 text-pill font-medium text-text-secondary outline-none focus-visible:outline-2 focus-visible:outline-solid focus-visible:-outline-offset-2 focus-visible:outline-focus';
    const pill = 'inline-flex h-8 w-14 items-center justify-center rounded-full';

    return (
        <nav aria-label={t('shell.nav.label')} data-print="hide" className="fixed inset-x-0 bottom-0 z-(--z-bar) flex border-t border-border bg-surface pb-(--safe-bottom) md:hidden">
            {entries.map((entry) => (
                <Link key={entry.key} href={entry.href} aria-current={entry.key === current ? 'page' : undefined} className={cn(item, entry.key === current && 'font-semibold text-text-primary')}>
                    <span className={cn(pill, entry.key === current && 'bg-sidebar-accent')}>
                        <Icon icon={entry.icon} size="nav" />
                    </span>
                    {t(entry.label)}
                </Link>
            ))}
            <button type="button" className={item} onClick={onSearch}>
                <span className={pill}>
                    <Icon icon={Search} size="nav" />
                </span>
                {t('shell.nav.cari')}
            </button>
            <button type="button" aria-haspopup="dialog" aria-expanded={menuOpen} className={cn(item, inMenu && 'font-semibold text-text-primary')} onClick={onMenu}>
                <span className={cn(pill, inMenu && 'bg-sidebar-accent')}>
                    <Icon icon={Menu} size="nav" />
                </span>
                {t('shell.nav.menu')}
            </button>
        </nav>
    );
}

/**
 * "Menu": full-screen on the phone — "Kerja", the groups and "Akun" (§6.2; GR-11) —, a drawer from the rail's side at the
 * tablet band and after Ctrl B — the primary entries and the groups. Opening a page closes it.
 */
export function NavigationDrawer({
    account,
    pathname,
    open,
    onOpenChange,
    phone,
    accountItems,
    built = BUILT,
}: {
    account: AccountHints;
    pathname: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    phone: boolean;
    accountItems: ReactNode;
    built?: ReadonlySet<string>;
}) {
    const { t } = useTranslation();
    const id = useId();
    const top = phone ? workEntries(account, built) : primaryEntries(account, built);
    const tree = navigationTree(account, built);
    const closeOnPage = (event: MouseEvent) => {
        const plain = event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;

        if (plain && event.target instanceof Element && event.target.closest('a[href]') !== null) {
            onOpenChange(false);
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent closeLabel={t('shell.drawer.close')} aria-label={t('shell.drawer.label')} className="gap-5 md:right-auto md:left-0 md:max-w-nav-drawer md:p-4 md:shadow-drawer-start md:data-starting-style:-translate-x-6">
                <SheetHeader>
                    <SheetTitle>{t('shell.nav.menu')}</SheetTitle>
                </SheetHeader>
                {/* Delegated from the links, which stay the controls: a page opened from the menu closes it; a new tab or window leaves it open. */}
                <nav aria-label={t('shell.drawer.nav')} className="flex flex-col gap-6" onClick={closeOnPage}>
                    {top.length > 0 && (
                        <div className="flex flex-col">
                            {phone && <GroupLabel id={`${id}-work`}>{t('glossary.gl_195')}</GroupLabel>}
                            <EntryList entries={top} pathname={pathname} labelledBy={phone ? `${id}-work` : undefined} />
                        </div>
                    )}
                    {tree.length > 0 && <ModuleTree tree={tree} pathname={pathname} />}
                    {phone && (
                        <section aria-labelledby={`${id}-account`} className="flex flex-col gap-2 border-t border-border pt-4">
                            <GroupLabel id={`${id}-account`}>{t('shell.drawer.account')}</GroupLabel>
                            {accountItems}
                        </section>
                    )}
                </nav>
            </SheetContent>
        </Sheet>
    );
}
