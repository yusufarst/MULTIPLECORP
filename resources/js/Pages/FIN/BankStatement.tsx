// PG-050 *Mutasi Bank* (ROUTE-050, ROUTE-261, ROUTE-262; NAV-050; SCR-46; J-FIN-07; L-22; QS-12; TASK-051): dense, at the desk, one branch at a time: the statement lines with what cites them, the bank payments flagged until cited, the current claimed deductions. "Catat Mutasi Bank" with its BANK_STATEMENT evidence and the
// flagged payments this line answers; a line's void, a citation's correction and a claimed deduction's supersession each with its reason (IP-09), present only with `cash.correct`. "Kembali" → PG-041. Tab and cursor in the address (IP-18).
import { router } from '@inertiajs/react';
import { Plus, Upload } from 'lucide-react';
import { useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { DataList, ListContinuation } from '@/components/data-list';
import { BusinessDateControl, type BusinessDate } from '@/components/date-field';
import { DecimalInput, FormField, FormSection } from '@/components/form';
import { type MenuAction, PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tabs, TabsList, TabsTab } from '@/components/ui/tabs';
import { Code, DateText, Money } from '@/components/values';
import { FinanceNav } from '@/features/fin/finance-nav';
import { Answered, type Payload } from '@/features/fin/invoice-receivable';
import { ProofSheet } from '@/features/fin/proof-sheet';
import AppShell from '@/layouts/AppShell';
import { dateLong, money } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Tab = 'mutasi' | 'belum' | 'potongan';
type Citing = { id: string; client: string; date: string; net: string };
type LineRow = { kind: 'line'; key: string; handle: string; date: string; account: string; amount: string; reference: string; state: string; voided_reason: string | null; evidence: string; cited: string; citing: Citing[]; can_void: boolean };
type FlaggedRow = { kind: 'payment'; key: string; id: string; date: string; client: string; net: string; reference: string | null; account: string; account_handle: string; can_cite: boolean };
type ClaimRow = { kind: 'claim'; key: string; handle: string; payment: string; date: string; client: string; class: string; type: string; amount: string; settled: string; can_supersede: boolean };
type Row = LineRow | FlaggedRow | ClaimRow;
type OpenLine = { handle: string; account: string; date: string; amount: string; uncited: string; reference: string };
type Flagged = { id: string; account: string; date: string; client: string; net: string };
type Statement = { id: string; issuer: string; reference: string | null; date: string };
type Props = { branches: { id: string; code: string; name: string }[]; branch: string | null; tab: Tab; rows: Row[]; more: string | null; after: string | null; cursor_failed: boolean; accounts: { handle: string; label: string }[]; open_lines: OpenLine[]; flagged: Flagged[]; evidence: Statement[]; today: string; can: { correct: boolean } };
type Correction = { aksi: 'BATAL'; id: string; line: LineRow } | { aksi: 'KUTIPAN'; id: string; account: string; net: string; client: string } | { aksi: 'POTONGAN'; id: string; claim: ClaimRow };

const TABS: Tab[] = ['mutasi', 'belum', 'potongan'];

function RecordLine({ branch, accounts, evidence, flagged, today, back, onClose }: { branch: string; accounts: Props['accounts']; evidence: Statement[]; flagged: Flagged[]; today: string; back: string; onClose: () => void }) {
    const { t } = useTranslation();
    const [account, setAccount] = useState(accounts.length === 1 ? (accounts[0]?.handle ?? '') : '');
    const [statement, setStatement] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [amount, setAmount] = useState('');
    const [reference, setReference] = useState('');
    const [citing, setCiting] = useState<string[]>([]);
    const [uploading, setUploading] = useState(false);
    const record = useCommand<Payload>(() => '/keuangan/mutasi-bank', 'post');
    const answering = flagged.filter((row) => row.account === account);

    return (
        <Sheet open onOpenChange={(open) => { if (!open) onClose(); }}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t('payments.action.record_line')}</SheetTitle>
                </SheetHeader>
                <form className="flex min-w-0 flex-col gap-6" noValidate onSubmit={(event) => {
                    event.preventDefault();
                    record.submit({ perusahaan: branch, rekening: account, bukti: statement, tanggal: date.date, tanggal_otomatis: !date.chosen, jumlah: amount, referensi: reference, pembayaran: citing }, onClose);
                }}>
                    <FormField label={t('pages.pg_046.account')} required error={record.errors.rekening}>
                        {(control) => (
                            <NativeSelect {...control} value={account} onChange={(event) => { setAccount(event.target.value); setCiting([]); }}>
                                <option value="">{t('forms.choose')}</option>
                                {accounts.map((option) => <option key={option.handle} value={option.handle}>{option.label}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                    <FormField label={t('payments.line.statement')} required help={t('payments.line.statement_help')} error={record.errors.bukti}>
                        {(control) => (
                            <NativeSelect {...control} value={statement} onChange={(event) => { setStatement(event.target.value); }}>
                                <option value="">{t('forms.choose')}</option>
                                {evidence.map((option) => <option key={option.id} value={option.id}>{`${option.issuer}${option.reference === null ? '' : ` · ${option.reference}`} · ${dateLong(option.date)}`}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                    <Button variant="outline" size="sm" className="self-start" onClick={() => { setUploading(true); }}>
                        <Icon icon={Upload} size="sm" />
                        {t('payments.action.upload_statement')}
                    </Button>
                    <BusinessDateControl today={today} value={date} onChange={setDate} />
                    {record.errors.tanggal !== undefined && <p className="text-body text-danger">{record.errors.tanggal}</p>}
                    <FormField label={t('pages.pg_046.amount')} required error={record.errors.jumlah}>
                        {(control) => <DecimalInput {...control} scale={2} value={amount} onValueChange={(value) => { setAmount(value ?? ''); }} />}
                    </FormField>
                    <FormField label={t('payments.line.reference')} required help={t('payments.line.reference_help')} error={record.errors.referensi}>
                        {(control) => <Input {...control} value={reference} maxLength={120} autoComplete="off" onChange={(event) => { setReference(event.target.value); }} />}
                    </FormField>
                    {answering.length > 0 && (
                        <FormSection title={t('payments.line.citing')}>
                            <p className="text-body text-text-secondary">{t('payments.line.citing_help')}</p>
                            {answering.map((row) => (
                                <div key={row.id} className="flex items-start gap-3">
                                    <Checkbox id={`citing-${row.id}`} checked={citing.includes(row.id)} onCheckedChange={(checked) => { setCiting((current) => (checked ? [...current, row.id] : current.filter((id) => id !== row.id))); }} />
                                    <label htmlFor={`citing-${row.id}`} className="text-body text-text-primary">{`${dateLong(row.date)} · ${row.client} · ${money(row.net)}`}</label>
                                </div>
                            ))}
                        </FormSection>
                    )}
                    <Answered command={record} />
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="outline" onClick={onClose}>{t('actions.cancel')}</Button>
                        <Button type="submit" pending={record.pending} disabled={record.frozen}>{t('payments.action.record_line')}</Button>
                    </div>
                </form>
                {uploading && <ProofSheet open onOpenChange={setUploading} company={branch} types={['BANK_STATEMENT']} today={today} back={back} onRegistered={(id) => { setStatement(id); setUploading(false); }} />}
            </SheetContent>
        </Sheet>
    );
}

export default function BankStatement({ branches, branch, tab, rows, more, after, cursor_failed: cursorFailed, accounts, open_lines: openLines, flagged, evidence, today, can }: Props) {
    const { t } = useTranslation();
    const [base, setBase] = useState(rows);
    const [shown, setShown] = useState(rows);
    const [next, setNext] = useState(more);
    const [appending, setAppending] = useState(false);
    const [recording, setRecording] = useState(false);
    const [correcting, setCorrecting] = useState<Correction | null>(null);
    const [reason, setReason] = useState('');
    const [line, setLine] = useState('');
    const [amount, setAmount] = useState('');
    const correct = useCommand<Payload>(() => `/keuangan/mutasi-bank/${correcting?.id ?? ''}/koreksi`, 'post');

    if (base !== rows) {
        setBase(rows);
        setShown(appending ? [...shown, ...rows.filter((row) => !shown.some((known) => known.key === row.key))] : rows);
        setNext(more);
        setAppending(false);
    }

    const query = (change: Record<string, string | null>) => {
        const params = new URLSearchParams();

        for (const [key, value] of Object.entries({ perusahaan: branch, tab: tab === 'mutasi' ? null : tab, ...change })) {
            if (value !== null && value !== '') params.set(key, value);
        }

        const text = params.toString();

        return `/keuangan/mutasi-bank${text === '' ? '' : `?${text}`}`;
    };
    const visit = (change: Record<string, string | null>) => { router.get(query(change), {}, { preserveState: true }); };
    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        router.reload({ only: ['rows', 'more', 'after', 'cursor_failed'], data: { setelah: next } });
    };
    const back = query({ setelah: after });
    const open = (next: Correction) => { correct.clear(); setReason(''); setLine(''); setAmount(next.aksi === 'POTONGAN' ? next.claim.amount : ''); setCorrecting(next); };
    const rowActions = (row: Row): MenuAction[] => {
        if (row.kind === 'line') {
            return [
                ...(row.can_void ? [{ label: t('payments.action.void_line'), tone: 'danger' as const, onSelect: () => { open({ aksi: 'BATAL', id: row.handle, line: row }); } }] : []),
                ...(can.correct ? row.citing.map((citing) => ({ label: t('payments.action.correct_citation_of', { klien: citing.client }), onSelect: () => { open({ aksi: 'KUTIPAN', id: citing.id, account: row.account, net: citing.net, client: citing.client }); } })) : []),
            ];
        }

        if (row.kind === 'payment') {
            return row.can_cite ? [{ label: t('payments.action.cite'), onSelect: () => { open({ aksi: 'KUTIPAN', id: row.id, account: row.account_handle, net: row.net, client: row.client }); } }] : [];
        }

        return row.can_supersede ? [{ label: t('payments.action.supersede'), onSelect: () => { open({ aksi: 'POTONGAN', id: row.payment, claim: row }); } }] : [];
    };
    const reference = (row: Row) => row.kind === 'claim' ? row.type : (row.reference ?? '—');
    const figure = (row: Row) => row.kind === 'payment' ? row.net : row.amount;
    const secondary = (row: Row) => {
        if (row.kind === 'line') {
            return row.state === 'VOIDED' ? t('payments.line.voided', { alasan: row.voided_reason ?? '—' }) : row.citing.length === 0 ? t('payments.line.uncited') : t('payments.line.cited', { jumlah: money(row.cited), klien: row.citing.map((citing) => citing.client).join(', ') });
        }

        return row.client;
    };
    const candidates = correcting?.aksi === 'KUTIPAN' ? openLines : [];

    return (
        <AppShell title={t('pages.pg_050.title')} back={{ href: '/keuangan/invoice', label: t('pages.pg_041.title') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_050.title')} back={{ label: t('dialogs.back'), href: '/keuangan/invoice' }} primary={branch !== null ? <Button onClick={() => { setRecording(true); }}><Icon icon={Plus} size="sm" />{t('payments.action.record_line')}</Button> : undefined} />
                <FinanceNav current="bank" />
                <Answered command={correct} />
                {branches.length > 1 && (
                    <FormField label={t('receivables.field.branch')} className="min-w-0 sm:w-80">
                        {(control) => (
                            <NativeSelect {...control} value={branch ?? ''} onChange={(event) => { visit({ perusahaan: event.target.value }); }}>
                                {branches.map((option) => <option key={option.id} value={option.id}>{`${option.code} · ${option.name}`}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                )}
                <Tabs value={tab} onValueChange={(value) => { visit({ tab: String(value) }); }}>
                    <TabsList>
                        {TABS.map((each) => <TabsTab key={each} value={each}>{t(`payments.bank_tab.${each}` as TranslationKey)}</TabsTab>)}
                    </TabsList>
                </Tabs>
                {cursorFailed && shown.length === 0 ? (
                    <ListContinuation state="cursor_failed" onLoadMore={loadMore} onReload={() => { visit({}); }} />
                ) : shown.length === 0 ? (
                    <RegionEmpty text={t(`payments.bank_empty.${tab}` as TranslationKey)} />
                ) : (
                    <>
                        <DataList<Row>
                            caption={t('pages.pg_050.title')}
                            rows={shown}
                            rowKey={(row) => row.key}
                            rowHref={() => null}
                            rowActions={rowActions}
                            columns={[
                                { key: 'date', header: t('pages.pg_045.col_date'), cell: (row) => <DateText value={row.date} /> },
                                { key: 'reference', header: t(tab === 'potongan' ? 'payments.claim.class' : 'pages.pg_046.reference'), cell: (row) => <Code>{reference(row)}</Code> },
                                { key: 'account', header: t(tab === 'mutasi' ? 'pages.pg_046.account' : 'invoices.field.client'), priority: 'low', cell: (row) => (row.kind === 'line' ? row.account : row.client) },
                                { key: 'state', header: t('invoices.field.state'), cell: secondary },
                                { key: 'amount', header: t('pages.pg_046.amount'), numeric: true, cell: (row) => <Money value={figure(row)} /> },
                            ]}
                            dense={(row) => ({ title: reference(row), secondary: secondary(row), figure: <Money value={figure(row)} /> })}
                        />
                        <ListContinuation state={cursorFailed ? 'cursor_failed' : next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={() => { visit({}); }} />
                    </>
                )}
            </div>
            {recording && branch !== null && <RecordLine branch={branch} accounts={accounts} evidence={evidence} flagged={flagged} today={today} back={query({})} onClose={() => { setRecording(false); }} />}
            <ConfirmCommand
                open={correcting !== null}
                onOpenChange={(next) => { if (!next) setCorrecting(null); }}
                title={correcting === null ? '' : t(`payments.correct.${correcting.aksi}` as TranslationKey)}
                consequence={correcting === null ? '' : t(`payments.correct.${correcting.aksi}_line` as TranslationKey)}
                items={correcting?.aksi === 'KUTIPAN' ? (
                    <FormField label={t('pages.pg_046.statement_line')} required error={correct.errors.mutasi}>
                        {(control) => (
                            <NativeSelect {...control} value={line} onChange={(event) => { setLine(event.target.value); }}>
                                <option value="">{t('forms.choose')}</option>
                                {candidates.map((option) => <option key={option.handle} value={option.handle}>{t('payments.line.option', { tanggal: dateLong(option.date), jumlah: money(option.uncited), referensi: option.reference })}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                ) : correcting?.aksi === 'POTONGAN' ? (
                    <FormField label={t('payments.correct.new_amount')} required error={correct.errors.jumlah}>
                        {(control) => <DecimalInput {...control} scale={2} value={amount} onValueChange={(value) => { setAmount(value ?? ''); }} />}
                    </FormField>
                ) : undefined}
                reason={{ value: reason, onChange: setReason, error: correct.errors.alasan ?? null }}
                confirmLabel={t('payments.correct.confirm')}
                destructive={correcting?.aksi === 'BATAL'}
                pending={correct.pending}
                answer={correct.answer}
                onConfirm={() => {
                    if (correcting === null) return;
                    correct.submit({ aksi: correcting.aksi, alasan: reason, kembali: back, ...(correcting.aksi === 'KUTIPAN' ? { mutasi: line } : {}), ...(correcting.aksi === 'POTONGAN' ? { potongan: correcting.claim.handle, jumlah: amount } : {}) }, () => { setCorrecting(null); });
                }}
            />
        </AppShell>
    );
}
