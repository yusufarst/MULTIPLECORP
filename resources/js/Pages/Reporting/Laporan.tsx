// PG-051 *Laporan* (ROUTE-051; NAV-051; SCR-47; IA §8.6; TASK-060): the stock, purchase, sales, receivable, payment,
// profit and cash-flow reports, each by its read class (RD-02–RD-06), one at a time by its address key, filtered by
// period and company, one section per company and no total across companies (D-IA-12; CS-08). "Ekspor" requests the
// report as a job (ROUTE-283 → PG-053); *Laporan Gabungan* for the Owner and *Ekspor Saya* from here (NAV-052; NAV-053).
// `[desk]`, dense, readable on the phone.
import { router } from '@inertiajs/react';

import { type MenuAction } from '@/components/page-header';
import { ReportPage, type ReportProps } from '@/features/reporting/report-view';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';

export default function Laporan(props: ReportProps) {
    const { t } = useTranslation();
    const links: MenuAction[] = [
        ...(props.can.consolidated ? [{ label: t('pages.pg_052.title'), onSelect: () => { router.visit('/laporan/gabungan'); } }] : []),
        ...(props.can.export ? [{ label: t('pages.pg_053.title'), onSelect: () => { router.visit('/laporan/ekspor'); } }] : []),
    ];

    return (
        <AppShell title={t('pages.pg_051.title')}>
            <ReportPage props={props} path="/laporan" title={t('pages.pg_051.title')} links={links} />
        </AppShell>
    );
}
