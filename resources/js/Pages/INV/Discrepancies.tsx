// PG-029 *Selisih & Temuan* (ROUTE-029, ROUTE-197–ROUTE-200; NAV-029; SCR-27; SF-UNATTRIBUTED; AX-07; AX-37; OD-08–OD-10; PJ-07; UXS-39; TASK-043): the open findings and pending cases with their recognition snapshot as PJ-07 projects it — the number and identity of other candidate companies only to the Owner. "Tetapkan Surplus" and "Tetapkan Perusahaan" are the Owner's, "Tetapkan dengan Bukti" an ADM+ evidence holder's, "Balikkan Penetapan" the exact reversal, each with its reason (IP-09); a control is absent without its capability.
import type { FormDataConvertible } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { type BusinessDate, BusinessDateControl } from '@/components/date-field';
import { DecimalInput, FormField } from '@/components/form';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, Money } from '@/components/values';
import { ProofSheet } from '@/features/fin/proof-sheet';
import { ChoicesFind } from '@/features/inv/dispatch-line';
import AppShell from '@/layouts/AppShell';
import { previousPage } from '@/lib/previous-page';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Finding = { id: string; product: string; unit: string; rack: string; condition: 'USABLE' | 'UNUSABLE'; qty: string; date: string; serialized: boolean; scale: number; losses: { id: string; date: string; code: string; open: string }[] };
type Page<Row> = { rows: Row[]; more: string | null; cursor_failed: boolean };
type Candidate = { lot: string; code: string; company: string; qty: string; cost: string | null };
type Exposure = { id: string; company: string; exposure: string; assigned: string };
type LiveLot = { lot: string; code: string; qty: string; company: string; company_id: string };
type Case = { id: string; kind: 'LOSS' | 'CONDITION_CHANGE'; product: string; unit: string; rack: string; condition: 'USABLE' | 'UNUSABLE'; date: string; reason: string; actor: string; recognized: string; found: string; resolved: string; pending: string; candidates: Candidate[]; exposures: Exposure[]; lots: LiveLot[] };
type Resolution = { id: string; kind: 'OWNER_ATTRIBUTION' | 'EVIDENCE_IDENTIFIED'; product: string; rack: string; date: string; reason: string | null; actor: string | null; lines: { company: string; qty: string }[]; reversible: boolean };
type Can = { surplus: boolean; resolve: boolean; evidence: boolean; upload: boolean };
type Props = {
    findings: Page<Finding>;
    cases: Page<Case>;
    resolutions: Page<Resolution>;
    companies: { id: string; code: string; name: string }[];
    proofs: { find: string | null; rows: { id: string; label: string }[]; more: boolean };
    today: string;
    can: Can;
};

const figure = (value: string) => (/^\d+(\.\d+)?$/.test(value) ? value : '0');

/** A command's success on the page its answer opened, once: the row that sent it may be gone (IP-01). */
function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

/** The confirmation closes once an answer arrives, so a refusal shows at its row (IP-01). */
function useClosedOnAnswer(answer: unknown, close: () => void) {
    const [seen, setSeen] = useState(answer);

    if (seen !== answer) {
        setSeen(answer);

        if (answer !== null) close();
    }
}

function FindingRow({ finding, companies, today, can }: { finding: Finding; companies: Props['companies']; today: string; can: Can }) {
    const { t } = useTranslation();
    const command = useCommand<Record<string, FormDataConvertible>>(() => `/gudang/selisih-temuan/${finding.id}/surplus`, 'post');
    const [company, setCompany] = useState('');
    const [basis, setBasis] = useState('');
    const [restored, setRestored] = useState<Record<string, string>>({});
    const [reason, setReason] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const frozen = command.pending || command.unknown;
    useClosedOnAnswer(command.answer, () => { setConfirming(false); });

    return (
        <li data-slot="finding-row" className="flex min-w-0 flex-col gap-3 py-3 text-body">
            <p className="flex min-w-0 flex-wrap items-center gap-2"><span className="font-medium">{finding.product}</span><span>{`${finding.rack} · ${t(`stock.condition.${finding.condition}`)}`}</span><span>{`${finding.qty} ${finding.unit}`}</span><span className="text-text-secondary">{finding.date}</span><Badge>{t('discrepancies.state.waiting_owner')}</Badge></p>
            {command.answer !== null && command.answer.kind !== 'COMMITTED' && !confirming && <OutcomeMessage answer={command.answer} action={command.unknown ? <Button variant="outline" size="sm" onClick={command.retry}>{t('actions.try_again')}</Button> : undefined} />}
            {can.surplus && finding.losses.length > 0 && (
                <fieldset disabled={frozen} data-slot="finding-losses" className="flex min-w-0 flex-col gap-2">
                    <legend className="font-medium">{t('counts.identified_losses')}</legend>
                    {finding.losses.map((loss) => (
                        <FormField key={loss.id} label={t('counts.field.restore', { kode: loss.code })} help={`${loss.date} · ${loss.open}`} className="w-64">
                            {(control) => <DecimalInput {...control} scale={finding.scale} value={restored[loss.id] ?? ''} onValueChange={(next) => { setRestored({ ...restored, [loss.id]: next ?? '' }); }} />}
                        </FormField>
                    ))}
                </fieldset>
            )}
            {can.surplus && (
                <div className="flex min-w-0 flex-wrap items-end gap-3">
                    <FormField label={t('discrepancies.field.company')} error={command.errors.perusahaan} className="min-w-0 sm:w-64">
                        {(control) => (
                            <NativeSelect {...control} value={company} disabled={frozen} onChange={(event) => { setCompany(event.target.value); }}>
                                <option value="">{t('dispatch.substitute.choose')}</option>
                                {companies.map((one) => <option key={one.id} value={one.id}>{`${one.code} · ${one.name}`}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                    <FormField label={t('discrepancies.field.basis')} error={command.errors.dasar_biaya} className="w-48">
                        {(control) => <DecimalInput {...control} scale={2} disabled={frozen} value={basis} onValueChange={(next) => { setBasis(next ?? ''); }} />}
                    </FormField>
                    <Button disabled={company === '' || basis === '' || frozen} onClick={() => { command.clear(); setConfirming(true); }}>{t('actions.attribute_surplus')}</Button>
                </div>
            )}
            <ConfirmCommand
                open={confirming && !command.unknown}
                onOpenChange={setConfirming}
                title={t('actions.attribute_surplus')}
                consequence={t('discrepancies.confirm.surplus')}
                confirmLabel={t('actions.attribute_surplus')}
                pending={command.pending}
                items={<BusinessDateControl today={today} value={date} onChange={setDate} />}
                reason={{ value: reason, onChange: setReason, error: command.errors.alasan ?? null }}
                onConfirm={() => { command.submit({ perusahaan: company, dasar_biaya: basis, kebijakan_seri: finding.serialized, kebijakan_skala: finding.scale, pulihkan: Object.entries(restored).filter(([, qty]) => Number(figure(qty)) > 0).map(([konsumsi, jumlah]) => ({ konsumsi, jumlah })), alasan: reason, tanggal: date.date, tanggal_otomatis: !date.chosen }, () => { setConfirming(false); }); }}
            />
        </li>
    );
}

function CaseRow({ row, proofs, today, can }: { row: Case; proofs: Props['proofs']; today: string; can: Can }) {
    const { t } = useTranslation();
    const owner = useCommand<Record<string, FormDataConvertible>>(() => `/gudang/selisih-temuan/${row.id}/tetapkan`, 'post');
    const proof = useCommand<Record<string, FormDataConvertible>>(() => `/gudang/selisih-temuan/${row.id}/tetapkan-bukti`, 'post');
    const [quantities, setQuantities] = useState<Record<string, string>>({});
    const [bearers, setBearers] = useState<Record<string, string>>({});
    const [evidence, setEvidence] = useState('');
    const [uploaded, setUploaded] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);
    const [reason, setReason] = useState('');
    const [confirming, setConfirming] = useState<'owner' | 'evidence' | null>(null);
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const frozen = owner.pending || owner.unknown || proof.pending || proof.unknown;
    const entered = row.lots.filter((lot) => Number(figure(quantities[lot.lot] ?? '')) > 0);
    const companies = [...new Map(row.exposures.map((one) => [one.id, one])).values()];
    const answer = owner.answer ?? proof.answer;
    useClosedOnAnswer(answer, () => { setConfirming(null); });
    // A lot that came after the recognition has no exposure of its own: the Owner's choice among the candidates.
    const bearerOf = (lot: LiveLot) => bearers[lot.lot] ?? (companies.some((one) => one.id === lot.company_id) ? lot.company_id : companies[0]?.id ?? lot.company_id);
    const lines = () => {
        const byCompany = new Map<string, { perusahaan: string; tutup: { lot: string; jumlah: string }[] }>();

        for (const lot of entered) {
            const bearer = bearerOf(lot);
            const line = byCompany.get(bearer) ?? { perusahaan: bearer, tutup: [] };
            line.tutup.push({ lot: lot.lot, jumlah: quantities[lot.lot] ?? '0' });
            byCompany.set(bearer, line);
        }

        return [...byCompany.values()];
    };

    return (
        <li data-slot="case-row" className="flex min-w-0 flex-col gap-3 py-3 text-body">
            <p className="flex min-w-0 flex-wrap items-center gap-2"><span className="font-medium">{row.product}</span><span>{`${row.rack} · ${t(`stock.condition.${row.condition}`)}`}</span><span className="text-text-secondary">{row.date}</span><Badge>{t('discrepancies.state.waiting')}</Badge></p>
            <p data-slot="case-pending">{t('messages.msg_41', { jumlah: row.pending, satuan: row.unit })}</p>
            <p className="text-text-secondary">{`${row.actor} · ${row.reason}`}</p>
            {row.candidates.length > 0 && (
                <ul role="list" data-slot="case-candidates" className="flex min-w-0 flex-col gap-1">
                    {row.candidates.map((candidate) => <li key={candidate.lot} className="flex min-w-0 flex-wrap items-center gap-2"><Code>{candidate.code}</Code><span>{candidate.company}</span><span>{candidate.qty}</span>{candidate.cost !== null && <Money value={candidate.cost} />}</li>)}
                </ul>
            )}
            {row.exposures.length > 0 && <p data-slot="case-exposures" className="text-text-secondary">{row.exposures.map((one) => t('discrepancies.exposure', { perusahaan: one.company, paparan: one.exposure, ditetapkan: one.assigned })).join(' · ')}</p>}
            {answer !== null && answer.kind !== 'COMMITTED' && confirming === null && <OutcomeMessage answer={answer} />}
            {(can.resolve || can.evidence) && row.lots.length > 0 && (
                <fieldset disabled={frozen} data-slot="case-lots" className="flex min-w-0 flex-col gap-2">
                    <legend className="font-medium">{t('discrepancies.field.close')}</legend>
                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {row.lots.map((lot) => (
                            <li key={lot.lot} data-slot="case-lot" className="flex min-w-0 flex-wrap items-end gap-3 py-2">
                                <span className="flex min-w-0 flex-1 flex-wrap items-center gap-2"><Code>{lot.code}</Code><span>{lot.company}</span><span>{lot.qty}</span></span>
                                <FormField label={t('adjustments.field.quantity', { kode: lot.code, rak: row.rack })} className="w-40">
                                    {(control) => <DecimalInput {...control} scale={3} value={quantities[lot.lot] ?? ''} onValueChange={(next) => { setQuantities({ ...quantities, [lot.lot]: next ?? '' }); }} />}
                                </FormField>
                                {can.resolve && companies.length > 0 && (
                                    <FormField label={t('discrepancies.field.bearer', { kode: lot.code })} className="min-w-0 sm:w-48">
                                        {(control) => (
                                            <NativeSelect {...control} value={bearerOf(lot)} onChange={(event) => { setBearers({ ...bearers, [lot.lot]: event.target.value }); }}>
                                                {companies.map((one) => <option key={one.id} value={one.id}>{one.company}</option>)}
                                            </NativeSelect>
                                        )}
                                    </FormField>
                                )}
                            </li>
                        ))}
                    </ul>
                </fieldset>
            )}
            {can.evidence && !can.resolve && (
                <FormField label={t('adjustments.field.proof')} error={proof.errors.bukti} className="min-w-0 sm:w-96">
                    {(control) => (
                        <NativeSelect {...control} value={evidence} disabled={frozen} onChange={(event) => { setEvidence(event.target.value); }}>
                            <option value="">{t('dispatch.substitute.choose')}</option>
                            {uploaded !== null && <option value={uploaded}>{t('dispatch.substitute.evidence_uploaded')}</option>}
                            {evidence !== '' && evidence !== uploaded && !proofs.rows.some((one) => one.id === evidence) && <option value={evidence}>{t('dispatch.substitute.evidence_uploaded')}</option>}
                            {proofs.rows.filter((one) => one.id !== uploaded).map((one) => <option key={one.id} value={one.id}>{one.label}</option>)}
                        </NativeSelect>
                    )}
                </FormField>
            )}
            {can.evidence && !can.resolve && (
                <div className="flex min-w-0 flex-col gap-2">
                    {proofs.find !== null && proofs.rows.length === 0 && <p role="status" className="text-text-secondary">{t('adjustments.no_proof')}</p>}
                    {proofs.find !== null && <Button variant="ghost" size="sm" className="self-start" disabled={frozen} onClick={() => { router.reload({ only: ['proofs'], preserveUrl: true }); }}>{t('adjustments.all_proofs')}</Button>}
                    {(proofs.more || proofs.find !== null) && <ChoicesFind disabled={frozen} label={t('dispatch.substitute.find_evidence')} help={t('dispatch.substitute.find_evidence_help')} onFind={(text) => { router.reload({ only: ['proofs'], data: { cari_bukti: text }, preserveUrl: true }); }} />}
                    {can.upload && <Button variant="outline" className="self-start" disabled={frozen} onClick={() => { setUploading(true); }}>{t('dispatch.substitute.evidence')}</Button>}
                    {can.upload && <ProofSheet open={uploading} onOpenChange={setUploading} company={null} pool types={['WAREHOUSE_RECORD', 'OTHER']} today={today} back={`${window.location.pathname}${window.location.search}`} onRegistered={(id) => { setUploaded(id); setEvidence(id); setUploading(false); }} />}
                </div>
            )}
            <div className="flex min-w-0 flex-wrap gap-3">
                {can.resolve && <Button disabled={entered.length === 0 || frozen} onClick={() => { owner.clear(); setConfirming('owner'); }}>{t('actions.resolve_company')}</Button>}
                {can.evidence && !can.resolve && <Button disabled={entered.length === 0 || evidence === '' || frozen} onClick={() => { proof.clear(); setConfirming('evidence'); }}>{t('actions.resolve_evidence')}</Button>}
            </div>
            <ConfirmCommand
                open={confirming !== null}
                onOpenChange={(open) => { if (!open) setConfirming(null); }}
                title={t(confirming === 'evidence' ? 'actions.resolve_evidence' : 'actions.resolve_company')}
                consequence={t(confirming === 'evidence' ? 'discrepancies.confirm.evidence' : 'discrepancies.confirm.resolve')}
                confirmLabel={t(confirming === 'evidence' ? 'actions.resolve_evidence' : 'actions.resolve_company')}
                destructive
                pending={owner.pending || proof.pending}
                items={<BusinessDateControl today={today} value={date} onChange={setDate} />}
                reason={{ value: reason, onChange: setReason, error: (owner.errors.alasan ?? proof.errors.alasan) ?? null }}
                onConfirm={() => {
                    const done = () => { setConfirming(null); };

                    if (confirming === 'evidence') {
                        proof.submit({ tutup: entered.map((lot) => ({ lot: lot.lot, jumlah: quantities[lot.lot] ?? '0' })), bukti: [evidence], alasan: reason, tanggal: date.date, tanggal_otomatis: !date.chosen }, done);
                    } else {
                        owner.submit({ baris: lines(), alasan: reason, tanggal: date.date, tanggal_otomatis: !date.chosen }, done);
                    }
                }}
            />
        </li>
    );
}

function ResolutionRow({ row, today }: { row: Resolution; today: string }) {
    const { t } = useTranslation();
    const reverse = useCommand<Record<string, FormDataConvertible>>(() => `/gudang/selisih-temuan/${row.id}/balikkan`, 'post');
    const [reason, setReason] = useState('');
    const [confirming, setConfirming] = useState(false);
    const kind: TranslationKey = row.kind === 'EVIDENCE_IDENTIFIED' ? 'discrepancies.kind.evidence' : 'discrepancies.kind.owner';
    useClosedOnAnswer(reverse.answer, () => { setConfirming(false); });

    return (
        <li data-slot="resolution-row" className="flex min-w-0 flex-col gap-2 py-3 text-body">
            <p className="flex min-w-0 flex-wrap items-center gap-2"><span className="font-medium">{row.product}</span><span>{row.rack}</span><span className="text-text-secondary">{row.date}</span><Badge>{t(kind)}</Badge></p>
            {row.lines.length > 0 && <p>{row.lines.map((line) => `${line.company}: ${line.qty}`).join(' · ')}</p>}
            {row.actor !== null && <p className="text-text-secondary">{`${row.actor} · ${row.reason ?? ''}`}</p>}
            {reverse.answer !== null && reverse.answer.kind !== 'COMMITTED' && !confirming && <OutcomeMessage answer={reverse.answer} />}
            {row.reversible && <Button variant="outline" size="sm" className="self-start" disabled={reverse.pending} onClick={() => { reverse.clear(); setConfirming(true); }}>{t('actions.reverse_resolution')}</Button>}
            <ConfirmCommand
                open={confirming}
                onOpenChange={setConfirming}
                title={t('actions.reverse_resolution')}
                consequence={t('discrepancies.confirm.reverse')}
                confirmLabel={t('actions.reverse_resolution')}
                destructive
                pending={reverse.pending}
                reason={{ value: reason, onChange: setReason, error: reverse.errors.alasan ?? null }}
                onConfirm={() => { reverse.submit({ alasan: reason, tanggal: today, tanggal_otomatis: true }, () => { setConfirming(false); }); }}
            />
        </li>
    );
}

function More<Row>({ page, param }: { page: Page<Row>; param: string }) {
    const { t } = useTranslation();

    if (page.cursor_failed) return <Button variant="outline" className="self-start" render={<a href="/gudang/selisih-temuan" />}>{t('receiving.list.reload')}</Button>;

    return page.more === null ? null : <Button variant="outline" className="self-start" render={<a href={`/gudang/selisih-temuan?${param}=${encodeURIComponent(page.more)}`} />}>{t('actions.load_more')}</Button>;
}

export default function Discrepancies({ findings, cases, resolutions, companies, proofs, today, can }: Props) {
    const { t } = useTranslation();
    const [origin] = useState(() => previousPage() ?? '/gudang/stok');

    return (
        <AppShell title={t('pages.pg_029.title')} back={{ href: origin, label: t('shell.back') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_029.title')} />
                <PageAnswer />
                <section data-slot="discrepancy-cases" className="flex min-w-0 flex-col gap-2">
                    <h2 className="text-subhead">{t('discrepancies.section.cases')}</h2>
                    {cases.rows.length === 0 ? <RegionEmpty text={t('discrepancies.no_cases')} /> : <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">{cases.rows.map((row) => <CaseRow key={row.id} row={row} proofs={proofs} today={today} can={can} />)}</ul>}
                    <More page={cases} param="setelah_kasus" />
                </section>
                <section data-slot="discrepancy-findings" className="flex min-w-0 flex-col gap-2">
                    <h2 className="text-subhead">{t('discrepancies.section.findings')}</h2>
                    {findings.rows.length === 0 ? <RegionEmpty text={t('discrepancies.no_findings')} /> : <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">{findings.rows.map((row) => <FindingRow key={row.id} finding={row} companies={companies} today={today} can={can} />)}</ul>}
                    <More page={findings} param="setelah_temuan" />
                </section>
                {(resolutions.rows.length > 0 || resolutions.cursor_failed) && (
                    <section data-slot="discrepancy-resolutions" className="flex min-w-0 flex-col gap-2">
                        <h2 className="text-subhead">{t('discrepancies.section.resolutions')}</h2>
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">{resolutions.rows.map((row) => <ResolutionRow key={row.id} row={row} today={today} />)}</ul>
                        <More page={resolutions} param="setelah_penetapan" />
                    </section>
                )}
            </div>
        </AppShell>
    );
}
