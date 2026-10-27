import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut, Plus, UserRound } from 'lucide-react';

import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuGroup, DropdownMenuGroupLabel, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Icon } from '@/components/ui/icon';
import { useTranslation } from '@/lib/i18n';
import { type AccountHints, BUILT, CREATE, isOwner, present } from '@/lib/navigation';

// The top bar's two menus (INFORMATION_ARCHITECTURE §5.4, §10; INT-003, INT-004; PT-01): "Buat" with the items
// the account's capabilities allow — absent when none is, never disabled —, and the account menu with the
// account's name and role, *Akun Saya*, *Bahasa tampilan* and *Keluar*, each present once its page or route is
// built (RC-06; PG-065 TASK-023, ROUTE-108 TASK-017).

export function CreateMenu({ account, built = BUILT }: { account: AccountHints; built?: ReadonlySet<string> }) {
    const { t } = useTranslation();
    const items = present(CREATE, account, built);

    if (items.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="accent" size="header" />}>
                <Icon icon={Plus} size="sm" />
                {t('shell.create')}
                <Icon icon={ChevronDown} size="sm" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {items.map((item) => (
                    <DropdownMenuItem key={item.key} render={<Link href={item.href} />}>
                        <Icon icon={item.icon} size="sm" />
                        {t(item.label)}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function roleLabel(account: AccountHints): 'glossary.gl_031' | 'glossary.gl_032' {
    return isOwner(account) ? 'glossary.gl_031' : 'glossary.gl_032';
}

export function AccountMenu({ account, built = BUILT, onSignOut }: { account: AccountHints; built?: ReadonlySet<string>; onSignOut?: () => void }) {
    const { t } = useTranslation();
    const name = account.name ?? '';
    const ownPage = built.has('PG-065');
    const signOut = built.has('ROUTE-108') && onSignOut !== undefined;
    // *Bahasa tampilan* (ROUTE-118; INT-002; RB-10): the account's own language, kept and applied at once.
    const language = built.has('ROUTE-118');
    const locale = (usePage().props as { locale?: string }).locale;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" size="header" className="gap-2 px-2" />}>
                <Avatar initial={name.slice(0, 1).toUpperCase()} />
                <span className="max-lg:sr-only">{name}</span>
                <span className="sr-only">{t('shell.account.menu')}</span>
                <Icon icon={ChevronDown} size="sm" className="max-lg:hidden" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-56">
                <DropdownMenuGroup>
                    <DropdownMenuGroupLabel className="flex flex-col gap-0.5 normal-case">
                        <span className="text-body font-semibold text-text-primary">{name}</span>
                        <span className="text-caption text-text-tertiary">{t(roleLabel(account))}</span>
                    </DropdownMenuGroupLabel>
                </DropdownMenuGroup>
                {(ownPage || signOut) && <DropdownMenuSeparator />}
                {ownPage && (
                    <DropdownMenuItem render={<Link href="/akun" />}>
                        <Icon icon={UserRound} size="sm" />
                        {t('shell.account.my_account')}
                    </DropdownMenuItem>
                )}
                {language && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuGroupLabel>{t('account.locale.label')}</DropdownMenuGroupLabel>
                            {(['id', 'en'] as const).map((choice) => (
                                <DropdownMenuCheckboxItem
                                    key={choice}
                                    checked={locale === choice}
                                    onCheckedChange={() => {
                                        router.put('/akun/bahasa', { locale: choice }, { preserveScroll: true, preserveState: true });
                                    }}
                                >
                                    {t(choice === 'id' ? 'account.locale.id' : 'account.locale.en')}
                                </DropdownMenuCheckboxItem>
                            ))}
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator />
                    </>
                )}
                {signOut && (
                    <DropdownMenuItem tone="danger" onClick={onSignOut}>
                        <Icon icon={LogOut} size="sm" />
                        {t('shell.account.logout')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
