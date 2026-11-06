// PG-007 *Pekerjaan* (ROUTE-007; NAV-007; SCR-06; D-IA-08; `DIR-081` OB-03; INFORMATION_ARCHITECTURE §9; TASK-058):
// every account's work grouped by what has to happen next — *Barang: terima, keluarkan, kirim*, *Dokumen dan kasus*,
// *Tagihan dan pembayaran* (only with `finance.view`), *Penyelesaian proyek* —, each group present only with its read
// class, each a deferred group with its own spinner and error (PT-19); the "Berikutnya" card first on the phone; a view
// over the companies of the scope, counts per company and never summed (CS-08; PF-15). An account without a company
// grant sees MSG-38 and nothing above S0 (RG-04). No command.
import { Link, router, usePage } from '@inertiajs/react';

import { PageHeader } from '@/components/page-header';
import { TextLink } from '@/components/record-blocks';
import { RegionEmpty } from '@/components/region-state';
import { failed, type Failed, Refresh, type SignalRead, SignalSection } from '@/features/home/signals';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Group = 'goods' | 'documents' | 'billing' | 'completion';
type Props = {
    frame: { ungranted: boolean; groups: Record<Group, boolean>; views: { id: string; code: string }[] };
} & Partial<Record<Group, SignalRead[] | Failed>>;

const TITLES: Record<Group, TranslationKey> = {
    goods: 'pages.pg_007.group_goods',
    documents: 'pages.pg_007.group_docs',
    billing: 'pages.pg_007.group_billing',
    completion: 'pages.pg_007.group_completion',
};

/** "Berikutnya": the first row of the first group that has one — what to do next (IA §9; R1 f). */
function Next({ groups }: { groups: (SignalRead[] | Failed | undefined)[] }) {
    const { t } = useTranslation();

    for (const group of groups) {
        if (group === undefined || failed(group)) {
            continue;
        }

        for (const signal of group) {
            const row = signal.rows[0];

            if (row !== undefined && row.href !== null) {
                return (
                    <section aria-label={t('pages.pg_007.next_tpl', { v: '' })} data-slot="next" className="rounded-lg border border-border-subtle bg-surface p-4 md:hidden">
                        <TextLink href={row.href}>{t('pages.pg_007.next_tpl', { v: row.ref === null ? row.subject : `${row.subject} · ${row.ref}` })}</TextLink>
                    </section>
                );
            }
        }
    }

    return null;
}

export default function Pekerjaan() {
    const { t } = useTranslation();
    const page = usePage<Props>();
    const { frame } = page.props;
    const shown = (Object.keys(TITLES) as Group[]).filter((group) => frame.groups[group] && !frame.ungranted);
    const chosen = new URL(page.url, 'http://localhost').searchParams.get('perusahaan');

    return (
        <AppShell title={t('pages.pg_007.title')}>
            <div className="flex min-w-0 flex-col gap-8">
                <PageHeader title={t('pages.pg_007.title')} facts={shown.length > 0 ? <Refresh groups={shown} /> : undefined} />
                {frame.ungranted ? (
                    <RegionEmpty text={t('messages.msg_38')} />
                ) : (
                    <>
                        {frame.views.length > 1 && (
                            <nav aria-label={t('pages.pg_007.count_label')} className="flex min-w-0 flex-wrap gap-2">
                                {[{ id: null, code: t('pages.pg_008.all_companies') }, ...frame.views].map((view) => {
                                    const current = view.id === chosen;

                                    return (
                                        <Link
                                            key={view.id ?? 'semua'}
                                            href={view.id === null ? '/pekerjaan' : `/pekerjaan?perusahaan=${view.id}`}
                                            aria-current={current ? 'page' : undefined}
                                            className={cn('inline-flex min-h-touch items-center rounded-md px-3 text-label outline-none focus-visible:outline-2 focus-visible:outline-focus', current ? 'bg-surface-sunken font-semibold text-text-primary' : 'text-text-secondary hover:text-text-primary')}
                                        >
                                            {view.code}
                                        </Link>
                                    );
                                })}
                            </nav>
                        )}
                        <Next groups={shown.map((group) => page.props[group])} />
                        {shown.map((group) => (
                            <section key={group} aria-label={t(TITLES[group])} className="flex min-w-0 flex-col gap-3">
                                <h2 className="text-heading-sm font-semibold text-text-primary">{t(TITLES[group])}</h2>
                                <SignalSection data={page.props[group]} reload={() => { router.reload({ only: [group] }); }} />
                            </section>
                        ))}
                    </>
                )}
            </div>
        </AppShell>
    );
}
