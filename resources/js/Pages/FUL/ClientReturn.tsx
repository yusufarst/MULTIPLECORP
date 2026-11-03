// PG-034 *Retur dari Klien* (TASK-048; SCR-32; J-FUL-05; NAV-034; WF-FUL-05): delivered goods taken back from the client —
// per shipment line the units returned, each usable back into usable stock or damaged into UNUSABLE at a rack, with the case's
// reason and evidence and an optional new reservation (ROUTE-210) —; the cases with their open steps — the demand, the invoice,
// the refund and the damaged units' outcome —, "Tutup Kasus" (ROUTE-271), and "Balikkan (Tercatat Keliru)" on a restored unit
// (ROUTE-211). Net delivered falls; the delivery record stays. One column on the phone.
import type { FormDataConvertible } from '@inertiajs/core';
import { useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { type BusinessDate, BusinessDateControl } from '@/components/date-field';
import { DecimalInput, FormField } from '@/components/form';
import { DuplicateWarning, ReservationCut, type DuplicateMatch, type Reservation } from '@/components/notices';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateText } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Consumption = { id: string; lot: string; open: string; serials: { id: string; serial: string }[] };
type Line = { id: string; shipment: string; date: string; no: number; description: string; unit: string | null; base_unit: string | null; ratio: number; serialized: boolean; scale: number; delivered: string; returned: string; consumptions: Consumption[] };
type Restoration = { id: string; movement: string; date: string; quantity: string; standing: string; condition: string | null; contra?: string };
type Case = { id: string; state: string; reason: string | null; lock_version: number; date: string; lot: string | null; steps: { key: string; done: boolean }[]; restorations: Restoration[] };
type Can = { record: boolean; reverse: boolean; reserve: boolean; close: boolean; cost: boolean; upload: boolean };
type Page = { project: { id: string; number: string; title: string; client: string | null; state: string }; lines: Line[]; cases: Case[]; racks: { id: string; code: string; name: string }[]; proofs: { id: string; type: string; label: string }[]; can: Can };
type Props = { page: Page; today: string };
type Payload = Record<string, FormDataConvertible>;
type Reversing = { caseId: string; restoration: Restoration };

const figure = (value: string) => (/^\d+(\.\d+)?$/.test(value) ? value : '0');

/** The dialog closes once an answer arrives, so a refusal shows in place (IP-01). */
function useClosedOnAnswer(answer: unknown, close: () => void) {
    const [seen, setSeen] = useState(answer);

    if (seen !== answer) {
        setSeen(answer);

        if (answer !== null) close();
    }
}

export default function ClientReturn({ page, today }: Props) {
    const { t } = useTranslation();
    const { project, can } = page;
    const base = `/proyek/${project.id}/retur`;
    const record = useCommand<Payload>(() => base, 'post');
    const [target, setTarget] = useState('');
    const reverse = useCommand<Payload>(() => `/retur/${target}/balikkan`, 'post');
    const [closingCase, setClosingCase] = useState<Case | null>(null);
    const close = useCommand<Payload>(() => `/dokumen/kasus/${closingCase?.id ?? ''}/tutup`, 'post');
    const [lineId, setLineId] = useState(page.lines[0]?.id ?? '');
    const [quantities, setQuantities] = useState<Record<string, string>>({});
    const [picked, setPicked] = useState<string[]>([]);
    const [condition, setCondition] = useState<'USABLE' | 'UNUSABLE'>('USABLE');
    const [rack, setRack] = useState('');
    const [proof, setProof] = useState('');
    const [reserve, setReserve] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [confirming, setConfirming] = useState(false);
    const [reason, setReason] = useState('');
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [duplicateReason, setDuplicateReason] = useState('');
    const [reversing, setReversing] = useState<Reversing | null>(null);
    const [reverseQty, setReverseQty] = useState('');
    const [reverseReason, setReverseReason] = useState('');
    const [closeReason, setCloseReason] = useState('');
    const [cuts, setCuts] = useState<Record<string, string>>({});
    // IP-20: a reversal refused for the shortfall (C-06) stays open with the reservations the actor may cut.
    const shortfall = typeof reverse.state.shortfall === 'string' ? reverse.state.shortfall : null;
    const cutting = reverse.answer?.kind === 'REJECTED' && shortfall !== null;
    const choices = Array.isArray(reverse.state.reservations) ? (reverse.state.reservations as Reservation[]) : [];
    useClosedOnAnswer(record.answer, () => { setConfirming(false); });
    useClosedOnAnswer(reverse.answer, () => { if (!cutting) setReversing(null); });
    useClosedOnAnswer(close.answer, () => { setClosingCase(null); });
    const line = page.lines.find((one) => one.id === lineId) ?? null;
    const units = line === null ? [] : line.serialized
        ? line.consumptions.flatMap((consumption) => consumption.serials.filter((serial) => picked.includes(serial.id)).map((serial) => ({ konsumsi: consumption.id, jumlah: '1', satuan_dasar: true, kondisi: condition, rak: rack, seri: serial.id })))
        : line.consumptions.filter((consumption) => Number(figure(quantities[consumption.id] ?? '')) > 0).map((consumption) => ({ konsumsi: consumption.id, jumlah: quantities[consumption.id] ?? '0', satuan_dasar: true, kondisi: condition, rak: rack }));
    const matches = Array.isArray(record.state.matches) ? (record.state.matches as DuplicateMatch[]) : [];
    const warned = record.answer?.kind === 'REJECTED' && matches.length > 0;
    const ready = line !== null && units.length > 0 && rack !== '' && proof !== '';
    const answer = reverse.answer ?? close.answer;

    const submit = (extra: Payload = {}) => {
        if (line === null) return;
        record.submit({ baris: line.id, unit: units, alasan: reason, bukti: proof, tanggal: date.date, tanggal_otomatis: !date.chosen, ...(reserve === '' ? {} : { reservasi: reserve }), ...extra });
    };

    return (
        <AppShell title={t('pages.pg_034.title')} back={{ href: `/proyek/${project.id}`, label: t('shell.back') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_034.title')} crumbs={[{ label: project.number, href: `/proyek/${project.id}` }]} />
                <section data-slot="return-project" className="flex min-w-0 flex-col gap-1 text-body">
                    <p><a className="font-medium underline" href={`/proyek/${project.id}`}>{project.title}</a>{' · '}<Code>{project.number}</Code></p>
                    {project.client !== null && <p className="text-text-secondary">{project.client}</p>}
                    <p className="text-text-secondary">{t('returns_client.keeps_delivery')}</p>
                </section>
                {answer !== null && reversing === null && closingCase === null && <OutcomeMessage answer={answer} />}
                <section data-slot="return-lines" className="flex min-w-0 flex-col gap-3">
                    <h2 className="text-subhead">{t('returns_client.section.lines')}</h2>
                    {page.lines.length === 0 ? <RegionEmpty text={t('returns_client.no_lines')} /> : (
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {page.lines.map((one) => (
                                <li key={one.id} data-slot="return-line" className="flex min-w-0 flex-col gap-1 py-3 text-body">
                                    <span className="font-medium">{`${String(one.no)}. ${one.description}`}</span>
                                    <span className="text-caption text-text-secondary"><DateText value={one.date} />{` · ${t('returns_client.line_figures', { diterima: one.delivered, kembali: one.returned, satuan: one.base_unit ?? '' })}`}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
                {can.record && page.lines.some((one) => Number(one.delivered) > 0) && (
                    <fieldset data-slot="return-form" disabled={record.frozen} className="flex min-w-0 flex-col gap-3">
                        <legend className="text-subhead">{t('returns_client.section.record')}</legend>
                        <FormField label={t('returns_client.field.line')} error={record.errors.baris}>
                            {(control) => (
                                <NativeSelect {...control} value={lineId} onChange={(event) => { setLineId(event.target.value); setQuantities({}); setPicked([]); }}>
                                    {page.lines.filter((one) => Number(one.delivered) > 0).map((one) => <option key={one.id} value={one.id}>{`${String(one.no)}. ${one.description} · ${one.delivered} ${one.base_unit ?? ''}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        {line !== null && line.consumptions.map((consumption, index) => (
                            <div key={consumption.id} data-slot="return-unit" className="flex min-w-0 flex-col gap-2">
                                <span className="text-caption text-text-secondary"><Code>{consumption.lot}</Code>{` · ${t('returns_client.out', { jumlah: consumption.open })}`}</span>
                                {line.serialized ? (
                                    <fieldset className="flex min-w-0 flex-col gap-1">
                                        <legend className="text-body">{t('returns_client.field.serials')}</legend>
                                        {consumption.serials.map((serial) => (
                                            <label key={serial.id} className="flex min-h-floor items-center gap-2 text-body">
                                                <Checkbox checked={picked.includes(serial.id)} onCheckedChange={(checked) => { setPicked((current) => (checked ? [...current, serial.id] : current.filter((other) => other !== serial.id))); }} />
                                                <span className="font-mono">{serial.serial}</span>
                                            </label>
                                        ))}
                                    </fieldset>
                                ) : (
                                    <FormField label={t('returns_client.field.quantity', { satuan: line.base_unit ?? '' })} error={record.errors[`unit.${String(index)}.jumlah`]} className="w-48">
                                        {(control) => <DecimalInput {...control} scale={line.scale} value={quantities[consumption.id] ?? ''} onValueChange={(next) => { setQuantities((current) => ({ ...current, [consumption.id]: next ?? '' })); }} />}
                                    </FormField>
                                )}
                            </div>
                        ))}
                        <FormField label={t('returns_client.field.condition')} error={record.errors['unit.0.kondisi']}>
                            {(control) => (
                                <NativeSelect {...control} value={condition} onChange={(event) => { setCondition(event.target.value === 'UNUSABLE' ? 'UNUSABLE' : 'USABLE'); }}>
                                    <option value="USABLE">{t('stock.condition.USABLE')}</option>
                                    <option value="UNUSABLE">{t('stock.condition.UNUSABLE')}</option>
                                </NativeSelect>
                            )}
                        </FormField>
                        <FormField label={t('returns_client.field.rack')} error={record.errors['unit.0.rak']}>
                            {(control) => (
                                <NativeSelect {...control} value={rack} onChange={(event) => { setRack(event.target.value); }}>
                                    <option value="">{t('dispatch.substitute.choose')}</option>
                                    {page.racks.map((one) => <option key={one.id} value={one.id}>{`${one.code} · ${one.name}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        <FormField label={t('returns_client.field.proof')} error={record.errors.bukti}>
                            {(control) => (
                                <NativeSelect {...control} value={proof} onChange={(event) => { setProof(event.target.value); }}>
                                    <option value="">{t('dispatch.substitute.choose')}</option>
                                    {page.proofs.map((one) => <option key={one.id} value={one.id}>{one.label}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        {can.upload && <a className="self-start text-body underline" href="/dokumen/bukti">{t('deliveries.upload_proof')}</a>}
                        {can.reserve && condition === 'USABLE' && (
                            <FormField label={t('returns_client.field.reserve')} help={t('returns_client.field.reserve_help')} error={record.errors.reservasi} className="w-48">
                                {(control) => <DecimalInput {...control} scale={line?.scale ?? 0} value={reserve} onValueChange={(next) => { setReserve(next ?? ''); }} />}
                            </FormField>
                        )}
                        <BusinessDateControl today={today} value={date} onChange={setDate} />
                        {warned ? (
                            <div className="flex min-w-0 flex-col gap-2">
                                <DuplicateWarning matches={matches} message={record.answer?.text} acknowledged={acknowledged} onAcknowledge={setAcknowledged}
                                    reason={{ value: duplicateReason, onChange: setDuplicateReason, error: record.errors.reason ?? null }} canContinue />
                                <Button className="self-start" pending={record.pending} disabled={record.frozen} onClick={() => { submit({ acknowledged, reason: duplicateReason }); }}>{t('actions.continue')}</Button>
                            </div>
                        ) : (!confirming && record.answer !== null && <OutcomeMessage answer={record.answer} action={record.unknown ? <Button variant="outline" size="sm" onClick={record.retry}>{t('actions.try_again')}</Button> : undefined} />)}
                        {(['tanggal', 'unit', 'baris'] as const).map((field) => (record.errors[field] === undefined ? null : <p key={field} role="alert" className="text-body text-danger-fg">{record.errors[field]}</p>))}
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" render={<a href={`/proyek/${project.id}`} />}>{t('actions.cancel')}</Button>
                            <Button pending={record.pending} disabled={!ready || record.frozen} onClick={() => { record.clear(); setConfirming(true); }}>{t('returns_client.action.record')}</Button>
                        </div>
                    </fieldset>
                )}
                <section data-slot="return-cases" className="flex min-w-0 flex-col gap-3">
                    <h2 className="text-subhead">{t('returns_client.section.cases')}</h2>
                    {page.cases.length === 0 ? <RegionEmpty text={t('returns_client.none')} /> : (
                        <ol className="flex min-w-0 flex-col gap-4">
                            {page.cases.map((one) => (
                                <li key={one.id} data-slot="return-case" className="flex min-w-0 flex-col gap-2 rounded-md border border-border-subtle p-3 text-body">
                                    <span className="flex flex-wrap items-center gap-2 font-medium"><DateText value={one.date} />{one.lot !== null && <Code>{one.lot}</Code>}<Badge>{t(one.state === 'OPEN' ? 'returns_client.state.open' : 'returns_client.state.closed')}</Badge></span>
                                    {one.reason !== null && <span className="text-text-secondary">{one.reason}</span>}
                                    {one.steps.length > 0 && (
                                        <ul role="list" aria-label={t('cases.steps')} className="flex min-w-0 flex-col gap-1">
                                            {one.steps.map((step) => <li key={step.key}>{`${t(`cases.step.${step.key}` as TranslationKey)} · ${t(step.done ? 'cases.step_done' : 'cases.step_open')}`}</li>)}
                                        </ul>
                                    )}
                                    <ul role="list" className="flex min-w-0 flex-col gap-1">
                                        {one.restorations.map((restoration) => (
                                            <li key={restoration.id} className="flex min-w-0 flex-wrap items-center gap-2">
                                                <span>{`${restoration.quantity}${restoration.condition === null ? '' : ` · ${t(`stock.condition.${restoration.condition}` as TranslationKey)}`}`}</span>
                                                {restoration.standing !== restoration.quantity && <span className="text-caption text-text-secondary">{t('returns_client.standing', { jumlah: restoration.standing })}</span>}
                                                {can.cost && restoration.contra !== undefined && <span className="text-caption">{t('returns_client.contra', { biaya: restoration.contra })}</span>}
                                                {can.reverse && Number(restoration.standing) > 0 && <Button variant="ghost" size="sm" onClick={() => { reverse.clear(); setCuts({}); setReverseQty(restoration.standing); setReverseReason(''); setTarget(restoration.movement); setReversing({ caseId: one.id, restoration }); }}>{t('returns_client.action.reverse')}</Button>}
                                            </li>
                                        ))}
                                    </ul>
                                    {can.close && one.state === 'OPEN' && <Button variant="outline" size="sm" className="self-start" onClick={() => { close.clear(); setCloseReason(''); setClosingCase(one); }}>{t('actions.close_case')}</Button>}
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            </div>
            <ConfirmCommand
                open={confirming && !record.unknown}
                onOpenChange={(next) => { if (!next) setConfirming(false); }}
                title={t('returns_client.action.record')}
                consequence={t('returns_client.confirm.record')}
                confirmLabel={t('returns_client.action.record')}
                pending={record.pending}
                reason={{ value: reason, onChange: setReason, error: record.errors.alasan ?? null }}
                onConfirm={() => { setAcknowledged([]); setDuplicateReason(''); submit(); }}
            />
            <ConfirmCommand
                open={reversing !== null && !reverse.unknown}
                onOpenChange={(next) => { if (!next) setReversing(null); }}
                title={t('returns_client.action.reverse')}
                consequence={t('returns_client.confirm.reverse')}
                confirmLabel={t('returns_client.action.reverse')}
                destructive
                pending={reverse.pending}
                answer={cutting ? reverse.answer : null}
                items={reversing === null ? null : (
                    <div className="flex min-w-0 flex-col gap-3">
                        <FormField label={t('returns_client.field.reverse_quantity')} error={reverse.errors['unit.0.jumlah']}>
                            {(control) => <DecimalInput {...control} scale={3} value={reverseQty} onValueChange={(next) => { setReverseQty(next ?? ''); }} />}
                        </FormField>
                        {cutting && choices.length > 0 && (
                            <ReservationCut shortfall={t('returns_client.shortfall', { kurang: shortfall })} unit="" reservations={choices} cuts={cuts} onCut={(id, value) => { setCuts({ ...cuts, [id]: value ?? '' }); }} scale={3} />
                        )}
                    </div>
                )}
                reason={{ value: reverseReason, onChange: setReverseReason, error: reverse.errors.alasan ?? null }}
                onConfirm={() => {
                    if (reversing === null) return;
                    const potong = Object.entries(cuts).filter(([, qty]) => Number(figure(qty)) > 0).map(([reservasi, jumlah]) => ({ reservasi, jumlah }));
                    reverse.submit({ unit: [{ pemulihan: reversing.restoration.id, jumlah: reverseQty }], potong, alasan: reverseReason, tanggal: today, tanggal_otomatis: true, asal: base });
                }}
            />
            <ConfirmCommand
                open={closingCase !== null && !close.unknown}
                onOpenChange={(next) => { if (!next) setClosingCase(null); }}
                title={t('actions.close_case')}
                consequence={t('returns_client.confirm.close')}
                confirmLabel={t('actions.close_case')}
                pending={close.pending}
                reason={{ value: closeReason, onChange: setCloseReason, error: close.errors.alasan ?? null }}
                onConfirm={() => {
                    if (closingCase === null) return;
                    close.submit({ lock_version: closingCase.lock_version, alasan: closeReason, asal: 'retur-klien', proyek: project.id });
                }}
            />
        </AppShell>
    );
}
