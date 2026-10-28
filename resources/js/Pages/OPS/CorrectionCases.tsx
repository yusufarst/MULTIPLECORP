// PG-040 *Kasus Koreksi* (ROUTE-040, ROUTE-271; NAV-040; SCR-33; IA §8.4; ADMIN_FLOW §11; TASK-031): the correction
// cases of one company at a time, open ones first, each with its kind, reason, project and the residuals that keep it
// open (C-46) — an unbacked refund, an unresolved deduction, a purchase return's unaccounted cost —, their amounts by
// family (PJ-16), another company's causal project as the relation marker (XL-3). The page starts no case: a case opens
// with the correction that records it. "Tutup Kasus" with its outcome (IP-09; MSG-40), offered only when no residual
// remains and decided by the server.
import { router } from '@inertiajs/react';
import { useState } from 'react';

import { RelationMarker } from '@/components/company';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { ListContinuation } from '@/components/data-list';
import { FormField } from '@/components/form';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateTimeText, Money } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Branch = { id: string; code: string; name: string };
type Row = {
    id: string;
    kind: string;
    state: 'OPEN' | 'CLOSED';
    reason: string | null;
    outcome: string | null;
    opened_at: string;
    reopened: boolean;
    lock_version: number;
    project: { id: string; number: string } | null;
    causal: { foreign: true } | { foreign: false; id: string; number: string } | null;
    lot: string | null;
    residuals: string[];
    counters: Partial<Record<'unbacked_refund_amount' | 'residual_settlement_amount' | 'returned_cost' | 'refunded_value' | 'replaced_value' | 'unrecovered_value', string>>;
};
type Props = { branches: Branch[]; branch: string | null; rows: Row[]; more: string | null; can: { close: boolean } };

const kindName = (kind: string): TranslationKey => `cases.kind.${kind.toLowerCase()}` as TranslationKey;
const residualName = (residual: string): TranslationKey => `cases.residual.${residual}` as TranslationKey;
const counterName = (counter: string): TranslationKey => `cases.counter.${counter}` as TranslationKey;

function visit(query: Record<string, string | null>) {
    const params = new URLSearchParams(window.location.search);

    for (const [key, value] of Object.entries(query)) {
        if (value === null || value === '') {
            params.delete(key);
        } else {
            params.set(key, value);
        }
    }

    router.get(`/dokumen/kasus${params.size > 0 ? `?${params.toString()}` : ''}`, {}, { preserveScroll: true, preserveState: true });
}

function CaseDetail({ row, branch, canClose }: { row: Row; branch: string | null; canClose: boolean }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const close = useCommand<Record<string, string | number>>(() => `/dokumen/kasus/${row.id}/tutup?${new URLSearchParams(branch === null ? {} : { perusahaan: branch }).toString()}`, 'post');

    return (
        <section aria-label={t(kindName(row.kind))} className="flex min-w-0 flex-col gap-3 rounded-md border border-border-subtle bg-surface p-card">
            <div className="flex min-w-0 flex-wrap items-center gap-3">
                <span className="font-semibold text-text-primary">{t(kindName(row.kind))}</span>
                <StatusBadge family="correction_case" state={row.state} />
                {row.reopened && row.state === 'OPEN' && <span className="text-caption text-text-secondary">{t('cases.reopened')}</span>}
                <DateTimeText value={row.opened_at} />
            </div>
            {row.reason !== null && <p className="text-body text-text-primary">{row.reason}</p>}
            <dl className="grid min-w-0 grid-cols-2 gap-x-4 gap-y-1 text-body">
                {row.project !== null && (
                    <>
                        <dt className="text-text-secondary">{t('cases.field.project')}</dt>
                        <dd>
                            <Code>{row.project.number}</Code>
                        </dd>
                    </>
                )}
                {row.causal !== null && (
                    <>
                        <dt className="text-text-secondary">{t('cases.field.causal')}</dt>
                        <dd>{row.causal.foreign ? <RelationMarker /> : <Code>{row.causal.number}</Code>}</dd>
                    </>
                )}
                {row.lot !== null && (
                    <>
                        <dt className="text-text-secondary">{t('cases.field.lot')}</dt>
                        <dd>
                            <Code>{row.lot}</Code>
                        </dd>
                    </>
                )}
                {Object.entries(row.counters).map(([counter, amount]) => (
                    <div key={counter} className="contents">
                        <dt className="text-text-secondary">{t(counterName(counter))}</dt>
                        <dd>
                            <Money value={amount} />
                        </dd>
                    </div>
                ))}
            </dl>
            {row.residuals.length > 0 && row.state === 'OPEN' && (
                <div className="flex min-w-0 flex-col gap-1">
                    <p className="text-body font-medium text-text-primary">{t('cases.residuals')}</p>
                    <ul className="list-disc pl-5 text-body text-text-secondary">
                        {row.residuals.map((residual) => (
                            <li key={residual}>{t(residualName(residual))}</li>
                        ))}
                    </ul>
                </div>
            )}
            {row.outcome !== null && row.state === 'CLOSED' && <p className="text-body text-text-secondary">{t('cases.outcome', { hasil: row.outcome })}</p>}
            {close.answer !== null && <OutcomeMessage answer={close.answer} />}
            {canClose && row.state === 'OPEN' && row.residuals.length === 0 && (
                <div>
                    <Button variant="outline" onClick={() => { setReason(''); close.clear(); setOpen(true); }}>
                        {t('actions.close_case')}
                    </Button>
                </div>
            )}
            <ConfirmCommand
                open={open}
                onOpenChange={setOpen}
                title={t('cases.confirm.title', { jenis: t(kindName(row.kind)) })}
                consequence={t('cases.confirm.consequence')}
                reason={{ value: reason, onChange: setReason, error: close.errors.alasan ?? null }}
                confirmLabel={t('actions.close_case')}
                pending={close.pending}
                answer={close.answer}
                onConfirm={() => {
                    close.submit({ lock_version: row.lock_version, alasan: reason }, () => { setOpen(false); });
                }}
            />
        </section>
    );
}

export default function CorrectionCases({ branches, branch, rows, more, can }: Props) {
    const { t } = useTranslation();
    // PT-08: "Muat Lagi" adds the page after the rows shown on a partial reload; any other visit shows its first page.
    const [base, setBase] = useState(rows);
    const [shown, setShown] = useState(rows);
    const [next, setNext] = useState(more);
    const [appending, setAppending] = useState(false);

    if (base !== rows) {
        setBase(rows);
        setShown(appending ? [...shown, ...rows.filter((row) => !shown.some((known) => known.id === row.id))] : rows);
        setNext(more);
        setAppending(false);
    }

    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        router.reload({ only: ['rows', 'more'], data: { setelah: next }, preserveUrl: true });
    };

    return (
        <AppShell title={t('pages.pg_040.title')} back={{ href: '/dokumen', label: t('pages.pg_035.title') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_040.title')} />
                {branches.length > 1 && (
                    <FormField label={t('cases.field.branch')} className="min-w-0 sm:w-80">
                        {(control) => (
                            <NativeSelect {...control} value={branch ?? ''} onChange={(event) => { visit({ perusahaan: event.target.value, setelah: null }); }}>
                                {branches.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {`${option.code} · ${option.name}`}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>
                )}
                {shown.length === 0 ? (
                    <RegionEmpty text={branches.length === 0 ? t('cases.empty_scope') : t('cases.empty')} />
                ) : (
                    <>
                        <ol aria-label={t('pages.pg_040.title')} className="flex min-w-0 flex-col gap-4">
                            {shown.map((row) => (
                                <li key={row.id}>
                                    <CaseDetail row={row} branch={branch} canClose={can.close} />
                                </li>
                            ))}
                        </ol>
                        <ListContinuation state={next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={loadMore} />
                    </>
                )}
            </div>
        </AppShell>
    );
}
