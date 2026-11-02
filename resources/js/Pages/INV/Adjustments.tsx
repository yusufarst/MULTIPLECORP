// PG-027 *Penyesuaian & Kondisi* (ROUTE-027, ROUTE-194–ROUTE-196; NAV-027; SCR-25; J-INV-04; J-INV-06; IP-05; IP-09; IP-16; IP-20; TASK-042): one form per command — "Ubah Kondisi", "Periksa Ulang", "Catat Penyesuaian Stok" — for a product, chosen here or from PG-019: the units named lot by lot or serial by serial, oldest first only as a picking aid (BR-INV-04), unidentified lots confirmed one by one (L-42), the cut where usable supply would fall below the reservations, the proof, the reason. An adjustment only decreases (L-15), and the form says where its loss is recorded. A loss's amount only with `cost.view` of its bearer (J-INV-06); its exact reversal from its record (CM-34). Phone first: scan the unit, state the condition, photograph.
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
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateText, Money } from '@/components/values';
import { ProofSheet } from '@/features/fin/proof-sheet';
import { ChoicesFind } from '@/features/inv/dispatch-line';
import { lookUp } from '@/features/inv/scan-client';
import AppShell from '@/layouts/AppShell';
import * as decimal from '@/lib/decimal';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { previousPage } from '@/lib/previous-page';
import { isBackground } from '@/lib/session-core';
import { useCommand } from '@/lib/use-command';

type Condition = 'USABLE' | 'UNUSABLE';
type Action = 'kondisi' | 'periksa' | 'penyesuaian';
type Product = { public_id: string; name: string; sku: string | null; serialized: boolean; scale: number; unit: string };
type Place = { lot: string; code: string; location: string; rack: string; condition: Condition; qty: string; other: boolean; oldest: boolean };
type Serial = { handle: string; serial: string; lot: string; location: string; condition: Condition; code: string; rack: string; other: boolean };
type Units = { find: string | null; places: Place[]; serials: Serial[]; more: boolean; available: string; reservations: Reservation[]; scopes: { location: string; rack: string; condition: Condition; qty: string }[]; changes: { id: string; rack: string; qty: string }[] };
type Case = { id: string; kind: string; date: string; lot: string | null; project: string | null; cross: boolean };
type Cases = { find: string | null; rows: Case[]; more: boolean };
type Proofs = { find: string | null; rows: { id: string; label: string }[]; more: boolean };
type Loss = { id: string; type: string; date: string; qty: string; open: string; lot: string; amounts: { company: string; amount: string }[] };
type Failed = { failed: true };
type Can = { condition: boolean; adjust: boolean; move: boolean; reverse: boolean; reserve: boolean; evidence: boolean; allocate: boolean };
type Props = {
    product: Product | null;
    action: Action | null;
    lot: string | null;
    search: string | null;
    found: { id: string; name: string; sku: string | null }[];
    units?: Units | null;
    cases?: Cases | Failed;
    proofs?: Proofs | Failed;
    losses?: Loss[] | Failed;
    today: string;
    can: Can;
};

const placeKey = (place: { lot: string; location: string; condition: string }) => `${place.lot}|${place.location}|${place.condition}`;
const figure = (value: string) => (/^\d+(\.\d+)?$/.test(value) ? value : '0');
const latest = <Row,>(rows: Row[], key: (row: Row) => string): Row[] => [...new Map(rows.map((row) => [key(row), row])).values()];
const POOL_TYPES = ['WAREHOUSE_RECORD', 'OTHER'];

function LossRecord({ loss, today, canReverse }: { loss: Loss; today: string; canReverse: boolean }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const reverse = useCommand<Record<string, FormDataConvertible>>(() => `/gudang/penyesuaian/${loss.id}/balikkan`, 'post');

    return (
        <li data-slot="loss-record" className="flex min-w-0 flex-wrap items-center gap-2 py-2 text-body">
            <DateText value={loss.date} />
            <span>{`${t(`stock.movement.${loss.type}` as TranslationKey)} · ${loss.qty} · `}<Code>{loss.lot}</Code></span>
            {loss.amounts.map((one) => <span key={one.company} className="inline-flex items-center gap-1">{loss.amounts.length > 1 && <Code>{one.company}</Code>}<Money value={one.amount} /></span>)}
            {reverse.answer?.kind === 'COMMITTED' && <OutcomeMessage answer={reverse.answer} />}
            {canReverse && loss.open !== '0' && <Button variant="ghost" size="sm" onClick={() => { reverse.clear(); setOpen(true); }}>{t('adjustments.reverse.action')}</Button>}
            <ConfirmCommand
                open={open}
                onOpenChange={setOpen}
                title={t('adjustments.reverse.action')}
                consequence={t('adjustments.reverse.consequence')}
                confirmLabel={t('adjustments.reverse.action')}
                destructive
                pending={reverse.pending}
                answer={reverse.answer}
                reason={{ value: reason, onChange: setReason, error: reverse.errors.alasan ?? null }}
                onConfirm={() => { reverse.submit({ alasan: reason, tanggal: today, tanggal_otomatis: true }, () => { setOpen(false); }); }}
            />
        </li>
    );
}

function Form({ product, action, lot, units, cases, proofs, proofsFailed, today, can, origin, onLeaveGuard }: { product: Product; action: Action; lot: string | null; units: Units; cases: Cases | null; proofs: Proofs | null; proofsFailed: boolean; today: string; can: Can; origin: string; onLeaveGuard: (dirty: boolean) => void }) {
    const { t } = useTranslation();
    const from: Condition | null = action === 'kondisi' ? 'USABLE' : action === 'periksa' ? 'UNUSABLE' : null;
    const [quantities, setQuantities] = useState<Record<string, string>>({});
    const [chosen, setChosen] = useState<string[]>([]);
    const [unidentified, setUnidentified] = useState(false);
    const [confirmed, setConfirmed] = useState<string[]>([]);
    const [pendingQty, setPendingQty] = useState<Record<string, string>>({});
    const [convertQty, setConvertQty] = useState<Record<string, string>>({});
    const [kind, setKind] = useState('DISPOSAL');
    const [caseId, setCaseId] = useState('');
    const [allocation, setAllocation] = useState('');
    const [evidence, setEvidence] = useState<string[]>([]);
    const [uploaded, setUploaded] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);
    const [cuts, setCuts] = useState<Record<string, string>>({});
    const [reason, setReason] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [confirming, setConfirming] = useState(false);
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [duplicateReason, setDuplicateReason] = useState('');
    const [sentAs, setSentAs] = useState('');
    const [code, setCode] = useState('');
    const [result, setResult] = useState<string | null>(null);
    const [scanError, setScanError] = useState<string | null>(null);
    const scanRef = useRef<HTMLInputElement>(null);
    const chosenNow = useRef<string[]>([]);
    const command = useCommand<Record<string, FormDataConvertible>>(() => (action === 'penyesuaian' ? '/gudang/penyesuaian' : '/gudang/kondisi'), 'post');
    const frozen = command.pending || command.unknown;
    const guard = useScanGuard({ hasScanField: true });
    const [onlyLot, setOnlyLot] = useState(lot);
    // What is disposed of is unusable (WF-INV-06).
    const only: Condition | null = action === 'penyesuaian' && kind === 'DISPOSAL' ? 'UNUSABLE' : from;
    // What a search brought stays while it is entered: a narrower search hides no unit already picked.
    const [seenUnits, setSeenUnits] = useState(units);
    const [knownPlaces, setKnownPlaces] = useState(units.places);
    const [knownSerials, setKnownSerials] = useState(units.serials);

    if (units !== seenUnits) {
        setSeenUnits(units);
        setKnownPlaces((all) => [...all, ...units.places]);
        setKnownSerials((all) => [...all, ...units.serials]);
    }

    // The proof chosen stays named while a search narrows the list.
    const [seenProofs, setSeenProofs] = useState(proofs);
    const [knownProofs, setKnownProofs] = useState(proofs?.rows ?? []);

    if (proofs !== seenProofs) {
        setSeenProofs(proofs);
        setKnownProofs((all) => [...all, ...(proofs?.rows ?? [])]);
    }

    // So does the case chosen.
    const [seenCases, setSeenCases] = useState(cases);
    const [knownCases, setKnownCases] = useState(cases?.rows ?? []);

    if (cases !== seenCases) {
        setSeenCases(cases);
        setKnownCases((all) => [...all, ...(cases?.rows ?? [])]);
    }

    const caseRows = latest([...knownCases.filter((one) => one.id === caseId), ...(cases?.rows ?? [])], (one) => one.id);
    const offered = (row: { condition: Condition; lot: string }) => (only === null || row.condition === only) && (onlyLot === null || row.lot === onlyLot);
    const places = latest([...knownPlaces.filter((place) => Number(figure(quantities[placeKey(place)] ?? '')) > 0), ...units.places], placeKey).filter(offered);
    const serials = latest([...knownSerials.filter((serial) => chosen.includes(serial.handle)), ...units.serials], (serial) => serial.handle).filter(offered);
    const picks = product.serialized
        ? serials.filter((serial) => chosen.includes(serial.handle)).map((serial) => ({ key: serial.handle, lot: serial.lot, location: serial.location, condition: serial.condition, qty: '1', serial: serial.handle }))
        : places.filter((place) => Number(figure(quantities[placeKey(place)] ?? '')) > 0).map((place) => ({ key: placeKey(place), lot: place.lot, location: place.location, condition: place.condition, qty: quantities[placeKey(place)] ?? '0', serial: null as string | null }));
    // Across companies with no evidence: a pending quantity, without naming or counting them (SF-UNATTRIBUTED; PJ-07).
    const pendingScopes = unidentified && !product.serialized && action !== 'periksa' ? units.scopes.filter((scope) => only === null || scope.condition === only) : [];
    const pendingRows = pendingScopes.filter((scope) => Number(figure(pendingQty[`${scope.location}|${scope.condition}`] ?? '')) > 0);
    const conversions = action === 'penyesuaian' && kind === 'DISPOSAL' ? units.changes : [];
    const converted = conversions.filter((change) => Number(figure(convertQty[change.id] ?? '')) > 0);
    const usable = decimal.add('0', ...picks.filter((pick) => pick.condition === 'USABLE').map((pick) => figure(pick.qty)), ...pendingRows.filter((scope) => scope.condition === 'USABLE').map((scope) => figure(pendingQty[`${scope.location}|${scope.condition}`] ?? '')));
    const short = action === 'periksa' ? '0' : decimal.add(usable, `-${units.available}`);
    const needsCut = Number(short) > 0;
    const covered = decimal.add('0', ...Object.values(cuts).map(figure));
    const needsEvidence = action !== 'periksa';
    const chosenCase = caseRows.find((one) => one.id === caseId);
    const unconfirmed = unidentified && picks.some((pick) => !confirmed.includes(pick.key));
    const blockedByAllocation = chosenCase?.cross === true && allocation.trim() === '';
    const signature = JSON.stringify([picks, date.date, kind, caseId, cuts]);
    const matches = Array.isArray(command.state.matches) ? (command.state.matches as DuplicateMatch[]) : [];
    const matched = command.answer?.kind === 'REJECTED' && matches.length > 0;
    const warned = matched && sentAs === signature;
    // A pending quantity names no lot and no case: what does goes as its own command.
    const mixed = (pendingRows.length > 0 || converted.length > 0) && (picks.length > 0 || caseId !== '');
    const dirty = picks.length > 0 || pendingRows.length > 0 || converted.length > 0 || reason !== '' || evidence.length > 0;
    const ready = (picks.length > 0 || pendingRows.length > 0 || converted.length > 0) && !mixed && !unconfirmed && !blockedByAllocation && (!needsEvidence || evidence.length > 0) && (!needsCut || Number(covered) >= Number(short)) && (!warned || (matches.every((match) => acknowledged.includes(match.id)) && duplicateReason.trim() !== ''));
    const [seenAnswer, setSeenAnswer] = useState(command.answer);
    const [seenErrors, setSeenErrors] = useState(command.errors);

    if (seenAnswer !== command.answer) {
        setSeenAnswer(command.answer);

        if (command.answer !== null) setConfirming(false);
    }

    if (seenErrors !== command.errors) {
        setSeenErrors(command.errors);

        if (Object.keys(command.errors).length > 0) setConfirming(false);
    }

    useEffect(() => { onLeaveGuard(dirty); }, [dirty, onLeaveGuard]);
    useEffect(() => { scanRef.current?.focus(); }, []);

    const send = () => {
        setSentAs(signature);
        command.submit({
            ...(action === 'penyesuaian' ? { jenis: kind, kasus: caseId === '' ? null : caseId, alasan_alokasi: chosenCase?.cross === true ? allocation : null } : { aksi: action }),
            produk: product.public_id,
            unit: picks.map((pick) => ({ lot: pick.lot, rak: pick.location, kondisi: pick.condition, jumlah: pick.qty, seri: pick.serial, dikonfirmasi: confirmed.includes(pick.key) })),
            tertunda: pendingRows.map((scope) => ({ rak: scope.location, kondisi: scope.condition, jumlah: pendingQty[`${scope.location}|${scope.condition}`] ?? '0' })),
            konversi: converted.map((change) => ({ kasus: change.id, jumlah: convertQty[change.id] ?? '0' })),
            tanpa_identitas: unidentified,
            potong: needsCut ? Object.entries(cuts).filter(([, qty]) => Number(figure(qty)) > 0).map(([reservasi, jumlah]) => ({ reservasi, jumlah })) : [],
            bukti: evidence,
            alasan: reason,
            duplikat: warned ? acknowledged : [],
            alasan_duplikat: warned ? duplicateReason : null,
            kebijakan_seri: product.serialized,
            kebijakan_skala: product.scale,
            tanggal: date.date,
            tanggal_otomatis: !date.chosen,
        }, () => { onLeaveGuard(false); });
    };
    const scanned = (typed: string) => {
        setResult(null);
        setScanError(null);
        void lookUp(typed).then((scan) => {
            if (scan === null) {
                setScanError(t('messages.msg_06'));
            } else if (scan.kind === 'ambiguous') {
                setScanError(t('messages.msg_37', { kode: scan.code }));
            } else if (scan.kind === 'unknown' || scan.matches[0] === undefined) {
                setScanError(t('messages.msg_25', { kode: scan.code }));
            } else if (scan.matches[0].product !== product.public_id) {
                router.visit(`/gudang/penyesuaian?produk=${scan.matches[0].product}&aksi=${action}`);
            } else if (scan.matches[0].kind === 'serial') {
                const number = scan.matches[0].serial ?? scan.code;
                const named = (serial: Serial) => serial.serial.toLowerCase() === number.toLowerCase();
                const add = (row: Serial | undefined) => {
                    if (row === undefined) {
                        setScanError(t('adjustments.scan.absent', { seri: number }));
                    } else if (chosenNow.current.includes(row.handle)) {
                        setScanError(t('messages.msg_36', { seri: number }));
                    } else {
                        chosenNow.current = [...chosenNow.current, row.handle];
                        setChosen(chosenNow.current);
                        setResult(t('receiving.scan.serial_added', { seri: number }));
                    }
                };
                const row = serials.find(named);

                // The list is a first page or a search's: ask the server before calling the unit absent.
                if (row === undefined && (units.more || units.find !== null)) {
                    let found: Serial | undefined;
                    let answered = false;

                    router.reload({
                        only: ['units'],
                        data: { temukan: number },
                        preserveUrl: true,
                        onSuccess: (page) => { answered = true; found = (page.props as unknown as { units?: Units }).units?.serials.filter(offered).find(named); },
                        onFinish: () => { if (answered) add(found); else setScanError(t('messages.msg_06')); },
                    });
                } else {
                    add(row);
                }
            } else {
                setResult(t('adjustments.scan.same_product'));
            }
        });
    };
    const title: TranslationKey = action === 'kondisi' ? 'actions.change_condition' : action === 'periksa' ? 'actions.reinspect' : 'actions.record_adjustment';
    const unplaced = Object.entries(command.errors).filter(([field]) => !['alasan_duplikat', 'alasan_alokasi', 'kasus'].includes(field)).slice(0, 1);

    return (
        <section data-slot="adjustment-form" className="flex min-w-0 flex-col gap-4 pb-18 md:pb-0">
            <ScanNotice notice={guard.notice} />
            {command.answer !== null && !matched && !confirming && <OutcomeMessage answer={command.answer} action={command.unknown ? <Button variant="outline" size="sm" onClick={command.retry}>{t('actions.try_again')}</Button> : undefined} />}
            {warned && <DuplicateWarning matches={matches} message={command.answer?.text} acknowledged={acknowledged} onAcknowledge={setAcknowledged} canContinue reason={{ value: duplicateReason, onChange: setDuplicateReason, error: command.errors.alasan_duplikat ?? null }} />}
            {action === 'penyesuaian' && <p className="text-body text-text-secondary">{t('adjustments.loss_statement')}</p>}
            <BusinessDateControl today={today} value={date} onChange={setDate} />
            {action === 'penyesuaian' && (
                <FormField label={t('adjustments.field.kind')}>
                    {(control) => (
                        <NativeSelect {...control} value={kind} disabled={frozen} onChange={(event) => { setKind(event.target.value); }}>
                            {['DISPOSAL', 'SHRINKAGE', 'OTHER'].map((one) => <option key={one} value={one}>{t(`adjustments.kind.${one}` as TranslationKey)}</option>)}
                        </NativeSelect>
                    )}
                </FormField>
            )}
            {(onlyLot !== null || units.find !== null) && <Button variant="ghost" size="sm" className="self-start" disabled={frozen} onClick={() => { setOnlyLot(null); if (units.find !== null) router.reload({ only: ['units'], preserveUrl: true }); }}>{t('adjustments.all_lots')}</Button>}
            {(units.more || units.find !== null) && <ChoicesFind disabled={frozen} onFind={(text) => { router.reload({ only: ['units'], data: { temukan: text }, preserveUrl: true }); }} />}
            {product.serialized ? (
                <fieldset disabled={frozen} data-slot="adjustment-serials" className="flex min-w-0 flex-col gap-2">
                    <legend className="text-body font-medium">{t('adjustments.field.serials')}</legend>
                    {serials.length === 0 && <RegionEmpty text={t('adjustments.none')} />}
                    {serials.map((serial) => (
                        <label key={serial.handle} className="flex min-h-floor min-w-0 flex-wrap items-center gap-2 text-body">
                            <Checkbox checked={chosen.includes(serial.handle)} disabled={frozen} onCheckedChange={(checked) => { const next = checked ? [...chosen, serial.handle] : chosen.filter((other) => other !== serial.handle); chosenNow.current = next; setChosen(next); }} />
                            <span className="font-mono">{serial.serial}</span>
                            <span className="flex min-w-0 flex-wrap items-center gap-2 text-text-secondary"><Code>{serial.code}</Code>{serial.rack}{` · ${t(`stock.condition.${serial.condition}`)}`}{serial.other && <RelationMarker />}</span>
                        </label>
                    ))}
                </fieldset>
            ) : (
                <fieldset disabled={frozen} data-slot="adjustment-lots" className="flex min-w-0 flex-col gap-2">
                    <legend className="text-body font-medium">{t('adjustments.field.lots')}</legend>
                    <label className="flex min-h-floor items-center gap-2 text-body">
                        <Checkbox checked={unidentified} disabled={frozen} onCheckedChange={setUnidentified} />
                        {t('adjustments.unidentified')}
                    </label>
                    {places.length === 0 && <RegionEmpty text={t('adjustments.none')} />}
                    {pendingScopes.length > 0 && <p data-slot="adjustment-pending-note" className="text-body text-text-secondary">{t('adjustments.pending_note')}</p>}
                    {pendingScopes.map((scope) => {
                        const key = `${scope.location}|${scope.condition}`;

                        return (
                            <FormField key={key} label={t('adjustments.field.pending', { rak: scope.rack, kondisi: t(`stock.condition.${scope.condition}`) })} className="w-72">
                                {(control) => <DecimalInput {...control} scale={product.scale} value={pendingQty[key] ?? ''} onValueChange={(next) => { setPendingQty({ ...pendingQty, [key]: next ?? '' }); }} />}
                            </FormField>
                        );
                    })}
                    {mixed && <p role="status" data-slot="adjustment-mixed" className="text-body font-medium text-danger-fg">{t('adjustments.refusal.mixed')}</p>}
                    {conversions.map((change) => (
                        <FormField key={change.id} label={t('adjustments.field.conversion', { rak: change.rack, jumlah: change.qty })} className="w-72">
                            {(control) => <DecimalInput {...control} scale={product.scale} value={convertQty[change.id] ?? ''} onValueChange={(next) => { setConvertQty({ ...convertQty, [change.id]: next ?? '' }); }} />}
                        </FormField>
                    ))}
                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {places.map((place) => {
                            const key = placeKey(place);

                            return (
                                <li key={key} data-slot="adjustment-lot" className="flex min-w-0 flex-wrap items-end gap-3 py-2 text-body">
                                    <span className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                        <Code>{place.code}</Code>
                                        <span>{`${place.qty} · ${place.rack} · ${t(`stock.condition.${place.condition}`)}`}</span>
                                        {place.oldest && <Badge>{t('stock.lot.oldest')}</Badge>}
                                        {place.other && <RelationMarker />}
                                    </span>
                                    <FormField label={t('adjustments.field.quantity', { kode: place.code, rak: place.rack })} className="w-40">
                                        {(control) => <DecimalInput {...control} scale={product.scale} value={quantities[key] ?? ''} onValueChange={(next) => { setQuantities({ ...quantities, [key]: next ?? '' }); }} />}
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
                </fieldset>
            )}
            {needsCut && (can.reserve
                ? <ReservationCut shortfall={t('stock.reverse.shortfall', { kurang: short })} unit={product.unit} reservations={units.reservations} cuts={cuts} scale={product.scale} onCut={(id, value) => { setCuts({ ...cuts, [id]: value ?? '' }); }} />
                : <p role="alert" className="text-body font-medium text-danger-fg">{t('receiving.refusal.reservations')}</p>)}
            {action === 'penyesuaian' && (caseRows.length > 0 || cases?.find != null) && (
                <FormField label={t('adjustments.field.case')} error={command.errors.kasus}>
                    {(control) => (
                        <NativeSelect {...control} value={caseId} disabled={frozen} onChange={(event) => { setCaseId(event.target.value); }}>
                            <option value="">{t('adjustments.no_case')}</option>
                            {caseRows.map((one) => <option key={one.id} value={one.id}>{[one.date, one.lot, one.project].filter((part) => part !== null).join(' · ')}</option>)}
                        </NativeSelect>
                    )}
                </FormField>
            )}
            {action === 'penyesuaian' && cases !== null && cases.find !== null && (
                <>
                    {cases.rows.length === 0 && <p role="status" className="text-body text-text-secondary">{t('adjustments.case_none')}</p>}
                    <Button variant="ghost" size="sm" className="self-start" disabled={frozen} onClick={() => { router.reload({ only: ['cases'], preserveUrl: true }); }}>{t('adjustments.all_cases')}</Button>
                </>
            )}
            {action === 'penyesuaian' && cases !== null && (cases.more || cases.find !== null) && <ChoicesFind disabled={frozen} label={t('adjustments.find_case')} help={t('adjustments.find_case_help')} onFind={(text) => { router.reload({ only: ['cases'], data: { cari_kasus: text }, preserveUrl: true }); }} />}
            {chosenCase?.cross === true && (
                <FormField label={t('forms.intercompany_reason')} error={command.errors.alasan_alokasi}>
                    {(control) => <Input {...control} value={allocation} maxLength={500} disabled={frozen} onChange={(event) => { setAllocation(event.target.value); }} />}
                </FormField>
            )}
            {needsEvidence && (
                <div data-slot="adjustment-evidence" className="flex min-w-0 flex-col gap-2">
                    {proofsFailed ? (
                        <p role="alert" className="flex min-w-0 flex-wrap items-center gap-3 text-body text-danger-fg">
                            {t('receiving.list.group_failed')}
                            <Button variant="outline" size="sm" onClick={() => { router.reload({ only: ['proofs'] }); }}>{t('receiving.list.reload')}</Button>
                        </p>
                    ) : proofs === null ? <RegionLoading /> : (
                        <FormField label={t('adjustments.field.proof')} error={command.errors.bukti}>
                            {(control) => (
                                <NativeSelect {...control} value={evidence[0] ?? ''} disabled={frozen} onChange={(event) => { setEvidence(event.target.value === '' ? [] : [event.target.value]); }}>
                                    <option value="">{t('dispatch.substitute.choose')}</option>
                                    {uploaded !== null && <option value={uploaded}>{t('dispatch.substitute.evidence_uploaded')}</option>}
                                    {latest([...knownProofs.filter((row) => evidence.includes(row.id)), ...proofs.rows], (row) => row.id).filter((row) => row.id !== uploaded).map((row) => <option key={row.id} value={row.id}>{row.label}</option>)}
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
            <div className="flex min-w-0 flex-wrap items-center gap-3">
                <Button size="lg" disabled={!ready || frozen} onClick={() => { if (!warned) command.clear(); setConfirming(true); }}>{t(title)}</Button>
                <Button variant="ghost" size="lg" disabled={frozen} onClick={() => { router.visit(origin); }}>{t('actions.cancel')}</Button>
            </div>
            <ConfirmCommand
                open={confirming && !command.unknown}
                onOpenChange={setConfirming}
                title={t(title)}
                consequence={t(action === 'kondisi' ? 'adjustments.confirm.condition' : action === 'periksa' ? 'adjustments.confirm.reinspect' : 'adjustments.confirm.adjust')}
                confirmLabel={t(title)}
                destructive={action === 'penyesuaian'}
                pending={command.pending}
                reason={{ value: reason, onChange: setReason, error: command.errors.alasan ?? null }}
                onConfirm={send}
            />
            {can.evidence && <ProofSheet open={uploading} onOpenChange={setUploading} company={null} pool types={POOL_TYPES} today={today} back={`${window.location.pathname}${window.location.search}`} onRegistered={(id) => { setUploaded(id); setEvidence([id]); setUploading(false); }} />}
            <ScanField dock inputRef={scanRef} value={code} onValueChange={setCode} onLookup={scanned} result={result} error={scanError} />
        </section>
    );
}

export default function Adjustments({ product, action, lot, search, found, units, cases, proofs, losses, today, can }: Props) {
    const { t } = useTranslation();
    const allowed: Action[] = [...(can.condition ? (['kondisi', 'periksa'] as const) : []), ...(can.adjust ? (['penyesuaian'] as const) : [])];
    const current = action !== null && allowed.includes(action) ? action : allowed[0] ?? null;
    const [origin] = useState(() => previousPage() ?? (product === null ? '/gudang/stok' : `/gudang/stok/${product.public_id}`));
    const [typed, setTyped] = useState(search ?? '');
    const [dirty, setDirty] = useState(false);
    const [leaving, setLeaving] = useState<(() => void) | null>(null);
    const departing = useRef(false);
    const label = (one: Action): TranslationKey => (one === 'kondisi' ? 'actions.change_condition' : one === 'periksa' ? 'actions.reinspect' : 'actions.record_adjustment');

    // NAV-027; NV-05: leaving with unsent input asks MSG-35.
    useEffect(() => {
        const off = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (!dirty || departing.current || visit.method !== 'get' || visit.only.length > 0 || isBackground(visit)) return;

            const href = visit.url.href;
            setLeaving(() => () => { router.visit(href); });

            return false;
        });
        const unload = (event: BeforeUnloadEvent) => { if (dirty && !departing.current) event.preventDefault(); };
        window.addEventListener('beforeunload', unload);

        return () => { off(); window.removeEventListener('beforeunload', unload); };
    }, [dirty]);

    const loaded = <T,>(value: T | Failed | undefined): T | null => (value === undefined || (typeof value === 'object' && value !== null && 'failed' in value) ? null : (value));
    const shownLosses = loaded<Loss[]>(losses);

    return (
        <AppShell title={t('pages.pg_027.title')} back={{ href: origin, label: t('dialogs.back') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_027.title')} crumbs={[{ label: t('pages.pg_018.title'), href: '/gudang/stok' }]} />
                {product === null ? (
                    <form role="search" className="flex min-w-0 flex-col gap-3" onSubmit={(event) => { event.preventDefault(); router.get('/gudang/penyesuaian', typed === '' ? {} : { cari: typed }); }}>
                        <FormField label={t('adjustments.choose_product')}>
                            {(control) => <Input {...control} value={typed} maxLength={100} onChange={(event) => { setTyped(event.target.value); }} />}
                        </FormField>
                        <Button type="submit" variant="outline" className="self-start">{t('dispatch.line.find_action')}</Button>
                        {search !== null && found.length === 0 && <RegionEmpty text={t('adjustments.no_product')} />}
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle text-body">
                            {found.map((row) => <li key={row.id} className="py-2"><a className="underline" href={`/gudang/penyesuaian?produk=${row.id}`}>{row.name}</a></li>)}
                        </ul>
                    </form>
                ) : (
                    <>
                        <p className="text-subhead">{product.name}</p>
                        {current === null ? <RegionEmpty text={t('adjustments.not_allowed')} /> : (
                            <div role="group" aria-label={t('adjustments.field.action')} className="flex min-w-0 flex-wrap gap-2">
                                {allowed.map((one) => <Button key={one} variant={one === current ? 'primary' : 'outline'} size="sm" aria-pressed={one === current} onClick={() => { router.visit(`/gudang/penyesuaian?produk=${product.public_id}&aksi=${one}`); }}>{t(label(one))}</Button>)}
                            </div>
                        )}
                        {current !== null && (units === undefined || units === null ? <RegionLoading /> : (
                            <Form key={current} product={product} action={current} lot={lot} units={units} cases={loaded<Cases>(cases)} proofs={loaded<Proofs>(proofs)} proofsFailed={proofs !== undefined && 'failed' in proofs} today={today} can={can} origin={origin} onLeaveGuard={setDirty} />
                        ))}
                        {shownLosses !== null && shownLosses.length > 0 && (
                            <section data-slot="adjustment-losses" className="flex min-w-0 flex-col gap-2 max-md:pb-18">
                                <h2 className="text-subhead">{t('adjustments.losses.title')}</h2>
                                <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                    {shownLosses.map((loss) => <LossRecord key={loss.id} loss={loss} today={today} canReverse={can.reverse} />)}
                                </ul>
                            </section>
                        )}
                    </>
                )}
            </div>
            <UnsentInputDialog open={leaving !== null} kind="leave" onStay={() => { setLeaving(null); }}
                onLeave={() => { const go = leaving; departing.current = true; setLeaving(null); go?.(); }} />
        </AppShell>
    );
}
