// PG-026 *Stok Opname* (ROUTE-026, ROUTE-191–ROUTE-193, ROUTE-215; NAV-026; SCR-24; J-INV-05; IP-09; IP-15; IP-19; IP-20; TASK-043): counting on the phone with the scan field and a running list of lines, one request and one answer per line; applying and reviewing on the desktop. A stale count says MSG-30 with "Hitung Ulang"; a shortage names its lots or becomes *Selisih menunggu penetapan* without naming or counting the other companies; a surplus is not yet added to stock. The count sheet prints through the browser (INT-217).
import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import { RelationMarker } from '@/components/company';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { type BusinessDate, BusinessDateControl } from '@/components/date-field';
import { DecimalInput, FormField } from '@/components/form';
import { DuplicateWarning, ReservationCut, type DuplicateMatch, type Reservation } from '@/components/notices';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty, RegionLoading } from '@/components/region-state';
import { ScanField, ScanNotice, useScanGuard } from '@/components/scan-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Code } from '@/components/values';
import { ProofSheet } from '@/features/fin/proof-sheet';
import { ChoicesFind } from '@/features/inv/dispatch-line';
import { lookUp } from '@/features/inv/scan-client';
import AppShell from '@/layouts/AppShell';
import * as decimal from '@/lib/decimal';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Condition = 'USABLE' | 'UNUSABLE';
type Place = { lot: string; code: string; location: string; rack: string; condition: Condition; qty: string; other: boolean; oldest: boolean };
type Line = { id: string; rack: string; condition: Condition; qty: string; serial: string | null; code: string | null; unexpected: boolean };
type Scope = { location: string; rack: string; condition: Condition; system: string; counted: string; variance: string };
type Count = {
    id: string;
    state: 'OPEN' | 'APPLIED' | 'STALE' | 'CANCELLED';
    purpose: 'ORDINARY' | 'SERIAL_REGISTRATION';
    started: string;
    product: { id: string; name: string; sku: string | null; unit: string; serialized: boolean; scale: number };
    scopes: Scope[];
    lines: Line[];
    places: Place[];
    across: boolean;
    pending: string;
    changes: { id: string; qty: string }[];
    finding: string;
    losses: { id: string; date: string; code: string; open: string }[];
    missing: { serial: string; code: string }[];
};
type ListRow = { id: string; state: Count['state']; purpose: Count['purpose']; product: string; rack: string | null; condition: Condition | null; started: string; lines: number };
type Proofs = { find: string | null; rows: { id: string; label: string }[]; more: boolean };
type Failed = { failed: true };
type Can = { count: boolean; apply: boolean; reserve: boolean; evidence: boolean; allocate: boolean; register: boolean };
type Props = {
    list: { rows: ListRow[]; more: string | null; state: string | null; cursor_failed: boolean };
    count: Count | null;
    product: { public_id: string; name: string; sku: string | null; serialized: boolean } | null;
    found: { id: string; name: string; sku: string | null }[];
    search: string | null;
    racks: { id: string; code: string; name: string }[];
    proofs?: Proofs | Failed;
    reservations: Reservation[];
    available: string;
    today: string;
    can: Can;
};

const STATES: Count['state'][] = ['OPEN', 'APPLIED', 'STALE', 'CANCELLED'];
const POOL_TYPES = ['WAREHOUSE_RECORD', 'OTHER'];
const figure = (value: string) => (/^\d+(\.\d+)?$/.test(value) ? value : '0');
const placeKey = (place: { lot: string; location: string; condition: string }) => `${place.lot}|${place.location}|${place.condition}`;

function stateLabel(state: Count['state']): TranslationKey {
    return `counts.state.${state}` as TranslationKey;
}

function OpenForm({ product, found, search, racks, canRegister }: { product: Props['product']; found: Props['found']; search: string | null; racks: Props['racks']; canRegister: boolean }) {
    const { t } = useTranslation();
    const [typed, setTyped] = useState(search ?? '');
    const [rack, setRack] = useState('');
    const [condition, setCondition] = useState<Condition>('USABLE');
    const open = useCommand<Record<string, FormDataConvertible>>(() => '/gudang/stok-opname', 'post');
    const frozen = open.pending || open.unknown;

    if (product === null) {
        return (
            <form role="search" data-slot="count-find" className="flex min-w-0 flex-col gap-3" onSubmit={(event) => { event.preventDefault(); router.get('/gudang/stok-opname', typed === '' ? {} : { cari: typed }, { preserveState: true }); }}>
                <FormField label={t('counts.field.product')}>
                    {(control) => <Input {...control} value={typed} maxLength={120} onChange={(event) => { setTyped(event.target.value); }} />}
                </FormField>
                <Button type="submit" variant="outline" className="self-start">{t('dispatch.line.find_action')}</Button>
                {search !== null && found.length === 0 && <RegionEmpty text={t('adjustments.no_product')} />}
                <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                    {found.map((row) => <li key={row.id} className="py-2"><a className="underline" href={`/gudang/stok-opname?produk=${row.id}`}>{row.name}</a></li>)}
                </ul>
            </form>
        );
    }

    return (
        <section data-slot="count-open" className="flex min-w-0 flex-col gap-3">
            <p className="text-subhead">{product.name}</p>
            {open.answer !== null && <OutcomeMessage answer={open.answer} action={open.unknown ? <Button variant="outline" size="sm" onClick={open.retry}>{t('actions.try_again')}</Button> : undefined} />}
            <FormField label={t('counts.field.rack')} error={open.errors.rak}>
                {(control) => (
                    <NativeSelect {...control} value={rack} disabled={frozen} onChange={(event) => { setRack(event.target.value); }}>
                        <option value="">{t('dispatch.substitute.choose')}</option>
                        {racks.map((one) => <option key={one.id} value={one.id}>{`${one.code} · ${one.name}`}</option>)}
                    </NativeSelect>
                )}
            </FormField>
            <FormField label={t('counts.field.condition')} error={open.errors.kondisi}>
                {(control) => (
                    <NativeSelect {...control} value={condition} disabled={frozen} onChange={(event) => { setCondition(event.target.value as Condition); }}>
                        <option value="USABLE">{t('stock.condition.USABLE')}</option>
                        <option value="UNUSABLE">{t('stock.condition.UNUSABLE')}</option>
                    </NativeSelect>
                )}
            </FormField>
            <div className="flex min-w-0 flex-wrap gap-3">
                <Button size="lg" disabled={rack === '' || frozen} pending={open.pending} onClick={() => { open.submit({ produk: product.public_id, rak: rack, kondisi: condition }); }}>{t('actions.start_count')}</Button>
                {/* L-35: every unit on hand gets its serial before the product becomes serialized (`DIR-101`). */}
                {canRegister && !product.serialized && <Button size="lg" variant="outline" disabled={frozen} onClick={() => { open.submit({ produk: product.public_id, registrasi: true }); }}>{t('counts.start_registration')}</Button>}
            </div>
        </section>
    );
}

function LineForm({ count }: { count: Count }) {
    const { t } = useTranslation();
    const scanRef = useRef<HTMLInputElement>(null);
    const guard = useScanGuard({ hasScanField: true });
    const registering = count.purpose === 'SERIAL_REGISTRATION';
    const serialized = count.product.serialized || registering;
    const line = useCommand<Record<string, FormDataConvertible>>(() => `/gudang/stok-opname/${count.id}/baris`, 'post');
    const [place] = useState(registering ? '' : `${count.scopes[0]?.location ?? ''}|${count.scopes[0]?.condition ?? 'USABLE'}`);
    const [lotPlace, setLotPlace] = useState('');
    const [quantity, setQuantity] = useState('');
    const [code, setCode] = useState('');
    const [result, setResult] = useState<string | null>(null);
    const [scanError, setScanError] = useState<string | null>(null);
    const [unknown, setUnknown] = useState<string | null>(null);
    const frozen = line.pending || line.unknown;
    const chosen = count.places.find((one) => placeKey(one) === lotPlace);
    const [location, condition] = registering ? [chosen?.location ?? '', chosen?.condition ?? 'USABLE'] : place.split('|');

    useEffect(() => { scanRef.current?.focus(); }, []);

    const send = (body: Record<string, FormDataConvertible>) => {
        line.submit({ rak: location, kondisi: condition, ...body }, () => { setQuantity(''); setCode(''); setUnknown(null); });
    };
    const scanned = (typed: string) => {
        setResult(null);
        setScanError(null);
        setUnknown(null);

        if (registering) {
            send({ seri: typed, lot: chosen?.lot ?? null });

            return;
        }

        void lookUp(typed).then((scan) => {
            const match = scan?.matches[0];

            if (scan === null) {
                setScanError(t('messages.msg_06'));
            } else if (scan.kind === 'ambiguous') {
                setScanError(t('messages.msg_37', { kode: scan.code }));
            } else if (serialized && (scan.kind === 'unknown' || match?.kind !== 'serial' || match.product !== count.product.id)) {
                // Never selected on its own; recording what was seen is the actor's explicit step (L-48).
                setScanError(t('messages.msg_25', { kode: scan.code }));
                setUnknown(scan.code);
            } else if (serialized) {
                send({ seri: match?.serial ?? typed });
            } else if (scan.kind === 'unknown' || match === undefined || match.product !== count.product.id) {
                setScanError(t('messages.msg_25', { kode: scan.code }));
            } else {
                setResult(t('adjustments.scan.same_product'));
            }
        });
    };

    return (
        <section data-slot="count-line" className="flex min-w-0 flex-col gap-3 pb-18 md:pb-0 print:hidden">
            <ScanNotice notice={guard.notice} />
            {line.answer !== null && <OutcomeMessage answer={line.answer} action={line.unknown ? <Button variant="outline" size="sm" onClick={line.retry}>{t('actions.try_again')}</Button> : undefined} />}
            {registering && (
                <FormField label={t('counts.field.place')} error={line.errors.lot}>
                    {(control) => (
                        <NativeSelect {...control} value={lotPlace} disabled={frozen} onChange={(event) => { setLotPlace(event.target.value); }}>
                            <option value="">{t('dispatch.substitute.choose')}</option>
                            {count.places.map((one) => <option key={placeKey(one)} value={placeKey(one)}>{`${one.code} · ${one.rack} · ${t(`stock.condition.${one.condition}`)} · ${one.qty}`}</option>)}
                        </NativeSelect>
                    )}
                </FormField>
            )}
            {!serialized && (
                <div className="flex min-w-0 flex-wrap items-end gap-3">
                    <FormField label={t('counts.field.counted')} error={line.errors.jumlah} className="w-48">
                        {(control) => <DecimalInput {...control} scale={count.product.scale} disabled={frozen} value={quantity} onValueChange={(next) => { setQuantity(next ?? ''); }} />}
                    </FormField>
                    <Button size="lg" disabled={quantity === '' || frozen} pending={line.pending} onClick={() => { send({ jumlah: quantity }); }}>{t('counts.record_line')}</Button>
                </div>
            )}
            {unknown !== null && <Button variant="outline" size="sm" className="self-start" disabled={frozen} onClick={() => { send({ seri: unknown }); }}>{t('counts.record_unexpected', { seri: unknown })}</Button>}
            <ScanField dock inputRef={scanRef} value={code} onValueChange={setCode} onLookup={scanned} result={result} error={scanError ?? line.errors.seri ?? null} />
        </section>
    );
}

function Apply({ count, proofs, proofsFailed, reservations, available, today, can }: { count: Count; proofs: Proofs | null; proofsFailed: boolean; reservations: Reservation[]; available: string; today: string; can: Can }) {
    const { t } = useTranslation();
    const command = useCommand<Record<string, FormDataConvertible>>(() => `/gudang/stok-opname/${count.id}/terapkan`, 'post');
    const frozen = command.pending || command.unknown;
    const [quantities, setQuantities] = useState<Record<string, string>>({});
    const [confirmed, setConfirmed] = useState<string[]>([]);
    const [unidentified, setUnidentified] = useState(false);
    const [pending, setPending] = useState('');
    const [conversions, setConversions] = useState<Record<string, string>>({});
    const [restored, setRestored] = useState<Record<string, string>>({});
    const [cuts, setCuts] = useState<Record<string, string>>({});
    const [evidence, setEvidence] = useState('');
    const [uploaded, setUploaded] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);
    const [allocation, setAllocation] = useState('');
    const [reason, setReason] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [confirming, setConfirming] = useState(false);
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [duplicateReason, setDuplicateReason] = useState('');
    const registering = count.purpose === 'SERIAL_REGISTRATION';
    const scope = count.scopes[0];
    const variance = registering || scope === undefined ? '0' : scope.variance;
    // The basis carries an open finding, and a shortage takes it back first (WF-INV-05).
    const raw = Number(variance) < 0 ? variance.replace('-', '') : '0';
    const short = Number(raw) > Number(count.finding) ? decimal.add(raw, `-${count.finding}`) : '0';
    const picks = count.places.filter((place) => Number(figure(quantities[placeKey(place)] ?? '')) > 0);
    const named = decimal.add('0', ...picks.map((place) => figure(quantities[placeKey(place)] ?? '')));
    const converted = decimal.add('0', ...Object.values(conversions).map(figure));
    const covered = decimal.add(named, figure(pending), converted);
    const serialized = count.product.serialized;
    const balanced = serialized || Number(short) === 0 || Number(covered) === Number(short);
    const usable = scope?.condition !== 'USABLE' ? '0' : serialized ? String(count.missing.length) : decimal.add(named, figure(pending));
    const need = decimal.add(usable, `-${available}`);
    const needsCut = Number(need) > 0;
    const cut = decimal.add('0', ...Object.values(cuts).map(figure));
    const unconfirmed = unidentified && picks.some((place) => !confirmed.includes(placeKey(place)));
    const matches = Array.isArray(command.state.matches) ? (command.state.matches as DuplicateMatch[]) : [];
    const warned = command.answer?.kind === 'REJECTED' && matches.length > 0;
    // Named from evidence, or confirmed by the actor where its lots are one company's (BR-INV-04; L-42).
    const needsEvidence = !serialized && picks.length > 0 && !unidentified;
    const ready = count.lines.length > 0 && balanced && !unconfirmed && (!needsEvidence || evidence !== '') && (!needsCut || Number(cut) >= Number(need)) && (!warned || (matches.every((match) => acknowledged.includes(match.id)) && duplicateReason.trim() !== ''));
    const [seenAnswer, setSeenAnswer] = useState(command.answer);

    if (seenAnswer !== command.answer) {
        setSeenAnswer(command.answer);

        if (command.answer !== null) setConfirming(false);
    }

    const send = () => {
        command.submit({
            unit: picks.map((place) => ({ lot: place.lot, jumlah: quantities[placeKey(place)] ?? '0', dikonfirmasi: confirmed.includes(placeKey(place)) })),
            tanpa_identitas: unidentified,
            tertunda: pending === '' ? null : pending,
            konversi: Object.entries(conversions).filter(([, qty]) => Number(figure(qty)) > 0).map(([kasus, jumlah]) => ({ kasus, jumlah })),
            pulihkan: Object.entries(restored).filter(([, qty]) => Number(figure(qty)) > 0).map(([konsumsi, jumlah]) => ({ konsumsi, jumlah })),
            potong: needsCut ? Object.entries(cuts).filter(([, qty]) => Number(figure(qty)) > 0).map(([reservasi, jumlah]) => ({ reservasi, jumlah })) : [],
            bukti: evidence === '' ? [] : [evidence],
            alasan: reason,
            alasan_alokasi: allocation === '' ? null : allocation,
            duplikat: warned ? acknowledged : [],
            alasan_duplikat: warned ? duplicateReason : null,
            kebijakan_seri: serialized,
            kebijakan_skala: count.product.scale,
            tanggal: date.date,
            tanggal_otomatis: !date.chosen,
        });
    };
    const unplaced = Object.entries(command.errors).filter(([field]) => !['alasan', 'alasan_duplikat'].includes(field)).slice(0, 1);

    return (
        <section data-slot="count-apply" className="flex min-w-0 flex-col gap-4 print:hidden">
            <h2 className="text-subhead">{t('actions.apply_variance')}</h2>
            {command.answer !== null && !warned && !confirming && <OutcomeMessage answer={command.answer} action={command.unknown ? <Button variant="outline" size="sm" onClick={command.retry}>{t('actions.try_again')}</Button> : undefined} />}
            {warned && <DuplicateWarning matches={matches} message={command.answer?.text} acknowledged={acknowledged} onAcknowledge={setAcknowledged} canContinue reason={{ value: duplicateReason, onChange: setDuplicateReason, error: command.errors.alasan_duplikat ?? null }} />}
            <BusinessDateControl today={today} value={date} onChange={setDate} />
            {registering && <p className="text-body text-text-secondary">{t('counts.registration_note')}</p>}
            {Number(variance) > 0 && <p data-slot="count-surplus-note" className="text-body text-text-secondary">{t('counts.surplus_note')}</p>}
            {Number(count.pending) > 0 && Number(variance) > 0 && <p className="text-body text-text-secondary">{t('counts.found_note', { jumlah: count.pending, satuan: count.product.unit })}</p>}
            {Number(variance) > 0 && count.losses.length > 0 && (
                <fieldset disabled={frozen} data-slot="count-losses" className="flex min-w-0 flex-col gap-2">
                    <legend className="text-body font-medium">{t('counts.identified_losses')}</legend>
                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {count.losses.map((loss) => (
                            <li key={loss.id} className="flex min-w-0 flex-wrap items-end gap-3 py-2 text-body">
                                <span className="flex min-w-0 flex-1 flex-wrap items-center gap-2"><Code>{loss.code}</Code>{`${loss.date} · ${loss.open}`}</span>
                                <FormField label={t('counts.field.restore', { kode: loss.code })} className="w-40">
                                    {(control) => <DecimalInput {...control} scale={count.product.scale} value={restored[loss.id] ?? ''} onValueChange={(next) => { setRestored({ ...restored, [loss.id]: next ?? '' }); }} />}
                                </FormField>
                            </li>
                        ))}
                    </ul>
                </fieldset>
            )}
            {Number(short) > 0 && !serialized && (
                <fieldset disabled={frozen} data-slot="count-split" className="flex min-w-0 flex-col gap-2">
                    <legend className="text-body font-medium">{t('counts.split', { jumlah: short, satuan: count.product.unit })}</legend>
                    {count.across
                        ? <p data-slot="count-across" className="text-body text-text-secondary">{t('counts.across_note')}</p>
                        : (
                            <label className="flex min-h-floor items-center gap-2 text-body">
                                <Checkbox checked={unidentified} disabled={frozen} onCheckedChange={setUnidentified} />
                                {t('adjustments.unidentified')}
                            </label>
                        )}
                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {count.places.map((place) => {
                            const key = placeKey(place);

                            return (
                                <li key={key} data-slot="count-lot" className="flex min-w-0 flex-wrap items-end gap-3 py-2 text-body">
                                    <span className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                        <Code>{place.code}</Code>
                                        <span>{place.qty}</span>
                                        {place.oldest && <Badge>{t('stock.lot.oldest')}</Badge>}
                                        {place.other && <RelationMarker />}
                                    </span>
                                    <FormField label={t('adjustments.field.quantity', { kode: place.code, rak: place.rack })} className="w-40">
                                        {(control) => <DecimalInput {...control} scale={count.product.scale} value={quantities[key] ?? ''} onValueChange={(next) => { setQuantities({ ...quantities, [key]: next ?? '' }); }} />}
                                    </FormField>
                                    {unidentified && Number(figure(quantities[key] ?? '')) > 0 && (
                                        <label className="flex min-h-floor items-center gap-2">
                                            <Checkbox checked={confirmed.includes(key)} onCheckedChange={(checked) => { setConfirmed(checked ? [...confirmed, key] : confirmed.filter((other) => other !== key)); }} />
                                            {t('adjustments.confirm_lot', { kode: place.code })}
                                        </label>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                    {count.across && (
                        <FormField label={t('glossary.gl_089')} help={t('counts.pending_help')} className="w-64" error={command.errors.tertunda}>
                            {(control) => <DecimalInput {...control} scale={count.product.scale} value={pending} onValueChange={(next) => { setPending(next ?? ''); }} />}
                        </FormField>
                    )}
                    {count.changes.map((change) => (
                        <FormField key={change.id} label={t('counts.field.conversion', { jumlah: change.qty })} className="w-64">
                            {(control) => <DecimalInput {...control} scale={count.product.scale} value={conversions[change.id] ?? ''} onValueChange={(next) => { setConversions({ ...conversions, [change.id]: next ?? '' }); }} />}
                        </FormField>
                    ))}
                    {!balanced && <p role="status" className="text-body text-text-secondary">{t('counts.split_left', { jumlah: decimal.add(short, `-${covered}`) })}</p>}
                </fieldset>
            )}
            {serialized && count.missing.length > 0 && (
                <div data-slot="count-missing" className="flex min-w-0 flex-col gap-1 text-body">
                    <p className="font-medium">{t('counts.missing')}</p>
                    <ul role="list" className="flex min-w-0 flex-col">{count.missing.map((one) => <li key={one.serial}><span className="font-mono">{one.serial}</span>{' · '}<Code>{one.code}</Code></li>)}</ul>
                </div>
            )}
            {needsCut && (can.reserve
                ? <ReservationCut shortfall={t('stock.reverse.shortfall', { kurang: need })} unit={count.product.unit} reservations={reservations} cuts={cuts} scale={count.product.scale} onCut={(id, value) => { setCuts({ ...cuts, [id]: value ?? '' }); }} />
                : <p role="alert" className="text-body font-medium text-danger-fg">{t('receiving.refusal.reservations')}</p>)}
            {Number(short) > 0 && can.allocate && picks.length > 0 && (
                <FormField label={t('forms.intercompany_reason')} error={command.errors.alasan_alokasi}>
                    {(control) => <Input {...control} value={allocation} maxLength={500} disabled={frozen} onChange={(event) => { setAllocation(event.target.value); }} />}
                </FormField>
            )}
            {!registering && (
                <div data-slot="count-evidence" className="flex min-w-0 flex-col gap-2">
                    {proofsFailed ? (
                        <p role="alert" className="flex min-w-0 flex-wrap items-center gap-3 text-body text-danger-fg">
                            {t('receiving.list.group_failed')}
                            <Button variant="outline" size="sm" onClick={() => { router.reload({ only: ['proofs'] }); }}>{t('receiving.list.reload')}</Button>
                        </p>
                    ) : proofs === null ? <RegionLoading /> : (
                        <FormField label={t('adjustments.field.proof')} error={command.errors.bukti}>
                            {(control) => (
                                <NativeSelect {...control} value={evidence} disabled={frozen} onChange={(event) => { setEvidence(event.target.value); }}>
                                    <option value="">{t('dispatch.substitute.choose')}</option>
                                    {uploaded !== null && <option value={uploaded}>{t('dispatch.substitute.evidence_uploaded')}</option>}
                                    {evidence !== '' && evidence !== uploaded && !proofs.rows.some((row) => row.id === evidence) && <option value={evidence}>{t('dispatch.substitute.evidence_uploaded')}</option>}
                                    {proofs.rows.filter((row) => row.id !== uploaded).map((row) => <option key={row.id} value={row.id}>{row.label}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}
                    {proofs !== null && proofs.find !== null && proofs.rows.length === 0 && <p role="status" className="text-body text-text-secondary">{t('adjustments.no_proof')}</p>}
                    {proofs !== null && proofs.find !== null && <Button variant="ghost" size="sm" className="self-start" disabled={frozen} onClick={() => { router.reload({ only: ['proofs'], preserveUrl: true }); }}>{t('adjustments.all_proofs')}</Button>}
                    {proofs !== null && (proofs.more || proofs.find !== null) && <ChoicesFind disabled={frozen} label={t('dispatch.substitute.find_evidence')} help={t('dispatch.substitute.find_evidence_help')} onFind={(text) => { router.reload({ only: ['proofs'], data: { cari_bukti: text }, preserveUrl: true }); }} />}
                    {can.evidence && <Button variant="outline" className="self-start" disabled={frozen} onClick={() => { setUploading(true); }}>{t('dispatch.substitute.evidence')}</Button>}
                </div>
            )}
            {unplaced.map(([field, message]) => <p key={field} role="alert" className="text-body font-medium text-danger-fg">{message}</p>)}
            <Button size="lg" className="self-start" disabled={!ready || frozen} onClick={() => { if (!warned) command.clear(); setConfirming(true); }}>{t('actions.apply_variance')}</Button>
            <ConfirmCommand
                open={confirming && !command.unknown}
                onOpenChange={setConfirming}
                title={t('actions.apply_variance')}
                consequence={t(registering ? 'counts.confirm.registration' : 'counts.confirm.apply')}
                confirmLabel={t('actions.apply_variance')}
                destructive
                pending={command.pending}
                reason={{ value: reason, onChange: setReason, error: command.errors.alasan ?? null }}
                onConfirm={send}
            />
            {can.evidence && <ProofSheet open={uploading} onOpenChange={setUploading} company={null} pool types={POOL_TYPES} today={today} back={`${window.location.pathname}${window.location.search}`} onRegistered={(id) => { setUploaded(id); setEvidence(id); setUploading(false); }} />}
        </section>
    );
}

function Detail({ count, proofs, proofsFailed, reservations, available, today, can }: { count: Count; proofs: Proofs | null; proofsFailed: boolean; reservations: Reservation[]; available: string; today: string; can: Can }) {
    const { t } = useTranslation();
    const recount = useCommand<Record<string, FormDataConvertible>>(() => '/gudang/stok-opname', 'post');
    const cancel = useCommand<Record<string, FormDataConvertible>>(() => `/gudang/stok-opname/${count.id}/batalkan`, 'post');
    const [cancelling, setCancelling] = useState(false);
    const [reason, setReason] = useState('');
    const scope = count.scopes[0];
    const open = count.state === 'OPEN';

    return (
        <div data-slot="count-detail" className="flex min-w-0 flex-col gap-5">
            <div className="flex min-w-0 flex-wrap items-center gap-2">
                <p className="text-subhead">{count.product.name}</p>
                <Badge>{t(stateLabel(count.state))}</Badge>
                {count.purpose === 'SERIAL_REGISTRATION' && <Badge>{t('counts.registration')}</Badge>}
            </div>
            {count.state === 'STALE' && scope !== undefined && (
                <div data-slot="count-stale" className="flex min-w-0 flex-col gap-2 print:hidden">
                    <p role="status" className="text-body font-medium">{t('messages.msg_30')}</p>
                    {recount.answer !== null && <OutcomeMessage answer={recount.answer} />}
                    {can.count && <Button size="lg" className="self-start" disabled={recount.pending} pending={recount.pending} onClick={() => { recount.submit(count.purpose === 'SERIAL_REGISTRATION' ? { produk: count.product.id, registrasi: true } : { produk: count.product.id, rak: scope.location, kondisi: scope.condition }); }}>{t('actions.recount')}</Button>}
                </div>
            )}
            <section data-slot="count-scopes" className="flex min-w-0 flex-col gap-2">
                <h2 className="text-subhead">{t('counts.section.scope')}</h2>
                <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                    {count.scopes.map((one) => (
                        <li key={`${one.location}|${one.condition}`} className="flex min-w-0 flex-wrap gap-x-4 gap-y-1 py-2 text-body">
                            <span>{`${one.rack} · ${t(`stock.condition.${one.condition}`)}`}</span>
                            <span>{t('counts.figure.system', { jumlah: one.system })}</span>
                            <span>{t('counts.figure.counted', { jumlah: one.counted })}</span>
                            <span data-slot="count-variance" className="print:hidden">{t('counts.figure.variance', { jumlah: one.variance })}</span>
                            {/* The sheet's blank column to write on (DESIGN_SYSTEM §17). */}
                            <span className="hidden print:inline">{t('counts.print.write')}</span>
                        </li>
                    ))}
                </ul>
                {Number(count.pending) > 0 && <p data-slot="count-pending" className="text-body text-text-secondary">{t('messages.msg_41', { jumlah: count.pending, satuan: count.product.unit })}</p>}
                {Number(count.finding) > 0 && <p data-slot="count-finding" className="text-body text-text-secondary">{t('counts.open_finding', { jumlah: count.finding, satuan: count.product.unit })}</p>}
            </section>
            {open && can.count && <LineForm count={count} />}
            <section data-slot="count-lines" className="flex min-w-0 flex-col gap-2">
                <h2 className="text-subhead">{t('counts.section.lines')}</h2>
                {count.lines.length === 0 ? <RegionEmpty text={t('counts.no_lines')} /> : (
                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {count.lines.map((line) => (
                            <li key={line.id} data-slot="count-line-row" className="flex min-w-0 flex-wrap items-center gap-2 py-2 text-body">
                                <span>{`${line.rack} · ${t(`stock.condition.${line.condition}`)}`}</span>
                                {line.serial !== null ? <span className="font-mono">{line.serial}</span> : <span>{line.qty}</span>}
                                {line.code !== null && <Code>{line.code}</Code>}
                                {line.unexpected && <Badge>{t('counts.unexpected')}</Badge>}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
            {open && can.apply && (count.purpose === 'ORDINARY' || can.register) && <Apply count={count} proofs={proofs} proofsFailed={proofsFailed} reservations={reservations} available={available} today={today} can={can} />}
            <div className="flex min-w-0 flex-wrap gap-3 print:hidden">
                {can.count && <Button variant="outline" onClick={() => { window.print(); }}>{t('actions.print_count_sheet')}</Button>}
                {open && can.count && <Button variant="ghost" onClick={() => { setCancelling(true); }}>{t('actions.cancel_count')}</Button>}
            </div>
            {cancel.answer !== null && <OutcomeMessage answer={cancel.answer} />}
            <ConfirmCommand
                open={cancelling}
                onOpenChange={setCancelling}
                title={t('actions.cancel_count')}
                consequence={t('counts.confirm.cancel')}
                confirmLabel={t('actions.cancel_count')}
                destructive
                pending={cancel.pending}
                reason={{ value: reason, onChange: setReason, error: cancel.errors.alasan ?? null }}
                onConfirm={() => { cancel.submit({ alasan: reason }, () => { setCancelling(false); }); }}
            />
        </div>
    );
}

export default function StockCounts({ list, count, product, found, search, racks, proofs, reservations, available, today, can }: Props) {
    const { t } = useTranslation();
    const loaded = proofs !== undefined && !('failed' in proofs) ? proofs : null;

    return (
        <AppShell title={t('pages.pg_026.title')} back={count === null ? undefined : { href: '/gudang/stok-opname', label: t('pages.pg_026.title') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_026.title')} />
                {count !== null ? (
                    <Detail count={count} proofs={loaded} proofsFailed={proofs !== undefined && 'failed' in proofs} reservations={reservations} available={available} today={today} can={can} />
                ) : (
                    <>
                        {can.count && <OpenForm product={product} found={found} search={search} racks={racks} canRegister={can.register} />}
                        <section data-slot="count-list" className="flex min-w-0 flex-col gap-3">
                            <div role="group" aria-label={t('counts.field.state')} className="flex min-w-0 flex-wrap gap-2">
                                <Button size="sm" variant={list.state === null ? 'primary' : 'outline'} aria-pressed={list.state === null} onClick={() => { router.get('/gudang/stok-opname'); }}>{t('counts.all')}</Button>
                                {STATES.map((one) => <Button key={one} size="sm" variant={list.state === one ? 'primary' : 'outline'} aria-pressed={list.state === one} onClick={() => { router.get('/gudang/stok-opname', { status: one }); }}>{t(stateLabel(one))}</Button>)}
                            </div>
                            {list.rows.length === 0 ? <RegionEmpty text={t('counts.none')} /> : (
                                <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                    {list.rows.map((row) => (
                                        <li key={row.id} data-slot="count-row" className="flex min-w-0 flex-wrap items-center gap-2 py-2 text-body">
                                            <a className="underline" href={`/gudang/stok-opname?hitungan=${row.id}`}>{row.product}</a>
                                            {row.rack !== null && <span>{row.rack}</span>}
                                            {row.condition !== null && <span>{t(`stock.condition.${row.condition}`)}</span>}
                                            <span className="text-text-secondary">{row.started}</span>
                                            <Badge>{t(stateLabel(row.state))}</Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {list.cursor_failed && <Button variant="outline" className="self-start" render={<a href="/gudang/stok-opname" />}>{t('receiving.list.reload')}</Button>}
                            {list.more !== null && <Button variant="outline" className="self-start" render={<a href={`/gudang/stok-opname?${list.state === null ? '' : `status=${list.state}&`}setelah=${encodeURIComponent(list.more)}`} />}>{t('actions.load_more')}</Button>}
                        </section>
                    </>
                )}
            </div>
        </AppShell>
    );
}
