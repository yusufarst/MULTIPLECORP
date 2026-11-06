// The home pages' signal groups (PG-006, PG-007; INFORMATION_ARCHITECTURE §9; DESIGN_SYSTEM §9.5 PT-21): a deferred
// group with its own spinner and its own error with "Muat Ulang Bagian Ini", the others staying (PT-19); each signal
// one PT-21 group — its bounded counts per company, at most five rows, "Lihat Semua" — whose row reads its condition,
// company, record, age and link (INT-019). A signal the account does not read is absent, with no heading (PJ-13).
import { router, usePoll } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { type ReactNode, useState } from 'react';

import { RegionError, RegionLoading } from '@/components/region-state';
import { SignalGroup } from '@/components/signals';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { date, money, quantity, time } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';

export type SignalRow = { id: string; company: string | null; subject: string; ref: string | null; value: string | null; unit: string | null; at: string | null; href: string | null };
export type SignalRead = { code: string; rows: SignalRow[]; counts: { company: string; count: string }[]; more: string | null };
export type Failed = { failed: true };

export function failed(value: unknown): value is Failed {
    return typeof value === 'object' && value !== null && 'failed' in value;
}

const QUANTITY_ROWS: Record<string, TranslationKey> = {
    'QS-01': 'signals.qs_01_row',
    'QS-02': 'signals.qs_02_row',
    'QS-03': 'signals.qs_03_row',
    'QS-04': 'signals.qs_04_row',
    'QS-06': 'signals.qs_06_row',
    'QS-22': 'signals.qs_22_row',
    'QS-24': 'signals.qs_24_row',
};

const TITLES: Record<string, TranslationKey> = {
    'QS-01': 'signals.qs_01_title',
    'QS-02': 'signals.qs_02_title',
    'QS-03': 'signals.qs_03_title',
    'QS-04': 'signals.qs_04_title',
    'QS-05': 'signals.qs_05_title',
    'QS-06': 'signals.qs_06_title',
    'QS-07': 'signals.qs_07_title',
    'QS-08': 'signals.qs_08_title',
    'QS-09': 'signals.qs_09_title',
    'QS-10': 'signals.qs_10_title',
    'QS-11': 'signals.qs_11_title',
    'QS-12': 'signals.qs_12_title',
    'QS-13': 'signals.qs_13_title',
    'QS-15': 'signals.qs_15_title',
    'QS-16': 'pages.pg_006.row_qs16',
    'QS-17': 'signals.qs_17_title',
    'QS-18': 'signals.qs_18_title',
    'QS-19': 'signals.qs_19_title',
    'QS-20': 'pages.pg_006.row_qs20',
    'QS-21': 'signals.qs_21_title',
    'QS-22': 'signals.qs_22_title',
    'QS-23': 'signals.qs_23_title',
    'QS-24': 'signals.qs_24_title',
};

/** The row's condition and record as the signal states them (IA §9.1); figures in the locale's form. */
function useRow() {
    const { t, has } = useTranslation();
    const label = (key: string, fallback: string): string => (has(key) ? t(key) : fallback);
    const qty = (row: SignalRow, value: string | null = row.value): string => (value === null ? '' : quantity(value, row.unit ?? ''));
    const amount = (row: SignalRow): string => (row.value === null ? '' : money(row.value));

    return (code: string, row: SignalRow): { condition: ReactNode; subject: ReactNode } => {
        const project = row.ref === null ? row.subject : `${row.subject} · ${row.ref}`;
        const quantityRow = QUANTITY_ROWS[code];

        if (quantityRow !== undefined) {
            return { condition: t(quantityRow, { v: qty(row) }), subject: project };
        }

        switch (code) {
            case 'QS-05':
                return { condition: t('signals.qs_05_row', { v: row.value ?? '' }), subject: row.subject };
            case 'QS-07':
                return { condition: row.value === null ? label(`cases.kind.${row.subject.toLowerCase()}`, row.subject) : t('signals.qs_07_row', { v: amount(row) }), subject: row.ref ?? label(`cases.kind.${row.subject.toLowerCase()}`, row.subject) };
            case 'QS-08':
                return { condition: label(`status.rendition.${(row.value ?? '').toLowerCase()}`, row.value ?? ''), subject: row.subject };
            case 'QS-09':
            case 'QS-10':
            case 'QS-11':
                return { condition: t('signals.qs_receivable_row', { v: amount(row) }), subject: project };
            case 'QS-12':
                return { condition: `${label(`signals.qs_12_kind.${row.ref ?? ''}`, row.ref ?? '')} ${amount(row)}`, subject: row.subject };
            case 'QS-13':
            case 'QS-19':
                return { condition: amount(row), subject: row.subject };
            case 'QS-15':
                return {
                    condition: row.ref === 'unmet' ? t('signals.qs_15_kind.unmet', { v: row.value ?? '' }) : label(`signals.qs_15_kind.${row.ref ?? ''}`, '').replace(':v', amount(row)),
                    subject: row.subject,
                };
            case 'QS-16':
                return { condition: t('signals.qs_16_row', { v: row.value ?? '0' }), subject: `${row.subject} · ${label(`status.project.${(row.ref ?? '').toLowerCase()}`, row.ref ?? '')}` };
            case 'QS-17':
                return { condition: t('signals.qs_17_row', { v: qty(row), w: qty(row, row.ref) }), subject: row.subject };
            case 'QS-18':
                return { condition: row.ref === 'stale' ? t('signals.qs_18_kind.stale') : t('signals.qs_18_kind.finding', { v: qty(row) }), subject: row.subject };
            case 'QS-20':
            case 'QS-21': {
                const action = `activity.action.${row.subject}`;

                return { condition: has(action) ? t(action) : t('activity.action.unknown', { kode: row.subject }), subject: row.ref ?? '' };
            }
            case 'QS-23':
                return { condition: t('signals.qs_23_row', { v: row.value ?? '' }), subject: project };
            default:
                return { condition: row.value ?? '', subject: row.subject };
        }
    };
}

/** One deferred group: its spinner, its error, then its signals in the statement's order. */
export function SignalSection({ data, reload, owner = false }: { data: SignalRead[] | Failed | undefined; reload: () => void; owner?: boolean }) {
    const { t } = useTranslation();
    const row = useRow();

    if (data === undefined) {
        return <RegionLoading />;
    }

    if (failed(data)) {
        return <RegionError reference="" onReload={reload} />;
    }

    return (
        <div className="flex min-w-0 flex-col gap-5">
            {data.map((signal) => {
                const title = owner && signal.code === 'QS-18' ? 'signals.qs_18_attribution_title' : (TITLES[signal.code] ?? 'signals.qs_01_title');
                const empty = `signals.qs_${signal.code.slice(3)}_empty` as TranslationKey;

                return (
                    <SignalGroup
                        key={`${signal.code}-${title}`}
                        title={t(title)}
                        counts={signal.counts.map((count) => ({ company: count.company === '' ? t('pages.pg_008.no_company') : count.company, count: count.count }))}
                        state={{
                            phase: 'ready',
                            rows: signal.rows.map((item) => ({
                                id: item.id,
                                ...row(signal.code, item),
                                company: item.company ?? '',
                                age: item.at !== null && /^\d{4}-\d{2}-\d{2}/.test(item.at) ? date(item.at.slice(0, 10)) : '',
                                href: item.href,
                            })),
                        }}
                        empty={t(empty)}
                        seeAll={signal.more}
                        onReload={reload}
                    />
                );
            })}
        </div>
    );
}

/**
 * The refresh of a home page (PF-26; PF-36; H7-13): "Muat Ulang" by the user, and a poll no faster than once a minute
 * naming its groups' props, sent as a background request that never extends the session; "Diperbarui {waktu} WIB".
 */
export function Refresh({ groups }: { groups: string[] }) {
    const { t } = useTranslation();
    const [at, setAt] = useState(() => new Date().toISOString());
    const [busy, setBusy] = useState(false);
    const done = () => {
        setAt(new Date().toISOString());
        setBusy(false);
    };

    usePoll(60_000, { only: groups, async: true, onFinish: done });

    return (
        <div className="flex items-center gap-3 text-caption text-text-tertiary">
            <span>{t('states.updated_at', { waktu: time(at) })}</span>
            <Button
                variant="ghost"
                size="sm"
                disabled={busy}
                onClick={() => {
                    setBusy(true);
                    router.reload({ only: groups, onFinish: done });
                }}
            >
                <Icon icon={RefreshCw} size="sm" />
                {t('states.refresh')}
            </Button>
        </div>
    );
}
