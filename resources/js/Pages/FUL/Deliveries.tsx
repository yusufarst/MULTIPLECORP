// PG-030 *Pengiriman* (TASK-045; SCR-28; NAV-030; QS-06; D-IA-16): the shipments still awaiting delivery and the
// deliveries recorded, by tab in the address (IP-18), each row with its company where several are in scope (CS-08).
// No stock control here (FS-03).
import { usePage } from '@inertiajs/react';

import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { Code, DateText } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const PAGE = '/gudang/pengiriman';

type Awaiting = { id: string; date: string; project: string; title: string; client: string | null; company: string | null };
type Recorded = { id: string; date: string; recipient: string; project: string; company: string | null; shipment: string | null };
type Props = { list: { tab: 'menunggu' | 'tercatat'; rows: (Awaiting | Recorded)[]; more: boolean; several: boolean } };

/** A recorded delivery's success, shown on the page its answer opened (IP-01; NAV-031). */
function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

export default function Deliveries({ list }: Props) {
    const { t } = useTranslation();
    const awaiting = list.tab === 'menunggu';

    return (
        <AppShell title={t('pages.pg_030.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_030.title')} />
                <PageAnswer />
                <nav aria-label={t('pages.pg_030.title')} className="flex min-w-0 flex-wrap gap-4 border-b border-border-subtle">
                    {(['menunggu', 'tercatat'] as const).map((tab) => (
                        <a key={tab} href={tab === 'menunggu' ? PAGE : `${PAGE}?tab=tercatat`} aria-current={list.tab === tab ? 'page' : undefined}
                            className={cn('min-h-floor py-2 text-body', list.tab === tab ? 'border-b-2 border-text-primary font-medium' : 'text-text-secondary')}>
                            {t(tab === 'menunggu' ? 'deliveries.tab.awaiting' : 'deliveries.tab.recorded')}
                        </a>
                    ))}
                </nav>
                <section data-slot="delivery-list" className="flex min-w-0 flex-col gap-2">
                    {list.rows.length === 0 ? <RegionEmpty text={t(awaiting ? 'deliveries.none_awaiting' : 'deliveries.none_recorded')} /> : (
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {list.rows.map((row) => (
                                <li key={row.id} data-slot="delivery-row" className="flex min-w-0 flex-wrap items-center gap-2 py-2 text-body">
                                    {'recipient' in row ? (
                                        <>
                                            {row.shipment === null ? <span className="font-medium">{row.recipient}</span> : <a className="font-medium underline" href={`${PAGE}/${row.shipment}`}>{row.recipient}</a>}
                                            <Code>{row.project}</Code>
                                        </>
                                    ) : (
                                        <>
                                            <a className="font-medium underline" href={`${PAGE}/${row.id}`}>{row.title}</a>
                                            <Code>{row.project}</Code>
                                            {row.client !== null && <span className="text-text-secondary">{row.client}</span>}
                                        </>
                                    )}
                                    <DateText value={row.date} />
                                    {row.company !== null && <span className="text-caption text-text-secondary">{row.company}</span>}
                                </li>
                            ))}
                        </ul>
                    )}
                    {list.more && <p className="text-caption text-text-secondary">{t('deliveries.list.more')}</p>}
                </section>
            </div>
        </AppShell>
    );
}
