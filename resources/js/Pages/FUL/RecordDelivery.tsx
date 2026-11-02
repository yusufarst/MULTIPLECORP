// PG-031 *Catat Pengiriman* (TASK-045; SCR-29; J-FUL-02; NAV-031; D-IA-16): what reached the client against what was
// shipped — the recipient, the date, the condition and the proof, per line the quantity at the shipment's unit (ROUTE-201),
// a difference opening its case; the deliveries with their papers (DOC-08, DOC-10 drafted through ROUTE-221 for PG-036),
// "Koreksi Catatan Pengiriman" (ROUTE-204), "Tutup Pengiriman" and "Catat Hilang di Perjalanan" (ROUTE-203). A delivery
// never changes stock; the form says so (BR-INV-02; FS-03). One column on the phone.
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
import { previousPage } from '@/lib/previous-page';
import { useCommand } from '@/lib/use-command';

const PAGE = '/gudang/pengiriman';
const CLOSURES = ['REFUSED', 'RETURNED_UNDELIVERED'] as const;

type Line = { id: string; no: number; description: string; unit: string; base_unit: string; ratio: number; scale: number; serialized: boolean; shipped: string; delivered: string; held: string; remainder: string; serials: { id: string; serial: string }[] };
type DeliveredLine = { id: string; no: number; description: string; quantity: string; unit: string; reversal: boolean; reversed: boolean };
type Paper = { id: string; type: string; state: string | null; number: string | null };
type Delivery = { id: string; date: string; recipient: string; condition: string | null; lines: DeliveredLine[]; proof: { id: string; label: string } | null; papers: Paper[]; corrected: boolean };
type Closure = { id: string; line: string; no: number; kind: string; quantity: string; reason: string; date: string; superseded: boolean };
type Shipment = { id: string; date: string; project: { id: string; number: string; title: string }; client: string | null; lines: Line[]; deliveries: Delivery[]; closures: Closure[]; proofs: { id: string; type: string; label: string }[] };
type Can = { record: boolean; close: boolean; lost: boolean; correct: boolean; issue: boolean; upload: boolean };
type Props = { shipment: Shipment; today: string; can: Can };
type Payload = Record<string, FormDataConvertible>;
type Closing = { line: Line; lost: boolean; supersedes: Closure | null };
type Correcting = { delivery: Delivery; line: DeliveredLine };

const figure = (value: string) => (/^\d+(\.\d+)?$/.test(value) ? value : '0');

/** The confirmation closes once an answer arrives, so a refusal shows in place (IP-01). */
function useClosedOnAnswer(answer: unknown, close: () => void) {
    const [seen, setSeen] = useState(answer);

    if (seen !== answer) {
        setSeen(answer);

        if (answer !== null) close();
    }
}

export default function RecordDelivery({ shipment, today, can }: Props) {
    const { t } = useTranslation();
    const [origin] = useState(() => previousPage() ?? PAGE);
    const page = `${PAGE}/${shipment.id}`;
    const record = useCommand<Payload>(() => `${page}/catat`, 'post');
    const close = useCommand<Payload>(() => `${page}/tutup`, 'post');
    const correct = useCommand<Payload>(() => `${page}/koreksi`, 'post');
    const prepare = useCommand<Payload>(() => '/dokumen', 'post');
    const [quantities, setQuantities] = useState<Record<string, string>>({});
    const [recipient, setRecipient] = useState('');
    const [condition, setCondition] = useState('');
    const [discrepancy, setDiscrepancy] = useState(false);
    const [inspector, setInspector] = useState('');
    const [result, setResult] = useState('');
    const [proof, setProof] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [duplicateReason, setDuplicateReason] = useState('');
    const [closing, setClosing] = useState<Closing | null>(null);
    const [kind, setKind] = useState<(typeof CLOSURES)[number]>('REFUSED');
    const [closeQty, setCloseQty] = useState('');
    const [lostSerials, setLostSerials] = useState<string[]>([]);
    const [closeReason, setCloseReason] = useState('');
    const [correcting, setCorrecting] = useState<Correcting | null>(null);
    const [rightQty, setRightQty] = useState('');
    const [correctReason, setCorrectReason] = useState('');
    useClosedOnAnswer(close.answer, () => { setClosing(null); });
    useClosedOnAnswer(correct.answer, () => { setCorrecting(null); });
    const open = shipment.lines.filter((line) => Number(line.remainder) > 0);
    const filled = open.filter((line) => Number(figure(quantities[line.id] ?? '')) > 0);
    const matches = Array.isArray(record.state.matches) ? (record.state.matches as DuplicateMatch[]) : [];
    const warned = record.answer?.kind === 'REJECTED' && matches.length > 0;
    const ready = filled.length > 0 && recipient.trim().length >= 3 && proof !== '' && (!discrepancy || condition.trim().length >= 3) && (inspector.trim() === '') === (result.trim() === '');
    const answer = close.answer ?? correct.answer ?? prepare.answer;

    const submit = () => {
        record.submit({
            tanggal: date.date,
            tanggal_otomatis: !date.chosen,
            penerima: recipient,
            kondisi: condition,
            selisih: discrepancy,
            pemeriksa: inspector,
            hasil_pemeriksaan: result,
            bukti: proof,
            baris: filled.map((line) => ({ baris: line.id, jumlah: quantities[line.id] ?? '0' })),
            ...(warned ? { acknowledged, reason: duplicateReason } : {}),
        });
    };
    const startClose = (line: Line, lost: boolean, supersedes: Closure | null) => {
        close.clear();
        setKind('REFUSED');
        setCloseQty('');
        setLostSerials([]);
        setCloseReason('');
        setClosing({ line, lost, supersedes });
    };

    return (
        <AppShell title={t('pages.pg_031.title')} back={{ href: origin, label: t('shell.back') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_031.title')} />
                <section data-slot="delivery-shipment" className="flex min-w-0 flex-col gap-1 text-body">
                    <p><a className="font-medium underline" href={`/proyek/${shipment.project.id}`}>{shipment.project.title}</a>{' · '}<Code>{shipment.project.number}</Code></p>
                    <p className="text-text-secondary">{shipment.client ?? ''}{shipment.client === null ? '' : ' · '}{t('deliveries.shipped_on')} <DateText value={shipment.date} /></p>
                    <p className="text-text-secondary">{t('deliveries.no_stock')}</p>
                </section>
                {answer !== null && answer.kind !== 'COMMITTED' && closing === null && correcting === null && <OutcomeMessage answer={answer} />}
                {close.answer?.kind === 'COMMITTED' && <OutcomeMessage answer={close.answer} />}
                {correct.answer?.kind === 'COMMITTED' && <OutcomeMessage answer={correct.answer} />}
                <section data-slot="delivery-lines" className="flex min-w-0 flex-col gap-3">
                    <h2 className="text-subhead">{t('deliveries.section.lines')}</h2>
                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {shipment.lines.map((line) => (
                            <li key={line.id} data-slot="delivery-line" className="flex min-w-0 flex-col gap-2 py-3">
                                <span className="font-medium">{`${String(line.no)}. ${line.description}`}</span>
                                <dl className="grid min-w-0 grid-cols-2 gap-x-6 gap-y-1 text-body sm:grid-cols-4">
                                    {(['shipped', 'delivered', 'held', 'remainder'] as const).map((field) => (
                                        <div key={field} className="flex min-w-0 flex-col"><dt className="text-caption text-text-secondary">{t(`deliveries.figure.${field}`)}</dt><dd>{`${line[field]} ${line.base_unit}`}</dd></div>
                                    ))}
                                </dl>
                                {line.serials.length > 0 && <p className="text-caption text-text-secondary">{`${t('deliveries.serials')}: ${line.serials.map((one) => one.serial).join(', ')}`}</p>}
                                {can.record && Number(line.remainder) > 0 && (
                                    <FormField label={t('deliveries.field.quantity', { satuan: line.unit })} error={record.errors[`baris.${String(filled.indexOf(line))}.jumlah`]} className="w-48">
                                        {(control) => <DecimalInput {...control} scale={line.ratio === 1 ? line.scale : 3} value={quantities[line.id] ?? ''} onValueChange={(next) => { setQuantities((current) => ({ ...current, [line.id]: next ?? '' })); }} />}
                                    </FormField>
                                )}
                                {Number(line.remainder) > 0 && (can.close || can.lost) && (
                                    <div className="flex flex-wrap gap-2">
                                        {can.close && <Button variant="outline" size="sm" onClick={() => { startClose(line, false, null); }}>{t('deliveries.action.close')}</Button>}
                                        {can.lost && <Button variant="outline" size="sm" onClick={() => { startClose(line, true, null); }}>{t('actions.record_loss_in_transit')}</Button>}
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
                {can.record && open.length > 0 && (
                    <fieldset data-slot="delivery-form" disabled={record.frozen} className="flex min-w-0 flex-col gap-3">
                        <legend className="text-subhead">{t('deliveries.section.record')}</legend>
                        <FormField label={t('deliveries.field.recipient')} error={record.errors.penerima}>
                            {(control) => <Input {...control} value={recipient} maxLength={200} onChange={(event) => { setRecipient(event.target.value); }} />}
                        </FormField>
                        <BusinessDateControl today={today} value={date} onChange={setDate} />
                        <FormField label={t('deliveries.field.condition')} help={t('deliveries.field.condition_help')} error={record.errors.kondisi}>
                            {(control) => <Textarea {...control} value={condition} maxLength={500} onChange={(event) => { setCondition(event.target.value); }} />}
                        </FormField>
                        <label className="flex min-h-floor items-center gap-2 text-body">
                            <Checkbox checked={discrepancy} onCheckedChange={(checked) => { setDiscrepancy(checked); }} />
                            <span>{t('deliveries.field.discrepancy')}</span>
                        </label>
                        <FormField label={t('deliveries.field.proof')} help={t('deliveries.field.proof_help')} error={record.errors.bukti}>
                            {(control) => (
                                <NativeSelect {...control} value={proof} onChange={(event) => { setProof(event.target.value); }}>
                                    <option value="">{t('dispatch.substitute.choose')}</option>
                                    {shipment.proofs.map((one) => <option key={one.id} value={one.id}>{one.label}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        {can.upload && <a className="self-start text-body underline" href="/dokumen/bukti">{t('deliveries.upload_proof')}</a>}
                        <details className="flex min-w-0 flex-col gap-3">
                            <summary className="min-h-floor cursor-pointer text-body">{t('deliveries.section.inspection')}</summary>
                            <FormField label={t('deliveries.field.inspector')} error={record.errors.pemeriksa}>
                                {(control) => <Input {...control} value={inspector} maxLength={200} onChange={(event) => { setInspector(event.target.value); }} />}
                            </FormField>
                            <FormField label={t('deliveries.field.inspection_result')} error={record.errors.hasil_pemeriksaan}>
                                {(control) => <Textarea {...control} value={result} maxLength={500} onChange={(event) => { setResult(event.target.value); }} />}
                            </FormField>
                        </details>
                        {warned ? (
                            <DuplicateWarning matches={matches} message={record.answer?.text} acknowledged={acknowledged} onAcknowledge={setAcknowledged}
                                reason={{ value: duplicateReason, onChange: setDuplicateReason, error: record.errors.reason ?? null }} canContinue />
                        ) : (record.answer !== null && record.answer.kind !== 'COMMITTED' && <OutcomeMessage answer={record.answer} action={record.unknown ? <Button variant="outline" size="sm" onClick={record.retry}>{t('actions.try_again')}</Button> : undefined} />)}
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" render={<a href={origin} />}>{t('actions.cancel')}</Button>
                            <Button pending={record.pending} disabled={!ready || record.frozen} onClick={submit}>{t('deliveries.action.record')}</Button>
                        </div>
                    </fieldset>
                )}
                <section data-slot="delivery-records" className="flex min-w-0 flex-col gap-3">
                    <h2 className="text-subhead">{t('deliveries.section.records')}</h2>
                    {shipment.deliveries.length === 0 ? <RegionEmpty text={t('deliveries.none_for_shipment')} /> : (
                        <ol className="flex min-w-0 flex-col gap-4">
                            {shipment.deliveries.map((delivery) => (
                                <li key={delivery.id} data-slot="delivery-record" className="flex min-w-0 flex-col gap-2 rounded-md border border-border-subtle p-3 text-body">
                                    <span className="font-medium"><DateText value={delivery.date} />{` · ${delivery.recipient}`}</span>
                                    {delivery.condition !== null && <span className="text-text-secondary">{delivery.condition}</span>}
                                    {delivery.proof !== null && <span className="text-caption text-text-secondary">{`${t('deliveries.field.proof')}: ${delivery.proof.label}`}</span>}
                                    <ul role="list" className="flex min-w-0 flex-col gap-1">
                                        {delivery.lines.map((line) => (
                                            <li key={line.id} className="flex min-w-0 flex-wrap items-center gap-2">
                                                <span className={line.reversed ? 'line-through' : undefined}>{`${String(line.no)}. ${line.description} · ${line.reversal ? '−' : ''}${line.quantity} ${line.unit}`}</span>
                                                {line.reversal && <Badge>{t('deliveries.reversal')}</Badge>}
                                                {can.correct && !line.reversal && !line.reversed && <Button variant="ghost" size="sm" onClick={() => { correct.clear(); setRightQty(''); setCorrectReason(''); setCorrecting({ delivery, line }); }}>{t('actions.correct_delivery_record')}</Button>}
                                            </li>
                                        ))}
                                    </ul>
                                    {delivery.papers.map((paper) => (
                                        <a key={paper.id} className="self-start underline" href={`/dokumen/${paper.id}`}>{`${t(paper.type === 'DOC-10' ? 'documents.doc_10' : 'documents.doc_08')}${paper.number === null ? '' : ` ${paper.number}`}`}</a>
                                    ))}
                                    {delivery.corrected && delivery.papers.some((paper) => paper.number !== null) && <p className="text-caption text-text-secondary">{t('deliveries.revise_paper')}</p>}
                                    {can.issue && (
                                        <div className="flex flex-wrap gap-2">
                                            {(['DOC-08', 'DOC-10'] as const).map((type) => (
                                                <Button key={type} variant="outline" size="sm" pending={prepare.pending} disabled={prepare.frozen}
                                                    onClick={() => { prepare.submit({ jenis: type, subjek: { delivery_id: delivery.id } }); }}>
                                                    {t('deliveries.action.issue', { dokumen: t(type === 'DOC-10' ? 'documents.doc_10' : 'documents.doc_08') })}
                                                </Button>
                                            ))}
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
                {shipment.closures.length > 0 && (
                    <section data-slot="delivery-closures" className="flex min-w-0 flex-col gap-2">
                        <h2 className="text-subhead">{t('deliveries.section.closures')}</h2>
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {shipment.closures.map((closure) => {
                                const line = shipment.lines.find((one) => one.id === closure.line) ?? null;

                                return (
                                    <li key={closure.id} className="flex min-w-0 flex-wrap items-center gap-2 py-2 text-body">
                                        <span className={closure.superseded ? 'line-through' : undefined}>{`${String(closure.no)}. ${t(`deliveries.kind.${closure.kind}` as TranslationKey)} · ${closure.quantity}`}</span>
                                        <DateText value={closure.date} />
                                        <span className="text-text-secondary">{closure.reason}</span>
                                        {can.close && !closure.superseded && closure.kind !== 'LOST_IN_TRANSIT' && line !== null && <Button variant="ghost" size="sm" onClick={() => { startClose(line, false, closure); }}>{t('deliveries.action.supersede')}</Button>}
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                )}
            </div>
            <ConfirmCommand
                open={closing !== null && !close.unknown}
                onOpenChange={(next) => { if (!next) setClosing(null); }}
                title={closing?.lost ? t('actions.record_loss_in_transit') : t('deliveries.action.close')}
                consequence={closing?.lost ? t('deliveries.confirm.lost') : t('deliveries.confirm.close')}
                confirmLabel={closing?.lost ? t('actions.record_loss_in_transit') : t('deliveries.action.close')}
                pending={close.pending}
                items={closing === null ? null : (
                    <div className="flex min-w-0 flex-col gap-3">
                        {!closing.lost && (
                            <FormField label={t('deliveries.field.closure_kind')} error={close.errors.jenis}>
                                {(control) => (
                                    <NativeSelect {...control} value={kind} onChange={(event) => { setKind(event.target.value as (typeof CLOSURES)[number]); }}>
                                        {CLOSURES.map((one) => <option key={one} value={one}>{t(`deliveries.kind.${one}`)}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                        )}
                        {closing.lost && closing.line.serialized ? (
                            <fieldset className="flex min-w-0 flex-col gap-1">
                                <legend className="text-body">{t('deliveries.field.lost_serials')}</legend>
                                {closing.line.serials.map((one) => (
                                    <label key={one.id} className="flex min-h-floor items-center gap-2 text-body">
                                        <Checkbox checked={lostSerials.includes(one.id)} onCheckedChange={(checked) => { setLostSerials((current) => (checked ? [...current, one.id] : current.filter((other) => other !== one.id))); }} />
                                        <span className="font-mono">{one.serial}</span>
                                    </label>
                                ))}
                            </fieldset>
                        ) : (
                            <FormField label={t('deliveries.field.quantity', { satuan: closing.line.unit })} error={close.errors.jumlah}>
                                {(control) => <DecimalInput {...control} scale={closing.line.ratio === 1 ? closing.line.scale : 3} value={closeQty} onValueChange={(next) => { setCloseQty(next ?? ''); }} />}
                            </FormField>
                        )}
                    </div>
                )}
                reason={{ value: closeReason, onChange: setCloseReason, error: close.errors.alasan ?? null }}
                onConfirm={() => {
                    if (closing === null) return;
                    const serialized = closing.lost && closing.line.serialized;
                    close.submit({
                        baris: closing.line.id,
                        jenis: closing.lost ? 'LOST_IN_TRANSIT' : kind,
                        jumlah: serialized ? String(lostSerials.length) : closeQty,
                        seri: serialized ? lostSerials : [],
                        ...(closing.supersedes === null ? {} : { menggantikan: closing.supersedes.id }),
                        alasan: closeReason,
                        tanggal: today,
                        tanggal_otomatis: true,
                    });
                }}
            />
            <ConfirmCommand
                open={correcting !== null && !correct.unknown}
                onOpenChange={(next) => { if (!next) setCorrecting(null); }}
                title={t('actions.correct_delivery_record')}
                consequence={t('deliveries.confirm.correct')}
                confirmLabel={t('actions.correct_delivery_record')}
                pending={correct.pending}
                items={correcting === null ? null : (
                    <FormField label={t('deliveries.field.right_quantity', { satuan: correcting.line.unit })} help={t('deliveries.field.right_quantity_help')} error={correct.errors.jumlah}>
                        {(control) => <DecimalInput {...control} scale={3} value={rightQty} onValueChange={(next) => { setRightQty(next ?? ''); }} />}
                    </FormField>
                )}
                reason={{ value: correctReason, onChange: setCorrectReason, error: correct.errors.alasan ?? null }}
                onConfirm={() => {
                    if (correcting === null) return;
                    correct.submit({ pengiriman: correcting.delivery.id, baris: correcting.line.id, jumlah: rightQty, alasan: correctReason, tanggal: today, tanggal_otomatis: true });
                }}
            />
        </AppShell>
    );
}
