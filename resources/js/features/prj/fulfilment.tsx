// PG-011's commercial confirmation and *Item dan pemenuhan* (WF-PRJ-02; WF-FUL-01; J-PRJ-02; J-FUL-01; TASK-034):
// before the confirmation the way to confirm — "Konfirmasi Proyek" on the client's registered order evidence after its
// confirmation (IP-08; MSG-39), or the quotation —; after it the confirmation's kind, date and evidence and, per
// confirmed line, its mode and progress against the pinned demand by mode, derived each time (L-01): *Gudang* reserved,
// dispatched, delivered, cancelled and the shortage, availability with `stock.view`; *Kirim langsung* confirmed,
// delivered, cancelled; *Jasa* handed over, cancelled. "Batalkan Sisa" (destructive, MSG-40's reason; IP-09; IP-20) and
// "Ubah Cara Pemenuhan" (reason; L-21) per line, each present with its capability. "Reservasi" on a confirmed warehouse
// line with room left sends INV's own command (ROUTE-184; J-INV-02; TASK-038), at most the line's reservable quantity and
// the pooled availability — the server re-reads both at commit (AX-01). Nothing here records money: the company SPK is
// its own command (IP-19).
import { type ReactNode, useRef, useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { BusinessDateControl, type BusinessDate } from '@/components/date-field';
import { DecimalInput, FormField } from '@/components/form';
import { OutcomeMessage } from '@/components/outcome-message';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { DateText, Money } from '@/components/values';
import { quantity as formatQuantity } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Mode = 'WAREHOUSE' | 'DROPSHIP' | 'SERVICE';
export type FulfilmentLine = {
    handle: string;
    no: number;
    kind: 'GOODS' | 'SERVICE';
    mode: Mode;
    product: string;
    description: string;
    unit: string;
    demand: string;
    reserved: string;
    dispatched: string;
    dropship: string;
    handed_over: string;
    delivered: string;
    cancelled: string;
    remainder: string;
    shortage: string;
    reservable: string;
    split_from: number | null;
    available?: string;
    price?: string;
    value?: string;
    can: { reserve: boolean; cancel: boolean; replan: boolean };
};
export type Fulfilment = {
    confirmation: { kind: 'QUOTATION_APPROVAL' | 'NO_QUOTATION' | 'MIGRATION'; business_date: string; decided_by: string | null; reason: string | null; evidence: { id: string; type: string } | null } | null;
    lines: FulfilmentLine[];
    can: { confirm: boolean; spk: boolean };
    spk: string | null;
    evidence: { id: string; type: string; issuer: string; reference: string | null; date: string }[];
};
type Payload = Record<string, string | number | boolean | null | Record<string, string>[]>;

const modeName = (mode: string): TranslationKey => `projects.mode.${mode.toLowerCase()}` as TranslationKey;
const evidenceName = (type: string): TranslationKey => `fulfilment.evidence_type.${type.toLowerCase()}` as TranslationKey;
const amount = (value: string, unit: string): string => formatQuantity(value.replace(/\.?0+$/, '') || '0', unit);
const positive = (value: string): boolean => /[1-9]/.test(value);
// The hint's ceiling only — the line's reservable quantity, or the availability when lower; AX-01 decides at commit.
const ceiling = (line: FulfilmentLine): string => (line.available !== undefined && Number(line.available) < Number(line.reservable) ? line.available : line.reservable);

function Fact({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex min-w-0 flex-col">
            <dt className="text-caption text-text-secondary">{label}</dt>
            <dd className="text-body text-text-primary tabular-nums">{value}</dd>
        </div>
    );
}

/** One confirmed line: its mode and the progress its mode counts (WF-FUL-01; PX-01). */
function LineProgress({ line, onReserve, onCancel, onReplan }: { line: FulfilmentLine; onReserve: () => void; onCancel: () => void; onReplan: () => void }) {
    const { t } = useTranslation();
    const facts: [string, string][] =
        line.mode === 'WAREHOUSE'
            ? [['reserved', line.reserved], ['dispatched', line.dispatched], ['delivered', line.delivered], ['cancelled', line.cancelled]]
            : line.mode === 'DROPSHIP'
              ? [['dropship', line.dropship], ['delivered', line.delivered], ['cancelled', line.cancelled]]
              : [['handed_over', line.handed_over], ['cancelled', line.cancelled]];

    return (
        <li data-line={line.no} className="flex min-w-0 flex-col gap-3 py-4">
            <div className="flex min-w-0 flex-wrap items-center gap-3">
                <span className="min-w-0 font-semibold text-text-primary">{`${String(line.no)}. ${line.description}`}</span>
                <span className="rounded-sm border border-border-subtle px-2 text-caption text-text-secondary">{t(modeName(line.mode))}</span>
                {line.split_from !== null && <span className="text-caption text-text-secondary">{t('fulfilment.split_from', { n: line.split_from })}</span>}
            </div>
            <dl className="grid min-w-0 grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3 lg:grid-cols-6">
                <Fact label={t('fulfilment.field.demand')} value={amount(line.demand, line.unit)} />
                {facts.map(([key, value]) => (
                    <Fact key={key} label={t(`fulfilment.field.${key}` as TranslationKey)} value={amount(value, line.unit)} />
                ))}
                <Fact label={t('fulfilment.field.remainder')} value={amount(line.remainder, line.unit)} />
                {line.mode === 'WAREHOUSE' && positive(line.shortage) && <Fact label={t('fulfilment.field.shortage')} value={amount(line.shortage, line.unit)} />}
                {line.available !== undefined && line.mode === 'WAREHOUSE' && <Fact label={t('fulfilment.field.available')} value={amount(line.available, line.unit)} />}
                {line.value !== undefined && <Fact label={t('fulfilment.field.value')} value={<Money value={line.value} />} />}
            </dl>
            {(line.can.reserve || line.can.cancel || line.can.replan) && (
                <div className="flex flex-wrap gap-2">
                    {line.can.reserve && (
                        <Button size="sm" onClick={onReserve}>
                            {t('actions.reserve')}
                        </Button>
                    )}
                    {line.can.replan && (
                        <Button variant="outline" size="sm" onClick={onReplan}>
                            {t('fulfilment.action.replan')}
                        </Button>
                    )}
                    {line.can.cancel && (
                        <Button variant="outline" size="sm" onClick={onCancel}>
                            {t('fulfilment.action.cancel')}
                        </Button>
                    )}
                </div>
            )}
        </li>
    );
}

export function FulfilmentSection({ projectId, lockVersion, fulfilment, today, title }: { projectId: string; lockVersion: number; fulfilment: Fulfilment; today: string; title: (children: ReactNode) => ReactNode }) {
    const { t } = useTranslation();
    const confirm = useCommand<Payload>(() => `/proyek/${projectId}/konfirmasi`, 'post');
    const cancel = useCommand<Payload>(() => `/proyek/${projectId}/batalkan-sisa`, 'post');
    const replan = useCommand<Payload>(() => `/proyek/${projectId}/ubah-pemenuhan`, 'post');
    const reserve = useCommand<Payload>(() => '/gudang/reservasi', 'post');
    // The company SPK, the confirmation's optional output as its own command (WF-PRJ-02; IP-19): ROUTE-221 drafts it and opens its composer.
    const spk = useCommand<Record<string, string | Record<string, string>>>(() => '/dokumen', 'post');
    const [confirming, setConfirming] = useState(false);
    const [evidence, setEvidence] = useState(fulfilment.evidence[0]?.id ?? '');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [cancelling, setCancelling] = useState<FulfilmentLine | null>(null);
    const [replanning, setReplanning] = useState<FulfilmentLine | null>(null);
    const [reserving, setReserving] = useState<FulfilmentLine | null>(null);
    // An open "Reservasi" intent stays with its line: another line leaves it, never retrying it there (IP-02).
    const reserved = useRef<string | null>(null);
    const [quantity, setQuantity] = useState('');
    const [mode, setMode] = useState<Mode>('DROPSHIP');
    const [reason, setReason] = useState('');
    // A success shows once, as the page's answer (PageAnswer); a refusal or an unknown outcome shows here (IP-01; IP-03).
    const answers = [confirm, cancel, replan, reserve, spk].map((command, index) => (command.answer === null || command.answer.kind === 'COMMITTED' ? null : <OutcomeMessage key={index} answer={command.answer} />));
    const confirmation = fulfilment.confirmation;

    return title(
        <>
            {answers}
            {confirmation === null ? (
                <div className="flex min-w-0 flex-col gap-3">
                    <p className="text-body text-text-secondary">{t('fulfilment.unconfirmed')}</p>
                    {fulfilment.can.confirm && fulfilment.evidence.length > 0 && (
                        <div>
                            <Button onClick={() => { confirm.clear(); setConfirming(true); }}>{t('fulfilment.action.confirm')}</Button>
                        </div>
                    )}
                    {fulfilment.can.confirm && fulfilment.evidence.length === 0 && (
                        <p className="text-body text-text-secondary">
                            {t('fulfilment.confirm.no_evidence')}{' '}
                            <a href="/dokumen/bukti" className="text-accent-link underline underline-offset-2">{t('fulfilment.confirm.upload')}</a>
                        </p>
                    )}
                </div>
            ) : (
                <div className="flex min-w-0 flex-col gap-2">
                    <p className="text-body text-text-primary">
                        {t(`fulfilment.kind.${confirmation.kind.toLowerCase()}` as TranslationKey)}
                        {' · '}
                        <DateText value={confirmation.business_date} />
                        {confirmation.decided_by !== null && ` · ${t('fulfilment.decided_by', { nama: confirmation.decided_by })}`}
                    </p>
                    {fulfilment.can.spk && (
                        <div className="flex flex-wrap gap-2">
                            {fulfilment.spk === null ? (
                                <Button variant="outline" pending={spk.pending} onClick={() => { spk.submit({ jenis: 'DOC-09', subjek: { project_id: projectId } }); }}>
                                    {t('fulfilment.action.spk')}
                                </Button>
                            ) : (
                                <Button variant="outline" render={<a href={`/dokumen/${fulfilment.spk}`} />}>
                                    {t('fulfilment.action.open_spk')}
                                </Button>
                            )}
                        </div>
                    )}
                    {confirmation.evidence !== null && (
                        <a href={`/dokumen/bukti?bukti=${confirmation.evidence.id}`} className="text-body text-accent-link underline underline-offset-2">
                            {t(evidenceName(confirmation.evidence.type))}
                        </a>
                    )}
                    <ol aria-label={t('fulfilment.lines')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {fulfilment.lines.map((line) => (
                            <LineProgress
                                key={line.handle}
                                line={line}
                                onReserve={() => { if (reserved.current === line.handle) reserve.clear(); else reserve.leave(); reserved.current = line.handle; setQuantity(ceiling(line).replace(/\.?0+$/, '')); setReserving(line); }}
                                onCancel={() => { cancel.clear(); setQuantity(line.remainder.replace(/\.?0+$/, '')); setReason(''); setCancelling(line); }}
                                onReplan={() => { replan.clear(); setMode(line.mode === 'WAREHOUSE' ? 'DROPSHIP' : 'WAREHOUSE'); setReason(''); setReplanning(line); }}
                            />
                        ))}
                    </ol>
                </div>
            )}
            <ConfirmCommand
                open={confirming}
                onOpenChange={setConfirming}
                title={t('fulfilment.confirm.title')}
                consequence={t('fulfilment.confirm.consequence')}
                items={
                    <div className="flex min-w-0 flex-col gap-4">
                        {fulfilment.evidence.length === 0 ? (
                            <p>{t('fulfilment.confirm.no_evidence')}</p>
                        ) : (
                            <FormField label={t('fulfilment.field.evidence')} required error={confirm.errors.bukti}>
                                {(control) => (
                                    <NativeSelect {...control} value={evidence} onChange={(event) => { setEvidence(event.target.value); }}>
                                        {fulfilment.evidence.map((option) => (
                                            <option key={option.id} value={option.id}>
                                                {`${t(evidenceName(option.type))} · ${option.issuer}${option.reference === null ? '' : ` · ${option.reference}`}`}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                )}
                            </FormField>
                        )}
                        <a href="/dokumen/bukti" className="text-accent-link underline underline-offset-2">{t('fulfilment.confirm.upload')}</a>
                        <BusinessDateControl today={today} value={date} onChange={setDate} />
                        {confirm.errors.tanggal !== undefined && <p className="text-danger">{confirm.errors.tanggal}</p>}
                    </div>
                }
                confirmLabel={t('fulfilment.action.confirm')}
                pending={confirm.pending}
                answer={confirm.answer}
                onConfirm={() => {
                    if (evidence === '') return;
                    confirm.submit({ lock_version: lockVersion, bukti: evidence, tanggal: date.date, tanggal_otomatis: !date.chosen }, () => { setConfirming(false); });
                }}
            />
            <ConfirmCommand
                open={cancelling !== null}
                onOpenChange={(open) => { if (!open) setCancelling(null); }}
                title={t('fulfilment.cancel.title', { n: cancelling?.no ?? 0 })}
                consequence={t('fulfilment.cancel.consequence')}
                items={
                    <div className="flex min-w-0 flex-col gap-4">
                        <FormField label={t('fulfilment.field.cancel_quantity', { satuan: cancelling?.unit ?? '' })} required error={cancel.errors['baris.0.jumlah']}>
                            {(control) => <DecimalInput {...control} value={quantity} scale={3} unit={cancelling?.unit} onValueChange={(value) => { setQuantity(value ?? ''); }} />}
                        </FormField>
                        <BusinessDateControl today={today} value={date} onChange={setDate} />
                    </div>
                }
                reason={{ value: reason, onChange: setReason, error: cancel.errors.alasan ?? null }}
                confirmLabel={t('fulfilment.action.cancel')}
                destructive
                pending={cancel.pending}
                answer={cancel.answer}
                onConfirm={() => {
                    if (cancelling === null) return;
                    cancel.submit({ baris: [{ baris: cancelling.handle, jumlah: quantity }], alasan: reason, tanggal: date.date, tanggal_otomatis: !date.chosen }, () => { setCancelling(null); });
                }}
            />
            <ConfirmCommand
                open={replanning !== null}
                onOpenChange={(open) => { if (!open) setReplanning(null); }}
                title={t('fulfilment.replan.title', { n: replanning?.no ?? 0 })}
                consequence={t('fulfilment.replan.consequence')}
                items={
                    <FormField label={t('fulfilment.field.mode')} required error={replan.errors.cara}>
                        {(control) => (
                            <NativeSelect {...control} value={mode} onChange={(event) => { setMode(event.target.value === 'WAREHOUSE' ? 'WAREHOUSE' : 'DROPSHIP'); }}>
                                {(['WAREHOUSE', 'DROPSHIP'] as const).filter((option) => option !== replanning?.mode).map((option) => (
                                    <option key={option} value={option}>{t(modeName(option))}</option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>
                }
                reason={{ value: reason, onChange: setReason, error: replan.errors.alasan ?? null }}
                confirmLabel={t('fulfilment.action.replan')}
                pending={replan.pending}
                answer={replan.answer}
                onConfirm={() => {
                    if (replanning === null) return;
                    replan.submit({ baris: replanning.handle, cara: mode, alasan: reason }, () => { setReplanning(null); });
                }}
            />
            <ConfirmCommand
                open={reserving !== null}
                onOpenChange={(open) => { if (!open) setReserving(null); }}
                title={t('reservations.reserve.title', { n: reserving?.no ?? 0 })}
                consequence={t('reservations.reserve.consequence')}
                items={
                    <div className="flex min-w-0 flex-col gap-2">
                        <FormField label={t('reservations.field.quantity', { satuan: reserving?.unit ?? '' })} required error={reserve.errors.jumlah}>
                            {(control) => <DecimalInput {...control} value={quantity} scale={3} unit={reserving?.unit} onValueChange={(value) => { setQuantity(value ?? ''); }} />}
                        </FormField>
                        {reserving !== null && <p className="text-caption text-text-secondary">{t('reservations.reserve.ceiling', { jumlah: amount(ceiling(reserving), reserving.unit) })}</p>}
                    </div>
                }
                confirmLabel={t('actions.reserve')}
                pending={reserve.pending}
                answer={reserve.answer}
                onConfirm={() => {
                    if (reserving === null) return;
                    reserve.submit({ proyek: projectId, baris: reserving.handle, jumlah: quantity }, () => { setReserving(null); });
                }}
            />
        </>,
    );
}
