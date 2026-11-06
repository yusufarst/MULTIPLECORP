// PG-008 *Tinjauan* (ROUTE-008; NAV-008; SCR-07; D-IA-11; INFORMATION_ARCHITECTURE §9.2; TASK-058): the Owner review
// queue — a derived view, never an approval step. *Kondisi yang Masih Terbuka* as a compact table above the activity,
// then *Aktivitas untuk Ditinjau* in four tabs — *Aktivitas*, *Keamanan*, *Bukti Gudang Bersama*, *Impor* —, newest
// first within the window (7 days by default), filtered by company, the divider "Tercatat setelah Anda masuk
// sebelumnya", "Buka Catatan", "Muat Lagi" and no total. No approve, dismiss or read control exists (UXS-40).
import { Deferred, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { ListContinuation } from '@/components/data-list';
import { PageHeader } from '@/components/page-header';
import { TextLink } from '@/components/record-blocks';
import { RegionEmpty, RegionLoading } from '@/components/region-state';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppShell from '@/layouts/AppShell';
import { date, dateTime, money } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Item = {
    key: string;
    at: string;
    business_date: string | null;
    actor: string | null;
    company: string | null;
    kind: string;
    reason: string | null;
    href: string | null;
    related?: { company: string | null; href: string | null } | null;
};
type Condition = { kind: string; company: string | null; record: string; since: string; href: string | null };
type Props = {
    tab: 'aktivitas' | 'keamanan' | 'bukti' | 'impor';
    window: { key: string; from: string; to: string };
    filters: { perusahaan: string | null; pelaku: string | null };
    companies: { id: string; code: string }[];
    previous: string | null;
    items: Item[];
    more: string | null;
    conditions?: Condition[];
};

const TABS = ['aktivitas', 'keamanan', 'bukti', 'impor'] as const;
const WINDOWS = ['hari_ini', '7_hari', '30_hari'] as const;

function visit(changes: Record<string, string | null>) {
    const url = new URL(window.location.href);

    for (const [key, value] of Object.entries(changes)) {
        if (value === null || value === '') {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, value);
        }
    }

    url.searchParams.delete('setelah');
    router.get(url.pathname + url.search, {}, { preserveScroll: true });
}

export default function Tinjauan() {
    const { t, has } = useTranslation();
    const { tab, window: span, filters, companies, previous, items, more } = usePage<Props>().props;
    const [shown, setShown] = useState<{ items: Item[]; more: string | null; from: Item[] }>({ items, more, from: items });
    const [loading, setLoading] = useState(false);
    const current = shown.from === items ? shown : { items, more, from: items };

    const kindOf = (item: Item): string => {
        const key = tab === 'aktivitas' ? `activity.action.${item.kind}` : tab === 'keamanan' ? `security_log.kind.${item.kind}` : tab === 'bukti' ? `evidence.type.${item.kind}` : `import.kind.${item.kind}`;

        return has(key) ? t(key) : item.kind;
    };

    const loadMore = () => {
        if (current.more === null) {
            return;
        }

        setLoading(true);
        router.reload({
            only: ['items', 'more'],
            data: { setelah: current.more },
            preserveUrl: true,
            onSuccess: (page) => {
                const next = page.props as unknown as Props;
                setShown({ items: [...current.items, ...next.items], more: next.more, from: next.items });
            },
            onFinish: () => { setLoading(false); },
        });
    };

    const divider = previous === null ? -1 : current.items.findIndex((item) => item.at < previous);

    return (
        <AppShell title={t('pages.pg_008.title')}>
            <div className="flex min-w-0 flex-col gap-8">
                <PageHeader title={t('pages.pg_008.title')} />
                <section aria-label={t('pages.pg_008.conditions')} className="flex min-w-0 flex-col gap-3">
                    <h2 className="text-heading-sm font-semibold text-text-primary">{t('pages.pg_008.conditions')}</h2>
                    <Deferred data="conditions" fallback={<RegionLoading />}>
                        <Conditions />
                    </Deferred>
                </section>
                <section aria-label={t('pages.pg_008.activity')} className="flex min-w-0 flex-col gap-4">
                    <h2 className="text-heading-sm font-semibold text-text-primary">{t('pages.pg_008.activity')}</h2>
                    <nav aria-label={t('pages.pg_008.activity')} className="flex min-w-0 flex-wrap gap-2" role="tablist">
                        {TABS.map((key) => (
                            <button
                                key={key}
                                type="button"
                                role="tab"
                                aria-selected={key === tab}
                                onClick={() => { visit({ tab: key === 'aktivitas' ? null : key }); }}
                                className={cn('inline-flex min-h-touch items-center rounded-md px-3 text-label outline-none focus-visible:outline-2 focus-visible:outline-focus', key === tab ? 'bg-surface-sunken font-semibold text-text-primary' : 'text-text-secondary hover:text-text-primary')}
                            >
                                {t(`pages.pg_008.tab.${key}` as TranslationKey)}
                            </button>
                        ))}
                    </nav>
                    <div className="flex min-w-0 flex-wrap gap-3">
                        <label className="flex flex-col gap-1 text-label text-text-secondary">
                            {t('pages.pg_008.window')}
                            <NativeSelect value={span.key} onChange={(event) => { visit({ jangka: event.target.value === '7_hari' ? null : event.target.value }); }}>
                                {WINDOWS.map((key) => (
                                    <option key={key} value={key}>{t(`pages.pg_008.window.${key}` as TranslationKey)}</option>
                                ))}
                                {span.key === 'rentang' && <option value="rentang">{`${date(span.from)} – ${date(span.to)}`}</option>}
                            </NativeSelect>
                        </label>
                        {companies.length > 1 && (
                            <label className="flex flex-col gap-1 text-label text-text-secondary">
                                {t('pages.pg_008.col_company')}
                                <NativeSelect value={filters.perusahaan ?? ''} onChange={(event) => { visit({ perusahaan: event.target.value }); }}>
                                    <option value="">{t('pages.pg_008.all_companies')}</option>
                                    {companies.map((company) => (
                                        <option key={company.id} value={company.id}>{company.code}</option>
                                    ))}
                                </NativeSelect>
                            </label>
                        )}
                    </div>
                    {current.items.length === 0 ? (
                        <RegionEmpty text={t('pages.pg_008.empty')} />
                    ) : (
                        <ol aria-label={t(`pages.pg_008.tab.${tab}` as TranslationKey)} className="flex min-w-0 flex-col divide-y divide-border-subtle" data-slot="review-items">
                            {current.items.map((item, index) => (
                                <li key={item.key} className="flex min-w-0 flex-col gap-1 py-3">
                                    {index === divider && previous !== null && index > 0 && (
                                        <p role="separator" className="pb-2 text-caption text-text-tertiary">{t('pages.pg_008.divider_tpl', { v: dateTime(previous) })}</p>
                                    )}
                                    <div className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                                        <span className="text-body text-text-primary">{kindOf(item)}</span>
                                        <span className="text-label text-text-secondary">{item.company ?? (tab === 'bukti' ? t('pages.pg_008.pool') : t('pages.pg_008.no_company'))}</span>
                                        {item.actor !== null && <span className="text-label text-text-secondary">{item.actor}</span>}
                                        <span className="ml-auto text-caption whitespace-nowrap text-text-tertiary">
                                            {dateTime(item.at)}
                                            {item.business_date !== null && ` · ${date(item.business_date)}`}
                                        </span>
                                    </div>
                                    {item.reason !== null && <p className="text-label text-text-secondary">{tab === 'impor' ? t('pages.pg_008.row_tpl', { v: item.reason }) : item.reason}</p>}
                                    <div className="flex flex-wrap gap-4">
                                        {item.href !== null && <TextLink href={item.href}>{t('pages.pg_008.open')}</TextLink>}
                                        {item.related?.href !== null && item.related?.href !== undefined && (
                                            <TextLink href={item.related.href}>{`${t('pages.pg_008.related')}${item.related.company === null ? '' : ` · ${item.related.company}`}`}</TextLink>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ol>
                    )}
                    {current.items.length > 0 && <ListContinuation state={current.more === null ? 'end' : 'more'} loading={loading} onLoadMore={loadMore} onReload={loadMore} />}
                </section>
            </div>
        </AppShell>
    );
}

function Conditions() {
    const { t } = useTranslation();
    const { conditions = [] } = usePage<Props>().props;

    if (conditions.length === 0) {
        return <RegionEmpty text={t('pages.pg_008.conditions_empty')} />;
    }

    return (
        <Table aria-label={t('pages.pg_008.conditions')}>
            <TableHeader>
                <TableRow className="hover:bg-transparent">
                    <TableHead>{t('pages.pg_008.col_condition')}</TableHead>
                    <TableHead>{t('pages.pg_008.col_company')}</TableHead>
                    <TableHead numeric>{t('pages.pg_008.col_amount')}</TableHead>
                    <TableHead>{t('pages.pg_008.col_since')}</TableHead>
                    <TableHead />
                </TableRow>
            </TableHeader>
            <TableBody>
                {conditions.map((condition, index) => (
                    <TableRow key={`${condition.kind}-${String(index)}`}>
                        <TableCell>{t(`pages.pg_008.condition.${condition.kind}` as TranslationKey)}</TableCell>
                        <TableCell>{condition.company ?? t('pages.pg_008.no_company')}</TableCell>
                        <TableCell numeric>{money(condition.record)}</TableCell>
                        <TableCell>{date(condition.since.slice(0, 10))}</TableCell>
                        <TableCell>{condition.href !== null && <TextLink href={condition.href}>{t('pages.pg_008.open')}</TextLink>}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
