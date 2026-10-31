// PG-045 *Pembayaran* (ROUTE-045; NAV-045; SCR-41; WF-FIN-03; J-FIN-03; TASK-051): by branch, newest first, the payment just recorded first (S2-J06); tabs, client and cursor in the address (IP-18); never a total across companies (CS-08). A row opens the payment in a sheet: its fact, *Tanpa Bukti* and *Dikontra*,
// its citation, applications and settlements, both sides of any correction visible (SF-CONTRA). "Terapkan", "Pindahkan Penerapan" and the contra in place, the Kuitansi on PG-036 (INT-221); a refund-consumed application offers no move, the row saying why (L-46). No control deletes a payment (FS-02).
import { router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

import { DataList, ListContinuation } from '@/components/data-list';
import { FormField } from '@/components/form';
import { OutcomeMessage } from '@/components/outcome-message';
import { type MenuAction, PageHeader } from '@/components/page-header';
import { PositionBlock } from '@/components/record-blocks';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { NativeSelect } from '@/components/ui/native-select';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tabs, TabsList, TabsTab } from '@/components/ui/tabs';
import { Code, DateText, Money } from '@/components/values';
import { FinanceNav } from '@/features/fin/finance-nav';
import { Answered, type Payload } from '@/features/fin/invoice-receivable';
import { type Application, ApplySheet, ContraSheet, MoveSheet, type Payment, type Targets } from '@/features/fin/payment-dialogs';
import AppShell from '@/layouts/AppShell';
import { add } from '@/lib/decimal';
import { money } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Tab = 'semua' | 'kredit' | 'tanpa_bukti';
type Branch = { id: string; code: string; name: string };
type Client = { id: string; name: string };
type Props = { branches: Branch[]; branch: string | null; tab: Tab; client: string | null; clients: Client[]; rows: Payment[]; more: string | null; after: string | null; cursor_failed: boolean; recorded: string | null; today: string; can: { record: boolean }; targets?: Targets };

const TABS: Tab[] = ['semua', 'kredit', 'tanpa_bukti'];
const positive = (value: string) => /[1-9]/.test(value);

function States({ payment }: { payment: Payment }) {
    return (
        <span className="flex flex-wrap gap-1">
            {payment.unevidenced !== null && <StatusBadge family="payment" state="WITHOUT_PROOF" size="sm" />}
            {positive(payment.contra) && <StatusBadge family="payment" state="CONTRA_D" size="sm" />}
        </span>
    );
}

export default function Payments({ branches, branch, tab, client, clients, rows, more, after, cursor_failed: cursorFailed, recorded, today, can, targets }: Props) {
    const { t } = useTranslation();
    const arrived = (usePage().flash as { answer?: { kind?: string; text?: string } }).answer;
    const [base, setBase] = useState(rows);
    const [shown, setShown] = useState(rows);
    const [next, setNext] = useState(more);
    const [appending, setAppending] = useState(false);
    const [opened, setOpened] = useState<string | null>(recorded);
    const [applying, setApplying] = useState<string | null>(null);
    const [moving, setMoving] = useState<{ payment: string; application: Application } | null>(null);
    const [contraing, setContraing] = useState<string | null>(null);
    const [kuitansi, setKuitansi] = useState<{ payment: string; invoice: string } | null>(null);
    const [linking, setLinking] = useState<string | null>(null);
    const prepare = useCommand<Payload>(() => '/dokumen', 'post');
    const link = useCommand<Payload>(() => `/keuangan/kuitansi/${linking ?? ''}/tautkan`, 'post');

    if (base !== rows) {
        setBase(rows);
        setShown(appending ? [...shown, ...rows.filter((row) => !shown.some((known) => known.id === row.id))] : rows);
        setNext(more);
        setAppending(false);
    }

    const query = (change: Record<string, string | null>) => {
        const params = new URLSearchParams();

        for (const [key, value] of Object.entries({ perusahaan: branch, tab: tab === 'semua' ? null : tab, klien: client, ...change })) {
            if (value !== null && value !== '') params.set(key, value);
        }

        const text = params.toString();

        return `/keuangan/pembayaran${text === '' ? '' : `?${text}`}`;
    };
    const visit = (change: Record<string, string | null>) => { router.get(query(change), {}, { preserveState: true }); };
    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        // The cursor stays in the address and comes back with reload (NV-05).
        router.reload({ only: ['rows', 'more', 'after', 'cursor_failed'], data: { setelah: next } });
    };
    const back = query({ setelah: after });
    const find = (id: string | null) => shown.find((row) => row.id === id) ?? null;
    const current = find(opened);
    const applyingRow = find(applying);
    const contraRow = find(contraing);
    const movingRow = moving === null ? null : find(moving.payment);
    const rowActions = (row: Payment): MenuAction[] => [
        ...(row.can.apply ? [{ label: t('payments.action.apply'), onSelect: () => { setApplying(row.id); } }] : []),
        ...(row.can.contra ? [{ label: t('payments.action.contra'), tone: 'danger' as const, onSelect: () => { setContraing(row.id); } }] : []),
    ];
    const applied = (row: Payment) => row.applications.filter((application) => application.state === 'ACTIVE');

    return (
        <AppShell title={t('pages.pg_045.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_045.title')} primary={can.record ? <Button onClick={() => { router.visit('/keuangan/pembayaran/baru'); }}><Icon icon={Plus} size="sm" />{t('actions.record_payment')}</Button> : undefined} />
                <FinanceNav current="payments" />
                {recorded !== null && arrived?.kind === 'COMMITTED' && arrived.text !== undefined && <OutcomeMessage answer={{ kind: 'COMMITTED', text: arrived.text }} />}
                <Answered command={prepare} />
                <Answered command={link} />
                <div className="flex min-w-0 flex-wrap items-end gap-4">
                    {branches.length > 1 && (
                        <FormField label={t('receivables.field.branch')} className="min-w-0 sm:w-80">
                            {(control) => (
                                <NativeSelect {...control} value={branch ?? ''} onChange={(event) => { visit({ perusahaan: event.target.value, klien: null }); }}>
                                    <option value="">{t('receivables.field.all_branches')}</option>
                                    {branches.map((option) => <option key={option.id} value={option.id}>{`${option.code} · ${option.name}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}
                    <FormField label={t('receivables.field.client')} className="min-w-0 sm:w-80">
                        {(control) => (
                            <NativeSelect {...control} value={client ?? ''} onChange={(event) => { visit({ klien: event.target.value }); }}>
                                <option value="">{t('receivables.field.all_clients')}</option>
                                {clients.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                </div>
                <Tabs value={tab} onValueChange={(value) => { visit({ tab: String(value) }); }}>
                    <TabsList>
                        {TABS.map((each) => <TabsTab key={each} value={each}>{t(`payments.tab.${each}` as TranslationKey)}</TabsTab>)}
                    </TabsList>
                </Tabs>
                {cursorFailed && shown.length === 0 ? (
                    <ListContinuation state="cursor_failed" onLoadMore={loadMore} onReload={() => { visit({}); }} />
                ) : shown.length === 0 ? (
                    <RegionEmpty text={branches.length === 0 ? t('receivables.empty_scope') : t(`payments.empty.${tab}` as TranslationKey)} />
                ) : (
                    <>
                        <DataList<Payment>
                            caption={t('pages.pg_045.title')}
                            rows={shown}
                            rowKey={(row) => row.id}
                            rowHref={() => null}
                            rowActions={rowActions}
                            columns={[
                                { key: 'date', header: t('pages.pg_045.col_date'), cell: (row) => <button type="button" className="text-accent-link underline underline-offset-2" onClick={() => { setOpened(row.id); }}><DateText value={row.date} /></button> },
                                ...(branches.length > 1 ? [{ key: 'company', header: t('receivables.field.branch'), cell: (row: Payment) => row.company }] : []),
                                { key: 'client', header: t('invoices.field.client'), cell: (row) => row.client },
                                { key: 'reference', header: t('pages.pg_046.reference'), priority: 'low', cell: (row) => row.reference ?? '—' },
                                { key: 'state', header: t('invoices.field.state'), cell: (row) => <States payment={row} /> },
                                { key: 'credit', header: t('glossary.gl_141'), numeric: true, priority: 'low', cell: (row) => <Money value={row.capacity} /> },
                                { key: 'amount', header: t('pages.pg_046.amount'), numeric: true, cell: (row) => <Money value={row.net} /> },
                            ]}
                            dense={(row) => ({ title: <button type="button" className="text-accent-link underline underline-offset-2" onClick={() => { setOpened(row.id); }}>{row.client}</button>, secondary: <DateText value={row.date} />, figure: <Money value={row.net} />, status: <States payment={row} /> })}
                        />
                        <ListContinuation state={cursorFailed ? 'cursor_failed' : next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={() => { visit({}); }} />
                    </>
                )}
            </div>
            {current !== null && (
                <Sheet open onOpenChange={(open) => { if (!open) setOpened(null); }}>
                    <SheetContent width="lg" closeLabel={t('actions.close')}>
                        <SheetHeader>
                            <SheetTitle>{t('payments.detail.title', { klien: current.client })}</SheetTitle>
                        </SheetHeader>
                        <div data-testid="payment" className="flex min-w-0 flex-col gap-5">
                            <States payment={current} />
                            <PositionBlock title={t('payments.position.title')} lines={[
                                { label: t('pages.pg_046.amount'), value: <Money value={current.amount} /> },
                                ...(positive(current.contra) ? [{ label: t('payments.position.contra'), value: <Money value={current.contra} /> }] : []),
                                { label: t('pages.pg_046.col_applied'), value: <Money value={add('0', ...applied(current).filter((row) => row.target === 'INVOICE').map((row) => row.amount))} /> },
                                { label: t('glossary.gl_141'), value: <Money value={current.capacity} />, emphasis: true },
                            ]} />
                            <dl className="grid min-w-0 gap-2 text-body sm:grid-cols-2">
                                <div><dt className="text-text-secondary">{t('pages.pg_045.col_date')}</dt><dd><DateText value={current.date} /></dd></div>
                                <div><dt className="text-text-secondary">{t('pages.pg_046.method')}</dt><dd>{t(`payments.method.${current.method}` as TranslationKey)}</dd></div>
                                {current.account !== null && <div><dt className="text-text-secondary">{t('pages.pg_046.account')}</dt><dd>{current.account}</dd></div>}
                                {current.reference !== null && <div><dt className="text-text-secondary">{t('pages.pg_046.reference')}</dt><dd>{current.reference}</dd></div>}
                                {current.intermediary !== null && <div><dt className="text-text-secondary">{t('payments.field.intermediary')}</dt><dd>{current.intermediary}</dd></div>}
                                <div><dt className="text-text-secondary">{t('pages.pg_046.statement_line')}</dt><dd>{current.line ?? (current.flagged ? t('payments.detail.flagged') : '—')}</dd></div>
                                {positive(current.claimed) && <div><dt className="text-text-secondary">{t('payments.detail.claimed')}</dt><dd><Money value={current.claimed} /></dd></div>}
                                {current.proof !== null && <div><dt className="text-text-secondary">{t('pages.pg_046.proof')}</dt><dd><a href={`/dokumen/bukti?bukti=${current.proof}`} className="text-accent-link underline underline-offset-2">{t('payments.detail.open_proof')}</a></dd></div>}
                                {current.recovery && <div className="sm:col-span-2"><dd>{t('payments.detail.recovery')}</dd></div>}
                                {current.rerecords !== null && <div className="sm:col-span-2"><dd>{t('payments.detail.rerecords')}</dd></div>}
                                {current.rerecorded_by !== null && <div className="sm:col-span-2"><dd>{t('payments.detail.rerecorded')}</dd></div>}
                                {current.unevidenced !== null && <div className="sm:col-span-2"><dt className="text-text-secondary">{t('payments.detail.no_proof')}</dt><dd>{current.unevidenced}</dd></div>}
                            </dl>
                            <section aria-label={t('pages.pg_046.applications')} className="flex min-w-0 flex-col gap-2">
                                <h3 className="text-heading-sm font-semibold text-text-primary">{t('pages.pg_046.applications')}</h3>
                                {current.applications.length === 0 && <p className="text-body text-text-secondary">{t('payments.detail.no_applications')}</p>}
                                <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                    {current.applications.map((row) => (
                                        <li key={row.handle} data-testid="payment-application" className="flex min-w-0 flex-col gap-1 py-2">
                                            <div className="flex min-w-0 flex-wrap items-baseline justify-between gap-2">
                                                <span className={row.state === 'ACTIVE' ? 'min-w-0 text-body text-text-primary' : 'min-w-0 text-body text-text-secondary line-through'}>
                                                    {row.target === 'REFUND' ? t('payments.detail.refund') : <>{row.invoice !== null ? <a href={`/keuangan/invoice/${row.invoice}`} className="text-accent-link underline underline-offset-2"><Code>{row.number ?? t('payments.invoice.unnumbered')}</Code></a> : '—'}</>}
                                                </span>
                                                <Money value={row.amount} />
                                            </div>
                                            {row.end_reason !== null && <p className="text-caption text-text-secondary">{t(`payments.end_reason.${row.end_reason}` as TranslationKey)}</p>}
                                            {row.state === 'ACTIVE' && row.target === 'REFUND' && <p className="text-caption text-text-secondary">{t('payments.detail.refund_final')}</p>}
                                            {row.state === 'ACTIVE' && row.target === 'INVOICE' && (
                                                <div className="flex flex-wrap gap-2">
                                                    {row.can_move && current.can.reallocate && <Button variant="ghost" size="sm" onClick={() => { setMoving({ payment: current.id, application: row }); }}>{t('payments.action.move')}</Button>}
                                                    {row.receipt !== null ? <a href={`/dokumen/${row.receipt}`} className="inline-flex min-h-touch items-center text-body text-accent-link underline underline-offset-2">{t('payments.action.open_receipt')}</a>
                                                        : current.can.kuitansi && row.kuitansi === null && row.invoice !== null && <Button variant="ghost" size="sm" pending={prepare.pending && kuitansi?.invoice === row.invoice} onClick={() => { setKuitansi({ payment: current.id, invoice: row.invoice ?? '' }); prepare.submit({ jenis: 'DOC-05', mode: 'RECEIPT', subjek: { payment_id: current.id, invoice_id: row.invoice ?? '' } }); }}>{t('payments.action.receipt')}</Button>}
                                                    {row.kuitansi !== null && current.can.link && <Button variant="ghost" size="sm" pending={link.pending && linking === row.kuitansi} onClick={() => { setLinking(row.kuitansi); link.submit({ pembayaran: [current.id] }); }}>{t('payments.action.link')}</Button>}
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                            {current.settlements.length > 0 && (
                                <section aria-label={t('payments.detail.settlements')} className="flex min-w-0 flex-col gap-2">
                                    <h3 className="text-heading-sm font-semibold text-text-primary">{t('payments.detail.settlements')}</h3>
                                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                        {current.settlements.map((row) => (
                                            <li key={row.handle} className="flex min-w-0 flex-wrap items-baseline justify-between gap-2 py-2 text-body">
                                                <span className={row.state === 'ACTIVE' ? 'min-w-0 text-text-primary' : 'min-w-0 text-text-secondary line-through'}>{`${t(`payments.claim.${row.class}` as TranslationKey)} · ${row.number ?? '—'}`}</span>
                                                <span>{money(row.amount)}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            )}
                            <div className="flex flex-wrap gap-2">
                                {current.can.apply && <Button onClick={() => { setApplying(current.id); }}>{t('payments.action.apply')}</Button>}
                                {current.can.contra && <Button variant="outline" onClick={() => { setContraing(current.id); }}>{t('payments.action.contra')}</Button>}
                            </div>
                        </div>
                    </SheetContent>
                </Sheet>
            )}
            {applyingRow !== null && <ApplySheet key={applyingRow.id} payment={applyingRow} targets={targets} today={today} back={back} onClose={() => { setApplying(null); }} />}
            {moving !== null && movingRow !== null && <MoveSheet key={moving.application.handle} payment={movingRow} application={moving.application} targets={targets} today={today} back={back} onClose={() => { setMoving(null); }} />}
            {contraRow !== null && <ContraSheet key={contraRow.id} payment={contraRow} today={today} back={back} onClose={() => { setContraing(null); }} />}
        </AppShell>
    );
}
