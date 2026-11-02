// PG-028 "Retur ke Pemasok" (TASK-044; SCR-26; J-PUR-03; NAV-028): the units of one lot of a purchase returned to its
// supplier with the reason and the cut (ROUTE-176; IP-20), scanned on the phone; the case's outcome (ROUTE-177) and its
// figures, an earlier outcome corrected, "Tutup Kasus" only once returned cost = refunded + replaced + unrecovered (ROUTE-271; C-46), its timeline.
import type { FormDataConvertible } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { CorrectionTrail, type CorrectionLink } from '@/components/correction-trail';
import { type BusinessDate, BusinessDateControl } from '@/components/date-field';
import { DecimalInput, FormField } from '@/components/form';
import { ReservationCut, type Reservation } from '@/components/notices';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { ScanField, ScanNotice, useScanGuard } from '@/components/scan-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateText, Money } from '@/components/values';
import { ChoicesFind } from '@/features/inv/dispatch-line';
import AppShell from '@/layouts/AppShell';
import * as decimal from '@/lib/decimal';
import { money } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { previousPage } from '@/lib/previous-page';
import { useCommand } from '@/lib/use-command';

const PAGE = '/gudang/retur-pemasok';
const OUTCOMES = ['REPLACEMENT', 'REFUND', 'CREDIT', 'UNRECOVERED'] as const;
type OutcomeKind = (typeof OUTCOMES)[number];

type Condition = 'USABLE' | 'UNUSABLE';
type Page<Row> = { rows: Row[]; more: string | null; cursor_failed: boolean };
type Row = { id: string; state: 'OPEN' | 'CLOSED'; date: string; purchase: string; number: string; supplier: string; lot: string; product: string; open: string | null };
type Unit = { lot: string; rack: string; condition: Condition; serial: string | null; qty: string; cost: string | null };
type Event = { action: string; reason: string | null; by: string | null; at: string; outcome: string | null; correction: boolean };
type Detail = {
    id: string;
    state: 'OPEN' | 'CLOSED';
    reason: string;
    lock_version: number;
    date: string;
    purchase: string;
    number: string;
    supplier: string;
    lot: string;
    product: string;
    scale: number;
    causal: string | null;
    figures: { returned: string; refunded: string; replaced: string; unrecovered: string; open: string } | null;
    replacement: { returned: string; expected: string; received: string; room: string };
    units: Unit[];
    events: Event[];
    trail: { corrects: CorrectionLink[]; corrected_by: CorrectionLink[] };
    can: { outcome: boolean; close: boolean };
};
type LotChoice = { lot: string; code: string; product: string; name: string; serialized: boolean; scale: number; unit: string };
type PurchaseForm = { id: string; number: string; supplier: string; lots: LotChoice[] };
type Place = { lot: string; code: string; location: string; rack: string; condition: Condition; qty: string };
type Serial = { handle: string; serial: string; lot: string; location: string; condition: Condition; code: string; rack: string };
type Units = { places: Place[]; serials: Serial[]; more: boolean; available: string; reservations: Reservation[] };
type Proofs = { find: string | null; rows: { id: string; label: string }[]; more: boolean };
type Can = { record: boolean; close: boolean; reserve: boolean; allocate: boolean; evidence: boolean };
type Props = {
    list: Page<Row>;
    case: Detail | null;
    purchase: PurchaseForm | null;
    lot: string | null;
    units: Units | null;
    proofs?: Proofs | { failed: true } | null;
    today: string;
    can: Can;
};

const figure = (value: string) => (/^\d+(\.\d+)?$/.test(value) ? value : '0');
const placeKey = (place: { location: string; condition: Condition }) => `${place.location}|${place.condition}`;

/** A command's success on the page its answer opened, once (IP-01). */
function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

/** The confirmation closes once an answer arrives, so a refusal shows in place (IP-01). */
function useClosedOnAnswer(answer: unknown, close: () => void) {
    const [seen, setSeen] = useState(answer);

    if (seen !== answer) {
        setSeen(answer);

        if (answer !== null) close();
    }
}

function ReturnForm({ purchase, lot, units, today, can }: { purchase: PurchaseForm; lot: string | null; units: Units | null; today: string; can: Can }) {
    const { t } = useTranslation();
    const command = useCommand<Record<string, FormDataConvertible>>(() => `/pembelian/${purchase.id}/retur`, 'post');
    const guard = useScanGuard({ hasScanField: true });
    const chosen = purchase.lots.find((one) => one.lot === lot) ?? null;
    const [quantities, setQuantities] = useState<Record<string, string>>({});
    const [serials, setSerials] = useState<string[]>([]);
    const [cuts, setCuts] = useState<Record<string, string>>({});
    const [code, setCode] = useState('');
    const [scanError, setScanError] = useState<string | null>(null);
    const [reason, setReason] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const frozen = command.pending || command.unknown;
    useClosedOnAnswer(command.answer, () => { setConfirming(false); });
    const serialized = chosen?.serialized ?? false;
    const places = units?.places ?? [];
    const lotSerials = units?.serials ?? [];
    const picks = serialized
        ? lotSerials.filter((one) => serials.includes(one.handle)).map((one) => ({ rak: one.location, kondisi: one.condition, jumlah: '1', seri: one.handle, usable: one.condition === 'USABLE' }))
        : places.filter((place) => Number(figure(quantities[placeKey(place)] ?? '')) > 0).map((place) => ({ rak: place.location, kondisi: place.condition, jumlah: quantities[placeKey(place)] ?? '0', seri: null, usable: place.condition === 'USABLE' }));
    const usable = decimal.add('0', ...picks.filter((pick) => pick.usable).map((pick) => pick.jumlah));
    const short = decimal.add(usable, `-${units?.available ?? '0'}`);
    const needsCut = Number(short) > 0;
    const cut = decimal.add('0', ...Object.values(cuts).map(figure));
    const ready = chosen !== null && picks.length > 0 && (!needsCut || (can.reserve && Number(cut) >= Number(short)));

    const scanned = (typed: string) => {
        const match = lotSerials.find((one) => one.serial.toLowerCase() === typed.trim().toLowerCase());

        if (match === undefined) {
            setScanError(t('messages.msg_25', { kode: typed }));

            return;
        }

        setScanError(null);
        setSerials((current) => (current.includes(match.handle) ? current : [...current, match.handle]));
        setCode('');
    };

    return (
        <section data-slot="return-form" className="flex min-w-0 flex-col gap-4">
            <h2 className="text-subhead">{t('returns.section.new', { nomor: purchase.number })}</h2>
            <p className="text-body text-text-secondary">{purchase.supplier}</p>
            {command.answer !== null && command.answer.kind !== 'COMMITTED' && !confirming && <OutcomeMessage answer={command.answer} action={command.unknown ? <Button variant="outline" size="sm" onClick={command.retry}>{t('actions.try_again')}</Button> : undefined} />}
            <FormField label={t('returns.field.lot')} error={command.errors.lot}>
                {(control) => (
                    <NativeSelect {...control} value={lot ?? ''} disabled={frozen} onChange={(event) => { router.get(PAGE, event.target.value === '' ? { pembelian: purchase.id } : { pembelian: purchase.id, lot: event.target.value }); }}>
                        <option value="">{t('returns.field.choose_lot')}</option>
                        {purchase.lots.map((one) => <option key={one.lot} value={one.lot}>{`${one.code} · ${one.name}`}</option>)}
                    </NativeSelect>
                )}
            </FormField>
            {purchase.lots.length === 0 && <RegionEmpty text={t('returns.no_lots')} />}
            {chosen !== null && units !== null && (
                <>
                    {serialized ? (
                        <fieldset disabled={frozen} data-slot="return-serials" className="flex min-w-0 flex-col gap-2">
                            <legend className="text-body font-medium">{t('returns.serials')}</legend>
                            <ScanNotice notice={guard.notice} />
                            <ScanField value={code} onValueChange={setCode} onLookup={scanned} error={scanError} />
                            {lotSerials.length === 0 ? <RegionEmpty text={t('returns.no_units')} /> : (
                                <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                    {lotSerials.map((one) => (
                                        <li key={one.handle} className="py-2">
                                            <label className="flex min-h-floor items-center gap-2 text-body">
                                                <Checkbox checked={serials.includes(one.handle)} onCheckedChange={(checked) => { setSerials(checked ? [...serials, one.handle] : serials.filter((other) => other !== one.handle)); }} />
                                                <span className="font-mono">{one.serial}</span>
                                                <span className="text-text-secondary">{`${one.rack} · ${t(`stock.condition.${one.condition}`)}`}</span>
                                            </label>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </fieldset>
                    ) : (
                        <fieldset disabled={frozen} data-slot="return-places" className="flex min-w-0 flex-col gap-2">
                            <legend className="text-body font-medium">{t('returns.field.units')}</legend>
                            {places.length === 0 ? <RegionEmpty text={t('returns.no_units')} /> : (
                                <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                    {places.map((place) => {
                                        const key = placeKey(place);

                                        return (
                                            <li key={key} data-slot="return-place" className="flex min-w-0 flex-wrap items-end gap-3 py-2 text-body">
                                                <span className="flex min-w-0 flex-1 flex-wrap items-center gap-2"><Code>{place.code}</Code><span>{`${place.rack} · ${t(`stock.condition.${place.condition}`)}`}</span><span>{`${place.qty} ${chosen.unit}`}</span></span>
                                                <FormField label={t('adjustments.field.quantity', { kode: place.code, rak: place.rack })} className="w-40">
                                                    {(control) => <DecimalInput {...control} scale={chosen.scale} value={quantities[key] ?? ''} onValueChange={(next) => { setQuantities({ ...quantities, [key]: next ?? '' }); }} />}
                                                </FormField>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </fieldset>
                    )}
                    {needsCut && (can.reserve
                        ? <ReservationCut shortfall={t('stock.reverse.shortfall', { kurang: short })} unit={chosen.unit} reservations={units.reservations} cuts={cuts} scale={chosen.scale} onCut={(id, value) => { setCuts({ ...cuts, [id]: value ?? '' }); }} />
                        : <p role="alert" className="text-body font-medium text-danger-fg">{t('receiving.refusal.reservations')}</p>)}
                    <Button size="lg" className="self-start" disabled={!ready || frozen} onClick={() => { command.clear(); setConfirming(true); }}>{t('pages.pg_028.title')}</Button>
                </>
            )}
            <ConfirmCommand
                open={confirming && !command.unknown}
                onOpenChange={setConfirming}
                title={t('pages.pg_028.title')}
                consequence={t('returns.confirm.return')}
                confirmLabel={t('pages.pg_028.title')}
                pending={command.pending}
                items={<BusinessDateControl today={today} value={date} onChange={setDate} />}
                reason={{ value: reason, onChange: setReason, error: command.errors.alasan ?? null }}
                onConfirm={() => {
                    command.submit({
                        lot: lot ?? '',
                        unit: picks.map((pick) => ({ rak: pick.rak, kondisi: pick.kondisi, jumlah: pick.jumlah, seri: pick.seri })),
                        potong: needsCut ? Object.entries(cuts).filter(([, qty]) => Number(figure(qty)) > 0).map(([reservasi, jumlah]) => ({ reservasi, jumlah })) : [],
                        alasan: reason,
                        kebijakan_seri: serialized,
                        kebijakan_skala: chosen?.scale ?? 0,
                        tanggal: date.date,
                        tanggal_otomatis: !date.chosen,
                    }, () => { setConfirming(false); });
                }}
            />
        </section>
    );
}

function CaseDetail({ detail, proofs, proofsFailed, today, can }: { detail: Detail; proofs: Proofs | null; proofsFailed: boolean; today: string; can: Can }) {
    const { t } = useTranslation();
    const outcome = useCommand<Record<string, FormDataConvertible>>(() => `/pembelian/${detail.purchase}/retur/${detail.id}/hasil`, 'post');
    const close = useCommand<Record<string, FormDataConvertible>>(() => `/dokumen/kasus/${detail.id}/tutup`, 'post');
    const [kind, setKind] = useState<OutcomeKind>('REPLACEMENT');
    // A refund expected is only a note: there is nothing to correct (WF-PUR-03).
    const [correcting, setCorrecting] = useState(false);
    const correction = correcting && kind !== 'REFUND';
    const [qty, setQty] = useState('');
    const [amount, setAmount] = useState('');
    const [proof, setProof] = useState('');
    const [allocation, setAllocation] = useState('');
    const [reason, setReason] = useState('');
    const [closeReason, setCloseReason] = useState('');
    const [confirming, setConfirming] = useState<'outcome' | 'close' | null>(null);
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const frozen = outcome.pending || outcome.unknown || close.pending || close.unknown;
    const answer = outcome.answer ?? close.answer;
    useClosedOnAnswer(answer, () => { setConfirming(null); });
    const ready = kind === 'REPLACEMENT' ? Number(figure(qty)) > 0 : kind === 'REFUND' ? true : Number(figure(amount)) > 0 && (kind !== 'CREDIT' || correction || proof !== '');
    const open = detail.figures?.open ?? null;

    return (
        <section data-slot="return-case" className="flex min-w-0 flex-col gap-4">
            <div className="flex min-w-0 flex-wrap items-center gap-2">
                <h2 className="text-subhead">{`${detail.product} · ${detail.lot}`}</h2>
                <Badge>{t(detail.state === 'OPEN' ? 'status.correction_case.open' : 'status.correction_case.closed')}</Badge>
            </div>
            <p className="text-body">
                <a className="underline" href={`/pembelian/${detail.purchase}`}><Code>{detail.number}</Code></a>
                {` · ${detail.supplier} · `}
                <DateText value={detail.date} />
            </p>
            <p className="text-body text-text-secondary">{detail.reason}</p>
            {detail.causal !== null && <p className="text-body text-text-secondary">{detail.causal === '' ? t('returns.causal_other') : t('returns.causal', { nomor: detail.causal })}</p>}
            {answer !== null && answer.kind !== 'COMMITTED' && confirming === null && <OutcomeMessage answer={answer} action={outcome.unknown || close.unknown ? <Button variant="outline" size="sm" onClick={outcome.unknown ? outcome.retry : close.retry}>{t('actions.try_again')}</Button> : undefined} />}
            {detail.figures !== null && (
                <dl data-slot="return-figures" className="grid min-w-0 grid-cols-1 gap-x-6 gap-y-2 text-body sm:grid-cols-2">
                    {([['returned', 'cases.counter.returned_cost'], ['refunded', 'cases.counter.refunded_value'], ['replaced', 'cases.counter.replaced_value'], ['unrecovered', 'cases.counter.unrecovered_value']] as const).map(([field, label]) => (
                        <div key={field} className="flex min-w-0 flex-wrap justify-between gap-2"><dt className="text-text-secondary">{t(label)}</dt><dd><Money value={detail.figures?.[field] ?? '0'} /></dd></div>
                    ))}
                </dl>
            )}
            {open !== null && detail.state === 'OPEN' && (Number(open) === 0
                ? <p data-slot="return-balanced" className="text-body">{t('returns.balanced')}</p>
                : <p data-slot="return-open" className="text-body font-medium">{t('returns.open_part', { nilai: money(open) })}</p>)}
            <section data-slot="return-units" className="flex min-w-0 flex-col gap-2">
                <h3 className="text-body font-medium">{t('returns.section.units')}</h3>
                <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                    {detail.units.map((unit, index) => (
                        <li key={`${unit.lot}-${String(index)}`} className="flex min-w-0 flex-wrap items-center gap-2 py-2 text-body">
                            <Code>{unit.lot}</Code>
                            <span>{`${unit.rack} · ${t(`stock.condition.${unit.condition}`)}`}</span>
                            {unit.serial !== null && <span className="font-mono">{unit.serial}</span>}
                            <span>{unit.qty}</span>
                            {unit.cost !== null && <Money value={unit.cost} />}
                        </li>
                    ))}
                </ul>
            </section>
            {detail.can.outcome && (
                <fieldset disabled={frozen} data-slot="return-outcome" className="flex min-w-0 flex-col gap-3">
                    <legend className="text-body font-medium">{t('returns.section.outcome')}</legend>
                    <FormField label={t('returns.field.outcome')} error={outcome.errors.hasil}>
                        {(control) => (
                            <NativeSelect {...control} value={kind} onChange={(event) => { setKind(event.target.value as OutcomeKind); }}>
                                {OUTCOMES.map((one) => <option key={one} value={one}>{t(`returns.outcome.${one}` as TranslationKey)}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                    {kind !== 'REFUND' && (
                        <label data-slot="return-correction" className="flex min-h-floor items-center gap-2 text-body">
                            <Checkbox checked={correcting} onCheckedChange={(checked) => { setCorrecting(checked); }} />
                            <span>{t('returns.field.correction')}</span>
                        </label>
                    )}
                    {correction && <p className="text-body text-text-secondary">{t('returns.correction_help')}</p>}
                    {kind === 'REPLACEMENT' && (
                        <FormField label={t('returns.field.replacement_qty')} help={correction ? undefined : t('returns.replacement_room', { sisa: detail.replacement.room })} error={outcome.errors.jumlah} className="w-48">
                            {(control) => <DecimalInput {...control} scale={detail.scale} value={qty} onValueChange={(next) => { setQty(next ?? ''); }} />}
                        </FormField>
                    )}
                    {kind !== 'REPLACEMENT' && (
                        <FormField label={t(kind === 'REFUND' ? 'returns.field.expected_refund' : 'returns.field.amount')} error={outcome.errors.nilai} className="w-56">
                            {(control) => <DecimalInput {...control} kind="money" scale={2} value={amount} onValueChange={(next) => { setAmount(next ?? ''); }} />}
                        </FormField>
                    )}
                    {kind === 'CREDIT' && !correction && (
                        <div data-slot="return-proof" className="flex min-w-0 flex-col gap-2">
                            {proofsFailed ? <p role="alert" className="text-body text-danger-fg">{t('receiving.list.group_failed')}</p> : (
                                <FormField label={t('returns.field.proof')} error={outcome.errors.bukti}>
                                    {(control) => (
                                        <NativeSelect {...control} value={proof} onChange={(event) => { setProof(event.target.value); }}>
                                            <option value="">{t('dispatch.substitute.choose')}</option>
                                            {(proofs?.rows ?? []).map((row) => <option key={row.id} value={row.id}>{row.label}</option>)}
                                        </NativeSelect>
                                    )}
                                </FormField>
                            )}
                            {proofs !== null && (proofs.more || proofs.find !== null) && <ChoicesFind disabled={frozen} label={t('dispatch.substitute.find_evidence')} help={t('dispatch.substitute.find_evidence_help')} onFind={(text) => { router.reload({ only: ['proofs'], data: { cari_bukti: text }, preserveUrl: true }); }} />}
                        </div>
                    )}
                    {kind === 'UNRECOVERED' && can.allocate && (
                        <FormField label={t('forms.intercompany_reason')} error={outcome.errors.alasan_alokasi}>
                            {(control) => <Input {...control} value={allocation} maxLength={500} onChange={(event) => { setAllocation(event.target.value); }} />}
                        </FormField>
                    )}
                    <Button className="self-start" disabled={!ready || frozen} onClick={() => { outcome.clear(); setConfirming('outcome'); }}>{t('returns.action.record_outcome')}</Button>
                </fieldset>
            )}
            {detail.can.close && <Button className="self-start" disabled={frozen} onClick={() => { close.clear(); setConfirming('close'); }}>{t('actions.close_case')}</Button>}
            <section data-slot="return-timeline" className="flex min-w-0 flex-col gap-2">
                <h3 className="text-body font-medium">{t('returns.section.timeline')}</h3>
                <ol className="flex min-w-0 flex-col gap-2">
                    {detail.events.map((event, index) => (
                        <li key={`${event.at}-${String(index)}`} className="flex min-w-0 flex-col text-body">
                            <span className="font-medium">{`${t(`activity.action.${event.action}` as TranslationKey)}${event.outcome === null ? '' : ` · ${t(`returns.outcome.${event.outcome}` as TranslationKey)}`}${event.correction ? ` · ${t('returns.correction')}` : ''}`}</span>
                            <span className="text-text-secondary">{`${event.by ?? ''} · ${event.at.slice(0, 16).replace('T', ' ')}`}</span>
                            {event.reason !== null && <span>{event.reason}</span>}
                        </li>
                    ))}
                </ol>
                <CorrectionTrail corrects={detail.trail.corrects} correctedBy={detail.trail.corrected_by} hrefOf={() => null} />
            </section>
            <ConfirmCommand
                open={confirming === 'outcome' && !outcome.unknown}
                onOpenChange={(next) => { setConfirming(next ? 'outcome' : null); }}
                title={t('returns.action.record_outcome')}
                consequence={t('returns.confirm.outcome')}
                confirmLabel={t('returns.action.record_outcome')}
                pending={outcome.pending}
                items={<BusinessDateControl today={today} value={date} onChange={setDate} />}
                reason={{ value: reason, onChange: setReason, error: outcome.errors.alasan ?? null }}
                onConfirm={() => {
                    outcome.submit({
                        hasil: kind,
                        koreksi: correction,
                        jumlah: kind === 'REPLACEMENT' ? qty : null,
                        nilai: kind === 'REPLACEMENT' || amount === '' ? null : amount,
                        bukti: kind === 'CREDIT' && !correction && proof !== '' ? proof : null,
                        alasan_alokasi: kind === 'UNRECOVERED' && allocation.trim() !== '' ? allocation : null,
                        alasan: reason,
                        tanggal: date.date,
                        tanggal_otomatis: !date.chosen,
                    }, () => { setConfirming(null); });
                }}
            />
            <ConfirmCommand
                open={confirming === 'close' && !close.unknown}
                onOpenChange={(next) => { setConfirming(next ? 'close' : null); }}
                title={t('cases.confirm.title', { jenis: t('cases.kind.purchase_return') })}
                consequence={t('cases.confirm.consequence')}
                confirmLabel={t('actions.close_case')}
                pending={close.pending}
                reason={{ value: closeReason, onChange: setCloseReason, error: close.errors.alasan ?? null }}
                onConfirm={() => { close.submit({ lock_version: detail.lock_version, alasan: closeReason, asal: 'retur' }, () => { setConfirming(null); }); }}
            />
        </section>
    );
}

function More({ page }: { page: Page<Row> }) {
    const { t } = useTranslation();

    if (page.cursor_failed) return <Button variant="outline" className="self-start" render={<a href={PAGE} />}>{t('receiving.list.reload')}</Button>;

    return page.more === null ? null : <Button variant="outline" className="self-start" render={<a href={`${PAGE}?setelah=${encodeURIComponent(page.more)}`} />}>{t('actions.load_more')}</Button>;
}

export default function SupplierReturns({ list, case: detail, purchase, lot, units, proofs, today, can }: Props) {
    const { t } = useTranslation();
    const [origin] = useState(() => previousPage() ?? '/gudang/stok');
    const loaded = proofs !== undefined && proofs !== null && !('failed' in proofs) ? proofs : null;

    return (
        <AppShell title={t('pages.pg_028.title')} back={{ href: origin, label: t('shell.back') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_028.title')} />
                <PageAnswer />
                {purchase !== null && can.record && <ReturnForm key={`${purchase.id}-${lot ?? ''}`} purchase={purchase} lot={lot} units={units} today={today} can={can} />}
                {detail !== null && <CaseDetail key={detail.id} detail={detail} proofs={loaded} proofsFailed={proofs !== undefined && proofs !== null && 'failed' in proofs} today={today} can={can} />}
                <section data-slot="return-list" className="flex min-w-0 flex-col gap-2">
                    <h2 className="text-subhead">{t('returns.section.list')}</h2>
                    {list.rows.length === 0 ? <RegionEmpty text={t('returns.none')} /> : (
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {list.rows.map((row) => (
                                <li key={row.id} data-slot="return-row" className="flex min-w-0 flex-wrap items-center gap-2 py-2 text-body">
                                    <a className="font-medium underline" href={`${PAGE}?kasus=${row.id}`}>{`${row.product} · ${row.lot}`}</a>
                                    <span><Code>{row.number}</Code>{` · ${row.supplier}`}</span>
                                    <DateText value={row.date} />
                                    <Badge>{t(row.state === 'OPEN' ? 'status.correction_case.open' : 'status.correction_case.closed')}</Badge>
                                    {row.open !== null && row.state === 'OPEN' && Number(row.open) !== 0 && <span className="text-text-secondary">{t('returns.list.open', { nilai: money(row.open) })}</span>}
                                </li>
                            ))}
                        </ul>
                    )}
                    <More page={list} />
                </section>
            </div>
        </AppShell>
    );
}
