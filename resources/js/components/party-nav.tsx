import { Link } from '@inertiajs/react';

import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

// *Data Master*'s built pages side by side (NAV-054, NAV-055; IA §8.6): *Klien* and *Pemasok*, the current one marked
// with `aria-current` — a page's own module navigation, the shell's menu opening the first (§5.3).
export function DataMasterNav({ current }: { current: 'clients' | 'suppliers' }) {
    const { t } = useTranslation();
    const entries = [
        { key: 'clients', href: '/data-master/klien', label: t('pages.pg_054.title') },
        { key: 'suppliers', href: '/data-master/pemasok', label: t('pages.pg_055.title') },
    ] as const;

    return (
        <nav aria-label={t('parties.nav_label')} className="flex min-w-0 gap-1 border-b border-border-subtle">
            {entries.map((entry) => (
                <Link
                    key={entry.key}
                    href={entry.href}
                    aria-current={entry.key === current ? 'page' : undefined}
                    className={cn(
                        'inline-flex min-h-touch items-center border-b-2 px-3 text-body',
                        entry.key === current ? 'border-primary font-semibold text-text-primary' : 'border-transparent text-text-secondary hover:text-text-primary',
                    )}
                >
                    {entry.label}
                </Link>
            ))}
        </nav>
    );
}
