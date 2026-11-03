// PG-048 *Kredit Pelanggan* (TASK-053; ROUTE-048; NAV-048; SCR-44; J-FIN-06; CALC-06; WF-FIN-06): credit per client and company, derived and never stored, each source with its trail —
// a payment or an opening credit —; "Terapkan ke Invoice" (ROUTE-252) to an issued invoice of the same client and company; "Kembalikan Dana" (ROUTE-256) with its transfer evidence and
// reason, the refund final while it stands (L-46); a refund's shortfall on its case; per project what has arisen, what is left and what is held, "Tahan Kredit" (ROUTE-264; DIR-104).
// No control deletes credit or a refund (FS-02).
import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { DecimalInput, FormField } from '@/components/form';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateText, Money } from '@/components/values';
import { FinanceNav } from '@/features/fin/finance-nav';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Branch = { id: string; code: string; name: string };
type Option = { id: string; label: string };
type Source = { id: string; kind: 'PAYMENT' | 'OPENING'; date: string; gross: string; capacity: string; evidenced: boolean };
type Refund = { id: string; date: string; amount: string; backed: string; shortfall: string; standing: boolean; case: string | null };
type Project = { id: string; number: string; title: string; state: string; arisen: string; left: string; held: string };
type Invoice = { id: string; number: string | null; project_number: string | null; outstanding: string };
type Group = {
    key: string;
    client: { id: string; name: string };
    company: Branch | null;
    credit: string;
    sources: Source[];
    refunds: Refund[];
    projects: Project[];
    accounts: Option[];
    proofs: Option[];
    invoices: Invoice[];
    can: { apply: boolean; refund: boolean; hold: boolean };
};
type Props = { page: { branches: Branch[]; branch: string | null; groups: Group[]; more: string | null; today: string } };
type Payload = Record<string, FormDataConvertible>;
type Acting = { kind: 'refund' | 'apply'; group: Group; source: Source } | { kind: 'hold'; group: Group; project: Project };

const positive = (value: string) => /[1-9]/.test(value);
const above = (a: string, b: string) => Number(a) > Number(b);

/** The dialog closes once an answer arrives, so a refusal shows in place (IP-01). */
function useClosedOnAnswer(answer: unknown, close: () => void) {
    const [seen, setSeen] = useState(answer);

    if (seen !== answer) {
        setSeen(answer);

        if (answer !== null) close();
    }
}

export default function CustomerCredit({ page }: Props) {
    const { t } = useTranslation();
    const [acting, setActing] = useState<Acting | null>(null);
    const [amount, setAmount] = useState('');
    const [account, setAccount] = useState('');
    const [proof, setProof] = useState('');
    const [invoice, setInvoice] = useState('');
    const [reason, setReason] = useState('');
    const refund = useCommand<Payload>(() => (acting?.kind === 'refund' ? `/keuangan/kredit-pelanggan/${acting.source.id}/kembalikan` : ''), 'post');
    const apply = useCommand<Payload>(() => (acting?.kind === 'apply' ? `/keuangan/pembayaran/${acting.source.id}/terapkan` : ''), 'post');
    const hold = useCommand<Payload>(() => (acting?.kind === 'hold' ? `/keuangan/kredit-pelanggan/proyek/${acting.project.id}/tahan` : ''), 'post');
    const answer = refund.answer ?? apply.answer ?? hold.answer;
    // INT-017: an outcome that never arrived keeps its target, so "Coba Lagi" resends the same command before any other may start (IP-02).
    const pending = [refund, apply, hold].find((command) => command.unknown) ?? null;
    useClosedOnAnswer(answer, () => { if (![refund, apply, hold].some((command) => command.unknown)) setActing(null); });

    const open = (next: Acting) => {
        if (pending !== null) return;
        refund.clear();
        apply.clear();
        hold.clear();
        setAmount(next.kind === 'hold' ? '' : next.source.capacity);
        setAccount(next.group.accounts[0]?.id ?? '');
        setProof('');
        setInvoice(next.group.invoices[0]?.id ?? '');
        setReason('');
        setActing(next);
    };

    return (
        <AppShell title={t('pages.pg_048.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_048.title')} />
                <FinanceNav current="credit" />
                {page.branches.length > 1 && (
                    <FormField label={t('credit.field.branch')} className="w-64">
                        {(control) => (
                            <NativeSelect {...control} value={page.branch ?? ''} onChange={(event) => { router.get('/keuangan/kredit-pelanggan', event.target.value === '' ? {} : { cabang: event.target.value }); }}>
                                <option value="">{t('credit.field.all_branches')}</option>
                                {page.branches.map((branch) => <option key={branch.id} value={branch.id}>{`${branch.code} · ${branch.name}`}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                )}
                <p className="text-body text-text-secondary">{t('credit.intro')}</p>
                {answer !== null && (acting === null || pending !== null) && <OutcomeMessage answer={answer} action={pending === null ? undefined : <Button variant="outline" size="sm" onClick={pending.retry}>{t('actions.try_again')}</Button>} />}
                {page.groups.length === 0 ? <RegionEmpty text={t('credit.none')} /> : (
                    <ol className="flex min-w-0 flex-col gap-4">
                        {page.groups.map((group) => (
                            <li key={group.key} data-slot="credit-group" className="flex min-w-0 flex-col gap-3 rounded-md border border-border-subtle p-4">
                                <div className="flex min-w-0 flex-wrap items-baseline justify-between gap-2">
                                    <h2 className="text-subhead">{group.client.name}{group.company !== null && <span className="text-body text-text-secondary">{` · ${group.company.code}`}</span>}</h2>
                                    <span className="text-body">{t('credit.total')} <Money value={group.credit} /></span>
                                </div>
                                <section data-slot="credit-sources" className="flex min-w-0 flex-col gap-1">
                                    <h3 className="text-body font-medium">{t('credit.section.sources')}</h3>
                                    {group.sources.length === 0 ? <p className="text-body text-text-secondary">{t('credit.no_sources')}</p> : (
                                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                            {group.sources.map((source) => (
                                                <li key={source.id} data-slot="credit-source" className="flex min-w-0 flex-wrap items-center gap-2 py-2 text-body">
                                                    <span>{t(source.kind === 'PAYMENT' ? 'credit.source.payment' : 'credit.source.opening')}</span>
                                                    <DateText value={source.date} />
                                                    <Money value={source.capacity} />
                                                    {!source.evidenced && <Badge>{t('credit.unevidenced')}</Badge>}
                                                    {group.can.apply && group.invoices.length > 0 && <Button variant="outline" size="sm" onClick={() => { open({ kind: 'apply', group, source }); }}>{t('actions.apply_to_invoice')}</Button>}
                                                    {group.can.refund && source.evidenced && <Button variant="outline" size="sm" onClick={() => { open({ kind: 'refund', group, source }); }}>{t('actions.refund')}</Button>}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </section>
                                {group.refunds.length > 0 && (
                                    <section data-slot="credit-refunds" className="flex min-w-0 flex-col gap-1">
                                        <h3 className="text-body font-medium">{t('credit.section.refunds')}</h3>
                                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                            {group.refunds.map((row) => (
                                                <li key={row.id} data-slot="credit-refund" className="flex min-w-0 flex-wrap items-center gap-2 py-2 text-body">
                                                    <DateText value={row.date} />
                                                    <Money value={row.amount} />
                                                    <Badge>{t(row.standing ? 'credit.refund.standing' : 'credit.refund.contra')}</Badge>
                                                    {positive(row.shortfall) && (
                                                        <span className="text-danger-fg">{t('credit.refund.shortfall')} <Money value={row.shortfall} />{row.case !== null && <> · <a className="underline" href={`/dokumen/kasus?kasus=${row.case}`}>{t('credit.refund.case')}</a></>}</span>
                                                    )}
                                                    {row.standing && <span className="text-caption text-text-secondary">{t('credit.refund.final')}</span>}
                                                </li>
                                            ))}
                                        </ul>
                                    </section>
                                )}
                                {group.projects.length > 0 && (
                                    <section data-slot="credit-projects" className="flex min-w-0 flex-col gap-1">
                                        <h3 className="text-body font-medium">{t('credit.section.projects')}</h3>
                                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                            {group.projects.map((project) => (
                                                <li key={project.id} data-slot="credit-project" className="flex min-w-0 flex-wrap items-center gap-2 py-2 text-body">
                                                    <a className="font-medium underline" href={`/proyek/${project.id}`}><Code>{project.number}</Code></a>
                                                    <span className="text-text-secondary">{project.title}</span>
                                                    <span>{t('credit.project.left')} <Money value={project.left} /></span>
                                                    <span className="text-caption text-text-secondary">{t('credit.project.arisen')} <Money value={project.arisen} /> · {t('credit.project.held')} <Money value={project.held} /></span>
                                                    {group.can.hold && project.state === 'ACTIVE' && positive(project.left) && above(project.arisen, project.held) && (
                                                        <Button variant="outline" size="sm" onClick={() => { open({ kind: 'hold', group, project }); }}>{t('actions.hold_credit')}</Button>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    </section>
                                )}
                            </li>
                        ))}
                    </ol>
                )}
                {page.more !== null && <Button variant="outline" className="self-start" render={<a href={`/keuangan/kredit-pelanggan?${new URLSearchParams({ ...(page.branch === null ? {} : { cabang: page.branch }), setelah: page.more }).toString()}`} />}>{t('actions.load_more')}</Button>}
            </div>
            <ConfirmCommand
                open={acting?.kind === 'refund' && !refund.unknown}
                onOpenChange={(next) => { if (!next) setActing(null); }}
                title={t('actions.refund')}
                consequence={t('credit.confirm.refund')}
                confirmLabel={t('actions.refund')}
                pending={refund.pending}
                blocked={acting?.kind !== 'refund' || !positive(amount) || account === '' || proof === ''}
                items={acting?.kind !== 'refund' ? null : (
                    <div className="flex min-w-0 flex-col gap-3">
                        <FormField label={t('credit.field.amount')} help={t('credit.field.capacity', { nilai: acting.source.capacity })} error={refund.errors.jumlah}>
                            {(control) => <DecimalInput {...control} scale={2} value={amount} onValueChange={(next) => { setAmount(next ?? ''); }} />}
                        </FormField>
                        <FormField label={t('credit.field.account')} error={refund.errors.rekening}>
                            {(control) => (
                                <NativeSelect {...control} value={account} onChange={(event) => { setAccount(event.target.value); }}>
                                    {acting.group.accounts.map((row) => <option key={row.id} value={row.id}>{row.label}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        <FormField label={t('credit.field.proof')} error={refund.errors.bukti}>
                            {(control) => (
                                <NativeSelect {...control} value={proof} onChange={(event) => { setProof(event.target.value); }}>
                                    <option value="">{t('dispatch.substitute.choose')}</option>
                                    {acting.group.proofs.map((row) => <option key={row.id} value={row.id}>{row.label}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        <a className="self-start text-body underline" href="/dokumen/bukti">{t('credit.upload_proof')}</a>
                    </div>
                )}
                reason={{ value: reason, onChange: setReason, error: refund.errors.alasan ?? null }}
                onConfirm={() => { refund.submit({ jumlah: amount, rekening: account, bukti: proof, alasan: reason, tanggal: page.today, tanggal_otomatis: true }); }}
            />
            <ConfirmCommand
                open={acting?.kind === 'apply' && !apply.unknown}
                onOpenChange={(next) => { if (!next) setActing(null); }}
                title={t('actions.apply_to_invoice')}
                consequence={t('credit.confirm.apply')}
                confirmLabel={t('actions.apply_to_invoice')}
                pending={apply.pending}
                blocked={acting?.kind !== 'apply' || !positive(amount) || invoice === ''}
                items={acting?.kind !== 'apply' ? null : (
                    <div className="flex min-w-0 flex-col gap-3">
                        <FormField label={t('credit.field.invoice')} error={apply.errors['penerapan.0.invoice']}>
                            {(control) => (
                                <NativeSelect {...control} value={invoice} onChange={(event) => { setInvoice(event.target.value); }}>
                                    {acting.group.invoices.map((row) => <option key={row.id} value={row.id}>{`${row.number ?? row.id.slice(0, 8)}${row.project_number === null ? '' : ` · ${row.project_number}`} · ${row.outstanding}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        <FormField label={t('credit.field.amount')} help={t('credit.field.capacity', { nilai: acting.source.capacity })} error={apply.errors['penerapan.0.jumlah']}>
                            {(control) => <DecimalInput {...control} scale={2} value={amount} onValueChange={(next) => { setAmount(next ?? ''); }} />}
                        </FormField>
                    </div>
                )}
                onConfirm={() => { apply.submit({ tanggal: page.today, tanggal_otomatis: true, penerapan: [{ invoice, jumlah: amount }], kembali: '/keuangan/kredit-pelanggan' }); }}
            />
            <ConfirmCommand
                open={acting?.kind === 'hold' && !hold.unknown}
                onOpenChange={(next) => { if (!next) setActing(null); }}
                title={t('actions.hold_credit')}
                consequence={acting?.kind === 'hold' ? t('credit.confirm.hold', { nilai: acting.project.left, timbul: acting.project.arisen }) : ''}
                confirmLabel={t('actions.hold_credit')}
                pending={hold.pending}
                reason={{ value: reason, onChange: setReason, error: hold.errors.alasan ?? null }}
                onConfirm={() => { hold.submit({ alasan: reason }); }}
            />
        </AppShell>
    );
}
