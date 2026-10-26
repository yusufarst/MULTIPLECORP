import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, LogOut, ShieldAlert, UserRound } from 'lucide-react';
import { type ReactNode, useRef, useState } from 'react';

import { GlobalSearch } from '@/components/shell/global-search';
import { AccountMenu, CreateMenu, roleLabel } from '@/components/shell/menus';
import { BottomBar, MoreDrawer, Sidebar } from '@/components/shell/navigation';
import { NoticeStrip, ShortcutsHelp } from '@/components/shell/notices';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Icon } from '@/components/ui/icon';
import { Alert } from '@/components/ui/alert';
import { Kbd } from '@/components/ui/kbd';
import { ToastViewport } from '@/components/ui/toast';
import { dateTime } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { type AccountHints, BUILT } from '@/lib/navigation';
import { type ReauthGateway, SessionGuard } from '@/lib/session-guard';
import { useShortcut } from '@/lib/shortcuts';
import { documentTitle } from '@/lib/title';
import { cn } from '@/lib/utils';

// The application shell every authenticated page sits in (DESIGN_SYSTEM PT-01, PT-02; INFORMATION_ARCHITECTURE
// §6, §8.8; NAVIGATION_CONTRACTS NV-01–NV-08; TASK-016): the skip link, the navigation, the top bar with
// "Cari atau pindai kode", "Buat" and the account menu, the notice strip, the content landmark, the phone's app
// bar, bottom bar and *Lainnya*, the toast viewport, and the session guard. It renders the server's hints — the
// account's capabilities and companies — and decides nothing (WS-07; AZ-01); it shows no business figure, count
// or company context (§6). The page names itself: its title enters the document title as "{nama layar} ·
// MultipleCorp", never a business number or name (H7-10), and its back action on the phone.

export type ShellProps = {
    /** The screen's name — the page title, never a business number or name (§11; H7-10). */
    title: string;
    /** The phone app bar's back action below a module's first page (NV-01). */
    back?: { href: string; label: string };
    /** A focused task hides the bottom bar; its own action bar takes the place (§6.2; GR-11). */
    focused?: boolean;
    children: ReactNode;
    /** The pages and routes built — the fixture gallery's own list; pages use the application's. */
    built?: ReadonlySet<string>;
    gateway?: ReauthGateway;
};

export default function AppShell({ title, back, focused = false, children, built = BUILT, gateway }: ShellProps) {
    const { t } = useTranslation();
    const page = usePage<{ auth: AccountHints }>();
    const account = page.props.auth;
    const pathname = new URL(page.url, window.location.origin).pathname;
    const [rail, setRail] = useState(false);
    const [more, setMore] = useState(false);
    const [phoneSearch, setPhoneSearch] = useState(false);
    const [help, setHelp] = useState(false);
    const [leaving, setLeaving] = useState(false);
    const searchInput = useRef<HTMLInputElement>(null);

    useShortcut({ key: 'k', ctrl: true }, () => {
        if (window.matchMedia('(min-width: 48rem)').matches) {
            searchInput.current?.focus();
        } else {
            setPhoneSearch(true);
        }
    });
    useShortcut({ key: '/', ctrl: true }, () => {
        setHelp(true);
    });
    useShortcut({ key: 'b', ctrl: true }, () => {
        setRail((value) => !value);
    });

    const signOut = built.has('ROUTE-108') ? () => {
        setLeaving(true);
    } : undefined;

    return (
        <SessionGuard gateway={gateway}>
            <Head title={documentTitle(title)} />
            <a
                href="#konten"
                className="sr-only z-(--z-skip) rounded-md bg-surface px-4 py-2 text-body font-semibold text-text-primary shadow-pop focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
            >
                {t('shell.skip')}
            </a>
            <div className="flex min-h-dvh bg-canvas">
                <Sidebar
                    account={account}
                    pathname={pathname}
                    rail={rail}
                    onToggle={() => {
                        setRail((value) => !value);
                    }}
                built={built} />
                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="sticky top-0 z-(--z-bar) flex h-appbar items-center gap-2 border-b border-border-subtle bg-surface px-m-gutter md:h-header md:gap-3 md:px-gutter">
                        {back !== undefined && (
                            <Button variant="ghost" size="icon-md" render={<Link href={back.href} />} aria-label={back.label} className="md:hidden">
                                <Icon icon={ArrowLeft} size="md" />
                            </Button>
                        )}
                        <p className="min-w-0 flex-1 truncate text-m-heading font-semibold text-text-primary md:hidden">{title}</p>
                        <div className="min-w-0 flex-1 max-md:hidden md:max-w-search">
                            <GlobalSearch account={account} built={built} inputRef={searchInput} />
                        </div>
                        <Kbd className="max-lg:hidden">{t('shell.search_kbd')}</Kbd>
                        <div className="ml-auto flex shrink-0 items-center gap-2">
                            <div className="max-md:hidden">
                                <CreateMenu account={account} built={built} />
                            </div>
                            <AccountMenu account={account} built={built} onSignOut={signOut} />
                        </div>
                    </header>
                    <NoticeStrip />
                    <main id="konten" tabIndex={-1} className={cn('flex min-w-0 flex-1 flex-col px-m-gutter py-4 outline-none md:px-gutter md:py-6', !focused && 'max-md:pb-[calc(var(--m-bottom-nav-h)+var(--safe-bottom)+var(--space-4))]')}>
                        <AccountNotice />
                        {children}
                    </main>
                </div>
            </div>
            {!focused && (
                <BottomBar
                    account={account}
                    pathname={pathname}
                    onSearch={() => {
                        setPhoneSearch(true);
                    }}
                    onMore={() => {
                        setMore(true);
                    }}
                    built={built}
                />
            )}
            <MoreDrawer
                account={account}
                pathname={pathname}
                open={more}
                onOpenChange={setMore}
                built={built}
                accountItems={
                    <div className="flex flex-col gap-2">
                        <p className="flex flex-col">
                            <span className="text-body font-semibold text-text-primary">{account.name}</span>
                            <span className="text-caption text-text-tertiary">{t(roleLabel(account))}</span>
                        </p>
                        {built.has('PG-065') && (
                            <Button variant="ghost" size="lg" className="justify-start" render={<Link href="/akun" />}>
                                <Icon icon={UserRound} size="sm" />
                                {t('shell.account.my_account')}
                            </Button>
                        )}
                        {signOut !== undefined && (
                            <Button variant="danger" size="lg" className="justify-start" onClick={signOut}>
                                <Icon icon={LogOut} size="sm" />
                                {t('shell.account.logout')}
                            </Button>
                        )}
                    </div>
                }
            />
            <Dialog open={phoneSearch} onOpenChange={setPhoneSearch}>
                <DialogContent phone="full" closeLabel={t('actions.close')} className="md:hidden">
                    <DialogHeader>
                        <DialogTitle>{t('shell.nav.cari')}</DialogTitle>
                    </DialogHeader>
                    <GlobalSearch account={account} built={built} />
                </DialogContent>
            </Dialog>
            <ShortcutsHelp open={help} onOpenChange={setHelp} />
            <UnsentInputDialog
                open={leaving}
                kind="logout"
                onStay={() => {
                    setLeaving(false);
                }}
                onLeave={() => {
                    setLeaving(false);
                    // ROUTE-108, then a full document load of sign-in with nothing of the page kept (AU-06; IP-11).
                    void fetch('/keluar', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).finally(() => {
                        router.clearHistory();
                        window.location.assign('/masuk');
                    });
                }}
            />
            <ToastViewport closeLabel={t('toast.close')} />
        </SessionGuard>
    );
}

/** MSG-29 at the first sign-in after a credential or factor event of the account, naming it and its time, once — on the page
    the sign-in lands on (SECURITY AU-13; H7-14; H7-18). */
function AccountNotice() {
    const { t } = useTranslation();
    const notice = (usePage().flash as { account_notice?: { kind?: unknown; at?: unknown } }).account_notice;

    if (notice === undefined || typeof notice.kind !== 'string' || typeof notice.at !== 'string') {
        return null;
    }

    return (
        <div className="mb-4">
            <Alert tone="warning" icon={ShieldAlert} title={t('messages.msg_29_event', { peristiwa: t(`account.events.${notice.kind}` as TranslationKey), waktu: dateTime(notice.at) })}>
                {t('messages.msg_29_not_you')}
            </Alert>
        </div>
    );
}
