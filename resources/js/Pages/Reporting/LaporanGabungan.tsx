// PG-052 *Laporan Gabungan* (ROUTE-052; NAV-052; SCR-48; IA §8.6; TASK-060): the Owner's consolidated figures — each
// company's period figures and *Gabungan*, their sums (CALC-12) — and the inter-company allocation report, both sides in
// full (RD-07; J-INV-07). Entered from *Laporan* and *Beranda*'s "Rincian angka"; "Kembali" returns to its origin.
// `reports.consolidated` only (OD-14). `[desk]`, dense.
import { router } from '@inertiajs/react';
import { useState } from 'react';

import { type MenuAction } from '@/components/page-header';
import { ReportPage, type ReportProps } from '@/features/reporting/report-view';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';
import { previousPage } from '@/lib/previous-page';

export default function LaporanGabungan(props: ReportProps) {
    const { t } = useTranslation();
    // The origin read once, kept while the filters change in place (preserved state), so "Kembali" returns where PG-052 was entered (NAV-052).
    const [origin] = useState(() => { const before = previousPage(); return before === null || before.startsWith('/laporan/gabungan') ? '/laporan' : before; });
    const links: MenuAction[] = [
        { label: t('pages.pg_051.title'), onSelect: () => { router.visit('/laporan'); } },
        ...(props.can.export ? [{ label: t('pages.pg_053.title'), onSelect: () => { router.visit('/laporan/ekspor'); } }] : []),
    ];

    return (
        <AppShell title={t('pages.pg_052.title')} back={{ href: origin, label: t('dialogs.back') }}>
            <ReportPage props={props} path="/laporan/gabungan" title={t('pages.pg_052.title')} back={{ label: t('dialogs.back'), href: origin }} links={links} />
        </AppShell>
    );
}
