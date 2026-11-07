// PG-053 *Ekspor Saya* (ROUTE-053; NAV-053; SCR-49; IA §8.6; TASK-060): the requester's own export requests, newest
// first — the report, its period and companies, its format and when it was asked — with IP-12's states *Diminta*,
// *Diproses*, *Siap Diunduh*, *Gagal* and *Tidak Tersedia*. A request still owed is polled as background work no faster
// than once a minute (ROUTE-285; RB-05; PF-26); "Unduh" an ordinary browser download (ROUTE-284; PF-29); a withheld
// request offers none (RV-08; UXS-23); "Minta Lagi" asks again with the same report and filters (ROUTE-283). The tab
// and cursor in the address (IP-18); "Kembali" → PG-051. `[both]`, dense rows on the phone (PT-06).
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

import { type Answer, OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTab } from '@/components/ui/tabs';
import { useLabel } from '@/features/reporting/report-view';
import AppShell from '@/layouts/AppShell';
import { date, dateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { useStatusPoll } from '@/lib/use-status-poll';

type State = 'PENDING' | 'RUNNING' | 'READY' | 'FAILED' | 'WITHHELD';
type Row = {
    id: string;
    report: string;
    format: 'xlsx' | 'pdf';
    from: string | null;
    to: string | null;
    companies: string[];
    state: State;
    requested_at: string;
    expires_at: string | null;
    href: string | null;
    again: Record<string, string>;
};
type Props = { rows: Row[]; more: string | null; tab: string; kept_hours: number };

const TABS = ['semua', 'berjalan', 'siap', 'gagal'] as const;
const FACE = { PENDING: 'REQUESTED', RUNNING: 'RUNNING', READY: 'READY', FAILED: 'FAILED', WITHHELD: 'WITHHELD' } as const;

function Status({ row, onSettled }: { row: Row; onSettled: () => void }) {
    const owed = row.state === 'PENDING' || row.state === 'RUNNING';

    return owed ? <Polled row={row} onSettled={onSettled} /> : <StatusBadge family="export" state={FACE[row.state]} size="sm" />;
}

/** A request still owed, polled through ROUTE-285 until it settles, then the list read again for its download. */
function Polled({ row, onSettled }: { row: Row; onSettled: () => void }) {
    const { status } = useStatusPoll('ekspor', row.id);
    const state = (status?.state ?? row.state) as State;
    const settled = state !== 'PENDING' && state !== 'RUNNING';

    useEffect(() => {
        if (settled) onSettled();
    }, [settled, onSettled]);

    return <StatusBadge family="export" state={FACE[state]} size="sm" />;
}

export default function EksporSaya({ rows, more, tab }: Props) {
    const { t } = useTranslation();
    const label = useLabel();
    const [answer, setAnswer] = useState<Answer | null>(null);
    const reload = useCallback(() => {
        router.reload({ only: ['rows', 'more'] });
    }, []);
    const visit = (patch: Record<string, string | null>) => {
        const query = Object.fromEntries(Object.entries({ tab: tab === 'semua' ? null : tab, ...patch }).filter(([, value]) => value !== null)) as Record<string, string>;
        router.get(`/laporan/ekspor${Object.keys(query).length > 0 ? `?${new URLSearchParams(query).toString()}` : ''}`, {}, { preserveScroll: true });
    };
    const again = (row: Row) => {
        setAnswer(null);
        router.post('/laporan/ekspor', row.again, {
            preserveScroll: true,
            onSuccess: (page) => {
                const given = (page.flash as { answer?: { text?: unknown } }).answer;

                if (given !== undefined && typeof given.text === 'string') {
                    setAnswer({ kind: 'REJECTED', text: given.text });
                }
            },
        });
    };

    return (
        <AppShell title={t('pages.pg_053.title')} back={{ href: '/laporan', label: t('dialogs.back') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_053.title')} back={{ href: '/laporan', label: t('dialogs.back') }} crumbs={[{ label: t('pages.pg_051.title'), href: '/laporan' }]} actions={[{ label: t('states.refresh'), onSelect: reload }]} />
                {answer !== null && <OutcomeMessage answer={answer} />}
                <div className="min-w-0 overflow-x-auto">
                    <Tabs value={tab} onValueChange={(value) => { visit({ tab: String(value) === 'semua' ? null : String(value) }); }}>
                        <TabsList>
                            {TABS.map((each) => <TabsTab key={each} value={each}>{label(`pages.pg_053.tab.${each}`)}</TabsTab>)}
                        </TabsList>
                    </Tabs>
                </div>
                {rows.length === 0 ? (
                    <RegionEmpty text={t('pages.pg_053.empty')} />
                ) : (
                    <ul className="flex min-w-0 flex-col divide-y divide-border-subtle" aria-label={t('pages.pg_053.title')} data-slot="exports">
                        {rows.map((row) => (
                            <li key={row.id} data-slot="export-row" data-state={row.state} className="flex min-w-0 flex-col gap-2 py-3 md:flex-row md:items-center md:gap-4">
                                <div className="flex min-w-0 flex-1 flex-col gap-1">
                                    <span className="text-body font-medium text-text-primary">
                                        {label(`pages.pg_051.report.${row.report}`)} · {row.format === 'pdf' ? 'PDF' : 'Excel'}
                                    </span>
                                    <span className="text-label text-text-secondary">
                                        {row.from !== null && row.to !== null ? `${date(row.from)} – ${date(row.to)}` : ''}
                                        {row.companies.length > 0 ? ` · ${row.companies.join(', ')}` : ''}
                                        {` · ${t('pages.pg_053.requested', { waktu: dateTime(row.requested_at) })}`}
                                    </span>
                                    {row.state === 'FAILED' && <span className="text-label text-text-secondary">{t('pages.pg_053.failed')}</span>}
                                    {row.state === 'WITHHELD' && <span className="text-label text-text-secondary">{t('pages.pg_053.withheld')}</span>}
                                    {row.expires_at !== null && <span className="text-caption text-text-tertiary">{t('pages.pg_053.expires', { waktu: dateTime(row.expires_at) })}</span>}
                                </div>
                                <div className="flex min-w-0 flex-wrap items-center gap-3">
                                    <Status row={row} onSettled={reload} />
                                    {row.href !== null && (
                                        <Button size="sm" render={<a href={row.href} />}>
                                            {t('actions.download')}
                                        </Button>
                                    )}
                                    {(row.state === 'FAILED' || row.state === 'WITHHELD') && (
                                        <Button size="sm" variant="outline" onClick={() => { again(row); }}>
                                            {t('actions.request_again')}
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
                {more !== null && (
                    <Button variant="outline" className="self-start" onClick={() => { visit({ setelah: more }); }}>
                        {t('actions.load_more')}
                    </Button>
                )}
            </div>
        </AppShell>
    );
}
