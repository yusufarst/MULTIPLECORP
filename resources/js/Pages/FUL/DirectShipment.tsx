// PG-032 *Kirim Langsung* (TASK-046; SCR-30; J-FUL-03; NAV-032; WF-FUL-03): the supplier's shipment straight to the client
// — "Konfirmasi Kirim Langsung" (ROUTE-205) with the shipment reference, the supplier's proof, per line the quantity and
// serials and, when the client's proof came with it, the delivery in the same command (L-40); the confirmations with their
// lines' open and delivered figures, the cost only with `cost.view`, the *Surat Jalan* drafted through ROUTE-221 for PG-036
// (ROUTE-216), the reversal of a confirmation recorded in error (ROUTE-206) and the client's return to the warehouse or the
// supplier (ROUTE-207), each with its reason. Receiving and dispatch are never offered here (BR-INV-11). One column on the phone.
import type { FormDataConvertible } from '@inertiajs/core';
import { useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { type BusinessDate, BusinessDateControl } from '@/components/date-field';
import { DecimalInput, FormField } from '@/components/form';
import { DuplicateWarning, type DuplicateMatch } from '@/components/notices';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { Code, DateText } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Purchase = { id: string; number: string; supplier: string | null; open: boolean };
type Line = { id: string; no: number; description: string; purchase: Purchase; unit: string | null; base_unit: string | null; ratio: number; serialized: boolean; scale: number; ordered: string; confirmed: string; remainder: string; awaited: string; cases: string[] };
type ConfirmedLine = { id: string; no: number; description: string; quantity: string; open: string; delivered: string; serial: string | null; replacement: boolean; cost?: string };
type Correction = { kind: string; quantity: string; at: string; case: string | null };
type Confirmation = { id: string; reference: string; date: string; purchase: string; supplier: string | null; delivery: { recipient: string; date: string } | null; lines: ConfirmedLine[]; corrections: Correction[]; document: { id: string; state: string | null; number: string | null } | null; standing: boolean };
type Can = { confirm: boolean; reverse: boolean; return: boolean; cost: boolean; issue: boolean; upload: boolean };
type Page = { project: { id: string; number: string; title: string; client: string | null; open: boolean }; lines: Line[]; confirmations: Confirmation[]; proofs: { id: string; type: string; label: string }[]; racks: { id: string; code: string; name: string }[]; can: Can };
type Props = { page: Page; today: string };
type Payload = Record<string, FormDataConvertible>;
type Correcting = { confirmation: Confirmation; command: 'reverse' | 'return' };

const figure = (value: string) => (/^\d+(\.\d+)?$/.test(value) ? value : '0');
const serialsOf = (text: string) => text.split(/[\n,]/).map((one) => one.trim()).filter((one) => one !== '');

/** The dialog closes once an answer arrives, so a refusal shows in place (IP-01). */
function useClosedOnAnswer(answer: unknown, close: () => void) {
    const [seen, setSeen] = useState(answer);

    if (seen !== answer) {
        setSeen(answer);

        if (answer !== null) close();
    }
}

export default function DirectShipment({ page, today }: Props) {
    const { t } = useTranslation();
    const { project, can } = page;
    const base = `/proyek/${project.id}/kirim-langsung`;
    const confirm = useCommand<Payload>(() => base, 'post');
    // The confirmation a correction names, kept once its dialog closes so a likely duplicate's confirmation is sent to it again (INT-015).
    const [target, setTarget] = useState('');
    const reverse = useCommand<Payload>(() => `${base}/${target}/balikkan`, 'post');
    const returned = useCommand<Payload>(() => `${base}/${target}/retur`, 'post');
    const prepare = useCommand<Payload>(() => '/dokumen', 'post');
    const purchases = page.lines.reduce<Purchase[]>((all, line) => (line.purchase.open && !all.some((one) => one.id === line.purchase.id) ? [...all, line.purchase] : all), []);
    const [purchase, setPurchase] = useState(purchases[0]?.id ?? '');
    const [quantities, setQuantities] = useState<Record<string, string>>({});
    const [serials, setSerials] = useState<Record<string, string>>({});
    const [reference, setReference] = useState('');
    const [proof, setProof] = useState('');
    const [replacing, setReplacing] = useState('');
    const [delivered, setDelivered] = useState(false);
    const [recipient, setRecipient] = useState('');
    const [clientProof, setClientProof] = useState('');
    const [condition, setCondition] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [confirming, setConfirming] = useState(false);
    const [correcting, setCorrecting] = useState<Correcting | null>(null);
    const [amounts, setAmounts] = useState<Record<string, string>>({});
    const [destination, setDestination] = useState<'GUDANG' | 'PEMASOK'>('GUDANG');
    const [rack, setRack] = useState('');
    const [returnedCondition, setReturnedCondition] = useState<'USABLE' | 'UNUSABLE'>('USABLE');
    const [reason, setReason] = useState('');
    const [sent, setSent] = useState<{ command: 'reverse' | 'return'; payload: Payload } | null>(null);
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [againReason, setAgainReason] = useState('');
    useClosedOnAnswer(confirm.answer, () => { setConfirming(false); });
    useClosedOnAnswer(reverse.answer, () => { setCorrecting(null); });
    useClosedOnAnswer(returned.answer, () => { setCorrecting(null); });
    const shown = page.lines.filter((line) => line.purchase.id === purchase);
    const replaceable = shown.filter((line) => Number(line.awaited) > 0 && line.cases.length > 0);
    const entered = shown.filter((line) => (replacing === '' ? Number(line.remainder) > 0 : line.cases.includes(replacing)) && (line.serialized ? serialsOf(serials[line.id] ?? '').length > 0 : Number(figure(quantities[line.id] ?? '')) > 0));
    const ready = purchase !== '' && entered.length > 0 && reference.trim() !== '' && proof !== '' && (!delivered || (recipient.trim().length >= 3 && clientProof !== ''));
    const again = sent?.command === 'return' ? returned : reverse;
    const againMatches = Array.isArray(again.state.matches) ? (again.state.matches as DuplicateMatch[]) : [];
    const againWarned = sent !== null && again.answer?.kind === 'REJECTED' && againMatches.length > 0;
    const answer = reverse.answer ?? returned.answer ?? prepare.answer;

    const startCorrection = (confirmation: Confirmation, command: 'reverse' | 'return') => {
        reverse.clear();
        returned.clear();
        setAmounts({});
        setReason('');
        setRack('');
        setDestination('GUDANG');
        setReturnedCondition('USABLE');
        setTarget(confirmation.id);
        setCorrecting({ confirmation, command });
    };
    const sendCorrection = () => {
        if (correcting === null) return;
        const lines = correcting.confirmation.lines.filter((line) => Number(figure(amounts[line.id] ?? '')) > 0);
        const payload: Payload = {
            baris: lines.map((line) => ({ baris: line.id, jumlah: amounts[line.id] ?? '0' })),
            alasan: reason,
            tanggal: today,
            tanggal_otomatis: true,
            ...(correcting.command === 'return' ? { tujuan: destination, ...(destination === 'GUDANG' ? { rak: rack, kondisi: returnedCondition } : {}) } : {}),
        };
        setSent({ command: correcting.command, payload });
        setAcknowledged([]);
        setAgainReason('');
        (correcting.command === 'reverse' ? reverse : returned).submit(payload);
    };

    return (
        <AppShell title={t('pages.pg_032.title')} back={{ href: `/proyek/${project.id}`, label: t('shell.back') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_032.title')} crumbs={[{ label: project.number, href: `/proyek/${project.id}` }]} />
                <section data-slot="dropship-project" className="flex min-w-0 flex-col gap-1 text-body">
                    <p><a className="font-medium underline" href={`/proyek/${project.id}`}>{project.title}</a>{' · '}<Code>{project.number}</Code></p>
                    {project.client !== null && <p className="text-text-secondary">{project.client}</p>}
                    <p className="text-text-secondary">{t('dropship.no_warehouse')}</p>
                </section>
                {answer !== null && answer.kind !== 'COMMITTED' && correcting === null && !againWarned && <OutcomeMessage answer={answer} />}
                {reverse.answer?.kind === 'COMMITTED' && <OutcomeMessage answer={reverse.answer} />}
                {returned.answer?.kind === 'COMMITTED' && <OutcomeMessage answer={returned.answer} />}
                {againWarned && (
                    <div className="flex min-w-0 flex-col gap-2">
                        <DuplicateWarning matches={againMatches} message={again.answer?.text} acknowledged={acknowledged} onAcknowledge={setAcknowledged}
                            reason={{ value: againReason, onChange: setAgainReason, error: again.errors.reason ?? null }} canContinue />
                        <Button className="self-start" pending={again.pending} disabled={again.frozen} onClick={() => { again.submit({ ...sent.payload, acknowledged, reason: againReason }); }}>{t('actions.continue')}</Button>
                    </div>
                )}
                <section data-slot="dropship-lines" className="flex min-w-0 flex-col gap-3">
                    <h2 className="text-subhead">{t('dropship.section.lines')}</h2>
                    {page.lines.length === 0 ? <RegionEmpty text={t('dropship.no_lines')} /> : (
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {page.lines.map((line) => (
                                <li key={line.id} data-slot="dropship-line" className="flex min-w-0 flex-col gap-2 py-3">
                                    <span className="font-medium">{`${String(line.no)}. ${line.description}`}</span>
                                    <span className="text-caption text-text-secondary">{`${line.purchase.number}${line.purchase.supplier === null ? '' : ` · ${line.purchase.supplier}`}`}</span>
                                    <dl className="grid min-w-0 grid-cols-2 gap-x-6 gap-y-1 text-body sm:grid-cols-4">
                                        {(['ordered', 'confirmed', 'remainder', 'awaited'] as const).map((field) => (
                                            <div key={field} className="flex min-w-0 flex-col"><dt className="text-caption text-text-secondary">{t(`dropship.figure.${field}`)}</dt><dd>{`${line[field]} ${line.base_unit ?? ''}`}</dd></div>
                                        ))}
                                    </dl>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
                {can.confirm && project.open && purchases.length > 0 && (
                    <fieldset data-slot="dropship-form" disabled={confirm.frozen} className="flex min-w-0 flex-col gap-3">
                        <legend className="text-subhead">{t('dropship.section.confirm')}</legend>
                        <FormField label={t('dropship.field.purchase')} error={confirm.errors.pembelian}>
                            {(control) => (
                                <NativeSelect {...control} value={purchase} onChange={(event) => { setPurchase(event.target.value); setReplacing(''); }}>
                                    {purchases.map((one) => <option key={one.id} value={one.id}>{`${one.number}${one.supplier === null ? '' : ` · ${one.supplier}`}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        <FormField label={t('dropship.field.reference')} help={t('dropship.field.reference_help')} error={confirm.errors.referensi}>
                            {(control) => <Input {...control} value={reference} maxLength={100} onChange={(event) => { setReference(event.target.value); }} />}
                        </FormField>
                        {replaceable.length > 0 && (
                            <FormField label={t('dropship.field.replacement')} help={t('dropship.field.replacement_help')} error={confirm.errors.kasus}>
                                {(control) => (
                                    <NativeSelect {...control} value={replacing} onChange={(event) => { setReplacing(event.target.value); }}>
                                        <option value="">{t('dropship.field.not_replacement')}</option>
                                        {[...new Set(replaceable.flatMap((line) => line.cases))].map((one) => <option key={one} value={one}>{one}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                        )}
                        {shown.filter((line) => (replacing === '' ? Number(line.remainder) > 0 : line.cases.includes(replacing))).map((line, index) => (
                            <div key={line.id} data-slot="dropship-entry" className="flex min-w-0 flex-col gap-2">
                                <span className="text-body">{`${String(line.no)}. ${line.description} · ${t('dropship.figure.remainder')} ${replacing === '' ? line.remainder : line.awaited} ${line.base_unit ?? ''}`}</span>
                                {line.serialized ? (
                                    <FormField label={t('dropship.field.serials')} help={t('dropship.field.serials_help')} error={confirm.errors[`baris.${String(index)}.seri`] ?? confirm.errors[`baris.${String(index)}.jumlah`]}>
                                        {(control) => <Textarea {...control} value={serials[line.id] ?? ''} onChange={(event) => { setSerials((current) => ({ ...current, [line.id]: event.target.value })); }} />}
                                    </FormField>
                                ) : (
                                    <FormField label={t('dropship.field.quantity', { satuan: line.unit ?? '' })} error={confirm.errors[`baris.${String(index)}.jumlah`]} className="w-48">
                                        {(control) => <DecimalInput {...control} scale={line.ratio === 1 ? line.scale : 3} value={quantities[line.id] ?? ''} onValueChange={(next) => { setQuantities((current) => ({ ...current, [line.id]: next ?? '' })); }} />}
                                    </FormField>
                                )}
                            </div>
                        ))}
                        <BusinessDateControl today={today} value={date} onChange={setDate} />
                        <FormField label={t('dropship.field.proof')} help={t('dropship.field.proof_help')} error={confirm.errors.bukti}>
                            {(control) => (
                                <NativeSelect {...control} value={proof} onChange={(event) => { setProof(event.target.value); }}>
                                    <option value="">{t('dispatch.substitute.choose')}</option>
                                    {page.proofs.map((one) => <option key={one.id} value={one.id}>{one.label}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        {can.upload && <a className="self-start text-body underline" href="/dokumen/bukti">{t('deliveries.upload_proof')}</a>}
                        <label className="flex min-h-floor items-center gap-2 text-body">
                            <Checkbox checked={delivered} onCheckedChange={(checked) => { setDelivered(checked); }} />
                            <span>{t('dropship.field.delivered')}</span>
                        </label>
                        {delivered && (
                            <div className="flex min-w-0 flex-col gap-3">
                                <FormField label={t('deliveries.field.recipient')} error={confirm.errors.penerima}>
                                    {(control) => <Input {...control} value={recipient} maxLength={200} onChange={(event) => { setRecipient(event.target.value); }} />}
                                </FormField>
                                <FormField label={t('dropship.field.client_proof')} error={confirm.errors.bukti_klien}>
                                    {(control) => (
                                        <NativeSelect {...control} value={clientProof} onChange={(event) => { setClientProof(event.target.value); }}>
                                            <option value="">{t('dispatch.substitute.choose')}</option>
                                            {page.proofs.map((one) => <option key={one.id} value={one.id}>{one.label}</option>)}
                                        </NativeSelect>
                                    )}
                                </FormField>
                                <FormField label={t('deliveries.field.condition')} error={confirm.errors.kondisi}>
                                    {(control) => <Textarea {...control} value={condition} maxLength={500} onChange={(event) => { setCondition(event.target.value); }} />}
                                </FormField>
                            </div>
                        )}
                        {!confirming && confirm.answer !== null && confirm.answer.kind !== 'COMMITTED' && <OutcomeMessage answer={confirm.answer} action={confirm.unknown ? <Button variant="outline" size="sm" onClick={confirm.retry}>{t('actions.try_again')}</Button> : undefined} />}
                        {(['tanggal', 'baris'] as const).map((field) => (confirm.errors[field] === undefined ? null : <p key={field} role="alert" className="text-body text-danger-fg">{confirm.errors[field]}</p>))}
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" render={<a href={`/proyek/${project.id}`} />}>{t('actions.cancel')}</Button>
                            <Button pending={confirm.pending} disabled={!ready || confirm.frozen} onClick={() => { confirm.clear(); setConfirming(true); }}>{t('dropship.action.confirm')}</Button>
                        </div>
                    </fieldset>
                )}
                <section data-slot="dropship-confirmations" className="flex min-w-0 flex-col gap-3">
                    <h2 className="text-subhead">{t('dropship.section.confirmations')}</h2>
                    {page.confirmations.length === 0 ? <RegionEmpty text={t('dropship.none')} /> : (
                        <ol className="flex min-w-0 flex-col gap-4">
                            {page.confirmations.map((confirmation) => (
                                <li key={confirmation.id} data-slot="dropship-confirmation" className="flex min-w-0 flex-col gap-2 rounded-md border border-border-subtle p-3 text-body">
                                    <span className="font-medium"><DateText value={confirmation.date} />{` · ${confirmation.reference}`}</span>
                                    <span className="text-caption text-text-secondary">{`${confirmation.purchase}${confirmation.supplier === null ? '' : ` · ${confirmation.supplier}`}`}</span>
                                    {confirmation.delivery !== null && <span className="text-text-secondary">{t('dropship.delivered_to', { penerima: confirmation.delivery.recipient })}</span>}
                                    <ul role="list" className="flex min-w-0 flex-col gap-1">
                                        {confirmation.lines.map((line) => (
                                            <li key={line.id} className="flex min-w-0 flex-wrap items-center gap-2">
                                                <span>{`${String(line.no)}. ${line.description} · ${line.quantity}`}</span>
                                                {line.serial !== null && <span className="font-mono">{line.serial}</span>}
                                                {line.replacement && <Badge>{t('dropship.replacement')}</Badge>}
                                                <span className="text-caption text-text-secondary">{t('dropship.line_figures', { sisa: line.open, diterima: line.delivered })}</span>
                                                {can.cost && line.cost !== undefined && <span className="text-caption">{t('dropship.cost', { biaya: line.cost })}</span>}
                                            </li>
                                        ))}
                                    </ul>
                                    {confirmation.corrections.length > 0 && (
                                        <ul role="list" className="flex min-w-0 flex-col gap-1 text-caption text-text-secondary">
                                            {confirmation.corrections.map((correction, index) => (
                                                <li key={`${correction.kind}-${String(index)}`}>{`${t(`dropship.kind.${correction.kind}` as TranslationKey)} · ${correction.quantity}${correction.case === null ? '' : ` · ${correction.case}`}`}</li>
                                            ))}
                                        </ul>
                                    )}
                                    {confirmation.document !== null && <a className="self-start underline" href={`/dokumen/${confirmation.document.id}`}>{`${t('documents.doc_03')}${confirmation.document.number === null ? '' : ` ${confirmation.document.number}`}`}</a>}
                                    <div className="flex flex-wrap gap-2">
                                        {can.issue && confirmation.standing && <Button variant="outline" size="sm" pending={prepare.pending} disabled={prepare.frozen} onClick={() => { prepare.submit({ jenis: 'DOC-03', subjek: { dropship_confirmation_id: confirmation.id } }); }}>{t('dropship.action.note')}</Button>}
                                        {can.reverse && confirmation.standing && <Button variant="ghost" size="sm" onClick={() => { startCorrection(confirmation, 'reverse'); }}>{t('dropship.action.reverse')}</Button>}
                                        {can.return && confirmation.standing && <Button variant="ghost" size="sm" onClick={() => { startCorrection(confirmation, 'return'); }}>{t('dropship.action.return')}</Button>}
                                    </div>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            </div>
            <ConfirmCommand
                open={confirming && !confirm.unknown}
                onOpenChange={(next) => { if (!next) setConfirming(false); }}
                title={t('dropship.action.confirm')}
                consequence={delivered ? t('dropship.confirm.with_delivery') : t('dropship.confirm.consequence')}
                confirmLabel={t('dropship.action.confirm')}
                pending={confirm.pending}
                onConfirm={() => {
                    confirm.submit({
                        pembelian: purchase,
                        referensi: reference,
                        bukti: proof,
                        ...(replacing === '' ? {} : { kasus: replacing }),
                        tanggal: date.date,
                        tanggal_otomatis: !date.chosen,
                        baris: entered.map((line) => ({ baris: line.id, jumlah: line.serialized ? String(serialsOf(serials[line.id] ?? '').length) : (quantities[line.id] ?? '0'), seri: line.serialized ? serialsOf(serials[line.id] ?? '') : [] })),
                        ...(delivered ? { penerima: recipient, bukti_klien: clientProof, kondisi: condition } : {}),
                    });
                }}
            />
            <ConfirmCommand
                open={correcting !== null && !(correcting.command === 'reverse' ? reverse : returned).unknown}
                onOpenChange={(next) => { if (!next) setCorrecting(null); }}
                title={correcting?.command === 'return' ? t('dropship.action.return') : t('dropship.action.reverse')}
                consequence={correcting?.command === 'return' ? t(destination === 'GUDANG' ? 'dropship.confirm.return_warehouse' : 'dropship.confirm.return_supplier') : t('dropship.confirm.reverse')}
                confirmLabel={correcting?.command === 'return' ? t('dropship.action.return') : t('dropship.action.reverse')}
                pending={(correcting?.command === 'return' ? returned : reverse).pending}
                items={correcting === null ? null : (
                    <div className="flex min-w-0 flex-col gap-3">
                        {correcting.command === 'return' && (
                            <>
                                <FormField label={t('dropship.field.destination')} error={returned.errors.tujuan}>
                                    {(control) => (
                                        <NativeSelect {...control} value={destination} onChange={(event) => { setDestination(event.target.value === 'PEMASOK' ? 'PEMASOK' : 'GUDANG'); }}>
                                            <option value="GUDANG">{t('dropship.destination.GUDANG')}</option>
                                            <option value="PEMASOK">{t('dropship.destination.PEMASOK')}</option>
                                        </NativeSelect>
                                    )}
                                </FormField>
                                {destination === 'GUDANG' && (
                                    <>
                                        <FormField label={t('dropship.field.rack')} error={returned.errors.rak}>
                                            {(control) => (
                                                <NativeSelect {...control} value={rack} onChange={(event) => { setRack(event.target.value); }}>
                                                    <option value="">{t('dispatch.substitute.choose')}</option>
                                                    {page.racks.map((one) => <option key={one.id} value={one.id}>{`${one.code} · ${one.name}`}</option>)}
                                                </NativeSelect>
                                            )}
                                        </FormField>
                                        <FormField label={t('dropship.field.condition')} error={returned.errors.kondisi}>
                                            {(control) => (
                                                <NativeSelect {...control} value={returnedCondition} onChange={(event) => { setReturnedCondition(event.target.value === 'UNUSABLE' ? 'UNUSABLE' : 'USABLE'); }}>
                                                    <option value="USABLE">{t('dropship.condition.USABLE')}</option>
                                                    <option value="UNUSABLE">{t('dropship.condition.UNUSABLE')}</option>
                                                </NativeSelect>
                                            )}
                                        </FormField>
                                    </>
                                )}
                            </>
                        )}
                        {correcting.confirmation.lines.filter((line) => Number(line.open) > 0).map((line, index) => (
                            <FormField key={line.id} label={`${String(line.no)}. ${line.description}${line.serial === null ? '' : ` · ${line.serial}`}`} help={t('dropship.line_figures', { sisa: line.open, diterima: line.delivered })}
                                error={(correcting.command === 'return' ? returned : reverse).errors[`baris.${String(index)}.jumlah`]} className="w-full">
                                {(control) => <DecimalInput {...control} scale={3} value={amounts[line.id] ?? ''} onValueChange={(next) => { setAmounts((current) => ({ ...current, [line.id]: next ?? '' })); }} />}
                            </FormField>
                        ))}
                        {(['baris', 'tanggal'] as const).map((field) => ((correcting.command === 'return' ? returned : reverse).errors[field] === undefined ? null : <p key={field} role="alert" className="text-body text-danger-fg">{(correcting.command === 'return' ? returned : reverse).errors[field]}</p>))}
                    </div>
                )}
                reason={{ value: reason, onChange: setReason, error: (correcting?.command === 'return' ? returned : reverse).errors.alasan ?? null }}
                onConfirm={sendCorrection}
            />
        </AppShell>
    );
}
