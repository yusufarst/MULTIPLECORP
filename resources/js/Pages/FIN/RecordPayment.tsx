// PG-046 *Catat Pembayaran* (ROUTE-046, ROUTE-251; NAV-046; SCR-42; WF-FIN-03; J-FIN-03; S2-J06; TASK-051): the company chosen here, never from the working filter (CS-03), the client, date, amount, method, the company's own account, the statement line it matches, reference, proof or the
// reason it has none (L-29), and in the same form the applications — amounts per issued invoice of that client and company, the rest staying *Kredit Pelanggan* (SF-APPLY) — sent as one command after MSG-39 (IP-08). One column with its application step on the phone, the applications beside the
// payment on the desktop. A likely duplicate is IP-05's warning (UXS-16); leaving with unsent input asks MSG-35. A second company's invoices are never offered.
import { router } from '@inertiajs/react';
import { Plus, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { ActionBar } from '@/components/action-bar';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { BusinessDateControl, type BusinessDate } from '@/components/date-field';
import { DecimalInput, FormField, FormSection } from '@/components/form';
import { type LookupResult } from '@/components/lookup';
import { DuplicateWarning, type DuplicateMatch } from '@/components/notices';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { PartyLookup } from '@/components/parties';
import { Disclosure, PositionBlock } from '@/components/record-blocks';
import { RegionEmpty } from '@/components/region-state';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { Code, Money } from '@/components/values';
import { ProofSheet } from '@/features/fin/proof-sheet';
import AppShell from '@/layouts/AppShell';
import { add } from '@/lib/decimal';
import { dateLong, money } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { previousPage } from '@/lib/previous-page';
import { isBackground } from '@/lib/session-core';
import { useCommand } from '@/lib/use-command';

type Company = { id: string; code: string; name: string };
type Invoice = { id: string; number: string | null; project_number: string | null; outstanding: string; billed: boolean; kuitansi: boolean };
type Account = { handle: string; label: string };
type Line = { handle: string; account: string; date: string; amount: string; uncited: string; reference: string };
type Proof = { id: string; type: string; issuer: string; reference: string | null; date: string };
type Props = {
    companies: Company[];
    company: string | null;
    client: { id: string; name: string } | null;
    origin: string | null;
    invoices: Invoice[];
    accounts: Account[];
    lines: Line[];
    evidence: Proof[];
    tax_types: { code: string }[];
    recoveries: { id: string; number: string | null }[];
    rerecordable: { id: string; date: string; amount: string; contra: string }[];
    transfers: { id: string; date: string; left: string }[];
    today: string;
    can: { upload: boolean; override: boolean };
};
type Claim = { kelas: string; pajak: string; biaya: string; jumlah: string };
type Payload = Record<string, string | boolean | string[] | { invoice: string; jumlah: string }[] | { kelas: string; pajak: string | null; biaya: string | null; jumlah: string }[] | null>;

const METHODS = ['TRANSFER', 'CASH', 'VIRTUAL_ACCOUNT', 'OTHER'];
const PROOFS = ['PAYMENT_PROOF', 'TRANSFER_PROOF', 'BANK_STATEMENT'];

export default function RecordPayment({ companies, company, client, origin, invoices, accounts, lines, evidence, tax_types: taxTypes, recoveries, rerecordable, transfers, today, can }: Props) {
    const { t } = useTranslation();
    const [chosenClient, setChosenClient] = useState<LookupResult | null>(client === null ? null : { id: client.id, code: '', label: client.name });
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [amount, setAmount] = useState('');
    const [method, setMethod] = useState('TRANSFER');
    const [account, setAccount] = useState(accounts.length === 1 ? (accounts[0]?.handle ?? '') : '');
    const [line, setLine] = useState('');
    const [reference, setReference] = useState('');
    const [intermediary, setIntermediary] = useState('');
    const [proof, setProof] = useState('');
    const [noProof, setNoProof] = useState('');
    const [applied, setApplied] = useState<Record<string, string>>(origin !== null ? { [origin]: '' } : {});
    const [claims, setClaims] = useState<Claim[]>([]);
    const [recovery, setRecovery] = useState('');
    const [replacing, setReplacing] = useState('');
    const [transfer, setTransfer] = useState('');
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [overrideReason, setOverrideReason] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [leaving, setLeaving] = useState<(() => void) | null>(null);
    const departing = useRef(false);
    const record = useCommand<Payload>(() => '/keuangan/pembayaran', 'post');
    const matches = Array.isArray(record.state.matches) ? (record.state.matches as DuplicateMatch[]) : [];
    const warned = record.answer?.kind === 'REJECTED' && matches.length > 0;
    const applications = invoices.filter((invoice) => (applied[invoice.id] ?? '') !== '');
    const total = applications.length === 0 ? '0' : add(...applications.map((invoice) => applied[invoice.id] ?? '0'));
    const left = amount === '' ? '0' : add(amount, `-${total}`);
    const dirty = amount !== '' || reference !== '' || applications.length > 0 || proof !== '' || noProof !== '' || claims.length > 0;
    const [back] = useState(() => (origin !== null ? `/keuangan/invoice/${origin}` : (previousPage() ?? '/keuangan/pembayaran')));
    const open = lines.filter((row) => row.account === account);
    const cash = method === 'CASH';
    const claimed = claims.filter((claim) => claim.jumlah !== '');
    const claimedTotal = claimed.length === 0 ? '0' : add(...claimed.map((claim) => claim.jumlah));

    useEffect(() => {
        const off = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (!dirty || departing.current || visit.method !== 'get' || visit.only.length > 0 || isBackground(visit)) {
                return;
            }

            const href = visit.url.href;
            setLeaving(() => () => { router.visit(href); });

            return false;
        });
        const unload = (event: BeforeUnloadEvent) => {
            if (dirty && !departing.current) event.preventDefault();
        };
        window.addEventListener('beforeunload', unload);

        return () => {
            off();
            window.removeEventListener('beforeunload', unload);
        };
    }, [dirty]);

    // The lists follow the company and client: the input entered stays (NV-04).
    const refresh = (next: { perusahaan?: string | null; klien?: string | null }) => {
        router.reload({ only: ['company', 'client', 'invoices', 'accounts', 'lines', 'evidence', 'tax_types', 'recoveries', 'rerecordable', 'transfers', 'can'], data: { perusahaan: next.perusahaan ?? company ?? '', klien: next.klien ?? chosenClient?.id ?? '' } });
    };
    const chooseCompany = (value: string) => {
        setAccount('');
        setLine('');
        setProof('');
        setApplied({});
        refresh({ perusahaan: value });
    };
    const chooseClient = (next: LookupResult | null) => {
        setChosenClient(next);
        setApplied({});
        refresh({ klien: next?.id ?? '' });
    };
    const submit = () => {
        record.submit({
            perusahaan: company ?? '',
            klien: chosenClient?.id ?? '',
            tanggal: date.date,
            tanggal_otomatis: !date.chosen,
            jumlah: amount,
            metode: method,
            rekening: cash ? null : account === '' ? null : account,
            mutasi: cash || line === '' ? null : line,
            referensi: reference === '' ? null : reference,
            perantara: intermediary === '' ? null : intermediary,
            bukti: proof === '' ? [] : [proof],
            alasan_tanpa_bukti: noProof === '' ? null : noProof,
            potongan: claimed.map((claim) => ({ kelas: claim.kelas, pajak: claim.kelas === 'TAX' ? claim.pajak : null, biaya: claim.kelas === 'FEE' ? claim.biaya : null, jumlah: claim.jumlah })),
            penerapan: applications.map((invoice) => ({ invoice: invoice.id, jumlah: applied[invoice.id] ?? '' })),
            pemulihan: recovery === '' ? null : recovery,
            mengganti: replacing === '' ? null : replacing,
            transfer: transfer === '' ? null : transfer,
            ...(warned ? { acknowledged, reason: overrideReason } : {}),
        }, () => { departing.current = true; });
        setConfirming(false);
    };
    const invoiceName = (invoice: Invoice) => invoice.number ?? t('payments.invoice.unnumbered');
    const setClaim = (index: number, patch: Partial<Claim>) => { setClaims((current) => current.map((claim, at) => (at === index ? { ...claim, ...patch } : claim))); };

    return (
        <AppShell title={t('pages.pg_046.title')} back={{ href: back, label: t('dialogs.back') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_046.title')} back={{ label: t('dialogs.back'), href: back }} />
                <div className="grid min-w-0 gap-6 lg:grid-cols-2 lg:items-start">
                    <div className="flex min-w-0 flex-col gap-6">
                        <FormSection title={t('payments.section.fact')}>
                            <FormField label={t('payments.field.company')} required error={record.errors.perusahaan}>
                                {(control) => (
                                    <NativeSelect {...control} value={company ?? ''} disabled={origin !== null} onChange={(event) => { chooseCompany(event.target.value); }}>
                                        <option value="">{t('forms.choose')}</option>
                                        {companies.map((option) => <option key={option.id} value={option.id}>{`${option.code} · ${option.name}`}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                            <div id="field-klien" className="flex min-w-0 flex-col gap-1">
                                <PartyLookup type="client" label={t('payments.field.client')} value={chosenClient} onChoose={chooseClient} canCreate={false} onCreate={() => undefined} />
                                {record.errors.klien !== undefined && <p className="text-caption text-danger-fg">{record.errors.klien}</p>}
                            </div>
                            <BusinessDateControl today={today} value={date} onChange={setDate} />
                            {record.errors.tanggal !== undefined && <p className="text-body text-danger">{record.errors.tanggal}</p>}
                            <FormField label={t('pages.pg_046.amount')} required error={record.errors.jumlah}>
                                {(control) => <DecimalInput {...control} scale={2} value={amount} onValueChange={(value) => { setAmount(value ?? ''); }} />}
                            </FormField>
                            <FormField label={t('pages.pg_046.method')} required error={record.errors.metode}>
                                {(control) => (
                                    <NativeSelect {...control} value={method} onChange={(event) => { setMethod(event.target.value); }}>
                                        {METHODS.map((option) => <option key={option} value={option}>{t(`payments.method.${option}` as TranslationKey)}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                            {!cash && (
                                <FormField label={t('pages.pg_046.account')} required error={record.errors.rekening}>
                                    {(control) => (
                                        <NativeSelect {...control} value={account} onChange={(event) => { setAccount(event.target.value); setLine(''); }}>
                                            <option value="">{t('forms.choose')}</option>
                                            {accounts.map((option) => <option key={option.handle} value={option.handle}>{option.label}</option>)}
                                        </NativeSelect>
                                    )}
                                </FormField>
                            )}
                            {!cash && account !== '' && (
                                <FormField label={t('pages.pg_046.statement_line')} help={t('payments.field.line_help')} error={record.errors.mutasi}>
                                    {(control) => (
                                        <NativeSelect {...control} value={line} onChange={(event) => { setLine(event.target.value); }}>
                                            <option value="">{t('payments.field.no_line')}</option>
                                            {open.map((option) => <option key={option.handle} value={option.handle}>{t('payments.line.option', { tanggal: dateLong(option.date), jumlah: money(option.uncited), referensi: option.reference })}</option>)}
                                        </NativeSelect>
                                    )}
                                </FormField>
                            )}
                            <FormField label={t('pages.pg_046.reference')} error={record.errors.referensi}>
                                {(control) => <Input {...control} value={reference} maxLength={120} autoComplete="off" onChange={(event) => { setReference(event.target.value); }} />}
                            </FormField>
                        </FormSection>
                        <FormSection title={t('pages.pg_046.proof')}>
                            <FormField label={t('pages.pg_046.proof')} help={t('payments.field.proof_help')} error={record.errors.bukti}>
                                {(control) => (
                                    <NativeSelect {...control} value={proof} onChange={(event) => { setProof(event.target.value); }}>
                                        <option value="">{t('payments.field.no_proof')}</option>
                                        {evidence.map((option) => <option key={option.id} value={option.id}>{`${t(`evidence.type.${option.type}` as TranslationKey)} · ${option.issuer}${option.reference === null ? '' : ` · ${option.reference}`} · ${dateLong(option.date)}`}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                            {can.upload && company !== null && (
                                <Button variant="outline" size="sm" className="self-start" onClick={() => { setUploading(true); }}>
                                    <Icon icon={Upload} size="sm" />
                                    {t('payments.action.upload_proof')}
                                </Button>
                            )}
                            {proof === '' && line === '' && (
                                <FormField label={t('pages.pg_046.no_proof_reason')} required error={record.errors.alasan_tanpa_bukti}>
                                    {(control) => <Textarea {...control} rows={2} value={noProof} maxLength={500} onChange={(event) => { setNoProof(event.target.value); }} />}
                                </FormField>
                            )}
                        </FormSection>
                        <Disclosure title={t('pages.pg_046.net_paid')} invalid={Object.keys(record.errors).some((key) => key.startsWith('potongan'))}>
                            <div className="flex min-w-0 flex-col gap-4">
                                <p className="text-body text-text-secondary">{t('payments.claim.help')}</p>
                                {claims.map((claim, index) => (
                                    <div key={String(index)} className="grid min-w-0 gap-3 sm:grid-cols-3">
                                        <FormField label={t('payments.claim.class')}>
                                            {(control) => (
                                                <NativeSelect {...control} value={claim.kelas} onChange={(event) => { setClaim(index, { kelas: event.target.value }); }}>
                                                    <option value="FEE">{t('payments.claim.FEE')}</option>
                                                    <option value="TAX">{t('payments.claim.TAX')}</option>
                                                </NativeSelect>
                                            )}
                                        </FormField>
                                        {claim.kelas === 'TAX' ? (
                                            <FormField label={t('payments.claim.tax_type')} error={record.errors[`potongan.${String(index)}.pajak`]}>
                                                {(control) => (
                                                    <NativeSelect {...control} value={claim.pajak} onChange={(event) => { setClaim(index, { pajak: event.target.value }); }}>
                                                        <option value="">{t('forms.choose')}</option>
                                                        {taxTypes.map((option) => <option key={option.code} value={option.code}>{option.code}</option>)}
                                                    </NativeSelect>
                                                )}
                                            </FormField>
                                        ) : (
                                            <FormField label={t('payments.claim.fee_type')} error={record.errors[`potongan.${String(index)}.biaya`]}>
                                                {(control) => <Input {...control} value={claim.biaya} maxLength={60} onChange={(event) => { setClaim(index, { biaya: event.target.value }); }} />}
                                            </FormField>
                                        )}
                                        <FormField label={t('pages.pg_046.amount')} error={record.errors[`potongan.${String(index)}.jumlah`]}>
                                            {(control) => <DecimalInput {...control} scale={2} value={claim.jumlah} onValueChange={(value) => { setClaim(index, { jumlah: value ?? '' }); }} />}
                                        </FormField>
                                    </div>
                                ))}
                                <Button variant="outline" size="sm" className="self-start" onClick={() => { setClaims((current) => [...current, { kelas: 'FEE', pajak: '', biaya: '', jumlah: '' }]); }}>
                                    <Icon icon={Plus} size="sm" />
                                    {t('payments.claim.add')}
                                </Button>
                                {claimed.length > 0 && amount !== '' && <p className="text-body text-text-secondary">{t('payments.claim.gross', { jumlah: money(add(amount, claimedTotal)) })}</p>}
                            </div>
                        </Disclosure>
                        <Disclosure title={t('payments.section.more')} invalid={['pemulihan', 'mengganti', 'transfer', 'perantara'].some((key) => record.errors[key] !== undefined)}>
                            <div className="flex min-w-0 flex-col gap-4">
                                <FormField label={t('payments.field.intermediary')} help={t('payments.field.intermediary_help')} error={record.errors.perantara}>
                                    {(control) => <Input {...control} value={intermediary} maxLength={200} onChange={(event) => { setIntermediary(event.target.value); }} />}
                                </FormField>
                                {recoveries.length > 0 && (
                                    <FormField label={t('payments.field.recovery')} help={t('payments.field.recovery_help')} error={record.errors.pemulihan}>
                                        {(control) => (
                                            <NativeSelect {...control} value={recovery} onChange={(event) => { setRecovery(event.target.value); }}>
                                                <option value="">{t('payments.field.none')}</option>
                                                {recoveries.map((option) => <option key={option.id} value={option.id}>{option.number ?? option.id}</option>)}
                                            </NativeSelect>
                                        )}
                                    </FormField>
                                )}
                                {rerecordable.length > 0 && (
                                    <FormField label={t('payments.field.replacing')} help={t('payments.field.replacing_help')} error={record.errors.mengganti}>
                                        {(control) => (
                                            <NativeSelect {...control} value={replacing} onChange={(event) => { setReplacing(event.target.value); }}>
                                                <option value="">{t('payments.field.none')}</option>
                                                {rerecordable.map((option) => <option key={option.id} value={option.id}>{`${dateLong(option.date)} · ${money(option.amount)}`}</option>)}
                                            </NativeSelect>
                                        )}
                                    </FormField>
                                )}
                                {transfers.length > 0 && (
                                    <FormField label={t('payments.field.transfer')} help={t('payments.field.transfer_help')} error={record.errors.transfer}>
                                        {(control) => (
                                            <NativeSelect {...control} value={transfer} onChange={(event) => { setTransfer(event.target.value); }}>
                                                <option value="">{t('payments.field.none')}</option>
                                                {transfers.map((option) => <option key={option.id} value={option.id}>{`${dateLong(option.date)} · ${money(option.left)}`}</option>)}
                                            </NativeSelect>
                                        )}
                                    </FormField>
                                )}
                            </div>
                        </Disclosure>
                    </div>
                    <div className="flex min-w-0 flex-col gap-6">
                        <FormSection title={t('pages.pg_046.applications')}>
                            {chosenClient === null || company === null ? (
                                <RegionEmpty text={t('payments.applications.choose')} />
                            ) : invoices.length === 0 ? (
                                <RegionEmpty text={t('payments.applications.none')} />
                            ) : (
                                <ul role="list" aria-label={t('pages.pg_046.applications')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                    {invoices.map((invoice, index) => (
                                        <li key={invoice.id} data-testid="application" className="grid min-w-0 gap-2 py-3 sm:grid-cols-2 sm:items-center">
                                            <div className="flex min-w-0 flex-col gap-1">
                                                <Code>{invoiceName(invoice)}</Code>
                                                <span className="text-caption text-text-secondary">{`${invoice.project_number === null ? '' : `${invoice.project_number} · `}${t(invoice.billed ? 'payments.invoice.billed' : 'payments.invoice.unbilled')}`}</span>
                                                <span className="text-body text-text-secondary">{t('payments.invoice.outstanding', { jumlah: money(invoice.outstanding) })}</span>
                                                {invoice.kuitansi && <span className="text-caption text-text-secondary">{t('payments.invoice.kuitansi')}</span>}
                                            </div>
                                            <FormField label={t('pages.pg_046.col_applied')} error={record.errors[`penerapan.${String(index)}.invoice`] ?? record.errors[`penerapan.${String(index)}.jumlah`]}>
                                                {(control) => <DecimalInput {...control} scale={2} value={applied[invoice.id] ?? ''} onValueChange={(value) => { setApplied((current) => ({ ...current, [invoice.id]: value ?? '' })); }} />}
                                            </FormField>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <PositionBlock title={t('payments.position.title')} lines={[
                                { label: t('pages.pg_046.amount'), value: <Money value={amount === '' ? '0' : amount} /> },
                                { label: t('pages.pg_046.col_applied'), value: <Money value={total} /> },
                                { label: t('glossary.gl_141'), value: <Money value={left.startsWith('-') ? '0' : left} />, emphasis: true },
                            ]} />
                            {left.startsWith('-') && <p className="text-body text-danger">{t('payments.applications.above', { jumlah: money(left.slice(1)) })}</p>}
                        </FormSection>
                        {warned ? (
                            <DuplicateWarning matches={matches} message={record.answer?.text} acknowledged={acknowledged} onAcknowledge={setAcknowledged} reason={{ value: overrideReason, onChange: setOverrideReason, error: record.errors.reason ?? null }} canContinue={record.state.override === true} />
                        ) : (
                            record.answer !== null && <OutcomeMessage answer={record.answer} action={record.unknown ? <Button variant="outline" size="sm" onClick={record.retry}>{t('actions.try_again')}</Button> : undefined} />
                        )}
                        <ActionBar label={t('pages.pg_046.title')}>
                            <Button variant="outline" onClick={() => { router.visit(back); }}>{t('actions.cancel')}</Button>
                            <Button disabled={record.frozen} pending={record.pending} onClick={() => { setConfirming(true); }}>{t('actions.record_payment')}</Button>
                        </ActionBar>
                    </div>
                </div>
            </div>
            <ConfirmCommand
                open={confirming}
                onOpenChange={setConfirming}
                title={t('payments.confirm.title', { jumlah: money(amount === '' ? '0' : amount) })}
                consequence={t('payments.confirm.consequence')}
                confirmLabel={t('actions.record_payment')}
                pending={record.pending}
                onConfirm={submit}
            />
            {uploading && company !== null && <ProofSheet open onOpenChange={setUploading} company={company} types={PROOFS} today={today} back={`/keuangan/pembayaran/baru?${new URLSearchParams({ perusahaan: company, klien: chosenClient?.id ?? '' }).toString()}`} onRegistered={(id) => { setProof(id); setUploading(false); }} />}
            <UnsentInputDialog open={leaving !== null} kind="leave" onStay={() => { setLeaving(null); }} onLeave={() => { const go = leaving; departing.current = true; setLeaving(null); go?.(); }} />
        </AppShell>
    );
}
