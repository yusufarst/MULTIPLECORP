import { Link, usePage } from '@inertiajs/react';

import { useTranslation } from '@/lib/i18n';
import type { AccountHints } from '@/lib/navigation';
import { cn } from '@/lib/utils';

// *Keuangan*'s built pages side by side (IA §5.3; NAV-041–NAV-050): *Invoice*, *Piutang*, *Pembayaran* and, to a holder of `settlement.record`, *Potongan* and of `bank_statement.record`, *Mutasi Bank*, the current one marked with `aria-current`. The shell's menu opens the first;
// an entry the account may not open is absent (NV-08), the server deciding.
export function FinanceNav({ current }: { current: 'invoices' | 'receivables' | 'payments' | 'deductions' | 'bank' }) {
    const { t } = useTranslation();
    const { auth } = usePage<{ auth: AccountHints }>().props;
    const entries = [
        { key: 'invoices', href: '/keuangan/invoice', label: t('pages.pg_041.title') },
        { key: 'receivables', href: '/keuangan/piutang', label: t('pages.pg_044.title') },
        { key: 'payments', href: '/keuangan/pembayaran', label: t('pages.pg_045.title') },
        ...(auth.capabilities.includes('settlement.record') ? [{ key: 'deductions', href: '/keuangan/potongan', label: t('pages.pg_047.title') }] : []),
        ...(auth.capabilities.includes('bank_statement.record') ? [{ key: 'bank', href: '/keuangan/mutasi-bank', label: t('pages.pg_050.title') }] : []),
    ];

    return (
        <nav aria-label={t('payments.nav_label')} className="flex min-w-0 flex-wrap gap-1 border-b border-border-subtle">
            {entries.map((entry) => (
                <Link
                    key={entry.key}
                    href={entry.href}
                    aria-current={entry.key === current ? 'page' : undefined}
                    className={cn('inline-flex min-h-touch items-center border-b-2 px-3 text-body', entry.key === current ? 'border-primary font-semibold text-text-primary' : 'border-transparent text-text-secondary hover:text-text-primary')}
                >
                    {entry.label}
                </Link>
            ))}
        </nav>
    );
}
