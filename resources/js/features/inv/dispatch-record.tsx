// The dispatch's corrections on PG-024 (ROUTE-188; ROUTE-189; INT-188; INT-189; CM-05; CM-06; CM-36; IP-05; IP-09; IP-19): "Batalkan Barang Keluar (Belum Berangkat)" and "Catat Barang Kembali Tanpa Terkirim" — each lot's or unit's quantity, the condition and rack it returns to, a new reservation with `stock.reserve`, the consumed one never reactivated —; "Koreksi Nomor Seri atau Lot" — quantities and the shipment stay unchanged —, each with its reason; a refusal answers in the dialog, a recorded one on the page. Never a cost.
import type { FormDataConvertible } from '@inertiajs/core';
import { useState } from 'react';

import { RelationMarker } from '@/components/company';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { type BusinessDate, BusinessDateControl } from '@/components/date-field';
import { DecimalInput, FormField } from '@/components/form';
import { DuplicateWarning, type DuplicateMatch } from '@/components/notices';
import { RegionLoading } from '@/components/region-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Code } from '@/components/values';
import { ProofSheet } from '@/features/fin/proof-sheet';
import { ChoicesFind, type Choices } from '@/features/inv/dispatch-line';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

export type Consumption = { handle: string; lot: string; other: boolean; open: string; location: string | null; serials: { handle: string; serial: string }[] };
export type DispatchRecord = { id: string; date: string; line: number; handle: string; name: string; qty: string; unit: string | null; base: string; serialized: boolean; scale: number; undelivered: string; consumptions: Consumption[]; note: IssuedNote | null; issuable: boolean; reversible: boolean };
export type IssuedNote = { id: string; number: string };
export type Proofs = { rows: { id: string; label: string }[]; more: boolean };
type Rack = { id: string; code: string; name: string };
type Entered = { qty: string; condition: 'USABLE' | 'UNUSABLE'; rack: string };
type ReturnRow = { key: string; consumption: Consumption; serial: string | null; label: string; unitOnly: boolean };
type Disposition = 'KEEP' | 'REVISE' | 'VOID';

// WORKFLOWS §8: the correction revises, voids or keeps with its reason the issued Surat Jalan it affects, in its own transaction.
function NoteDisposition({ note, canCorrect, choice, reason, disabled, errors, onChoice, onReason }: { note: IssuedNote; canCorrect: boolean; choice: Disposition; reason: string; disabled: boolean; errors: Record<string, string>; onChoice: (next: Disposition) => void; onReason: (next: string) => void }) {
    const { t } = useTranslation();

    return (
        <fieldset disabled={disabled} data-slot="note-disposition" className="flex min-w-0 flex-col gap-2 border-t border-border-subtle pt-3">
            <legend className="text-body font-medium">{t('dispatch.document.issued', { nomor: note.number })}</legend>
            <FormField label={t('dispatch.document.choice')} error={errors.dokumen}>
                {(control) => (
                    <NativeSelect {...control} value={choice} onChange={(event) => { onChoice(event.target.value === 'REVISE' ? 'REVISE' : event.target.value === 'VOID' ? 'VOID' : 'KEEP'); }}>
                        <option value="KEEP">{t('dispatch.document.KEEP')}</option>
                        {canCorrect && <option value="REVISE">{t('dispatch.document.REVISE')}</option>}
                        {canCorrect && <option value="VOID">{t('dispatch.document.VOID')}</option>}
                    </NativeSelect>
                )}
            </FormField>
            <FormField label={t('dispatch.document.reason')} required error={errors.alasan_dokumen}>
                {(control) => <Input {...control} value={reason} maxLength={500} onChange={(event) => { onReason(event.target.value); }} />}
            </FormField>
        </fieldset>
    );
}

export function ReverseDispatchDialog({ record, kind, racks, today, canReserve, canCorrect, onClose, onDone }: { record: DispatchRecord; kind: 'DISPATCH_REVERSAL' | 'UNDELIVERED_RETURN'; racks: Rack[]; today: string; canReserve: boolean; canCorrect: boolean; onClose: () => void; onDone: () => void }) {
    const { t } = useTranslation();
    const [reason, setReason] = useState('');
    const [reserve, setReserve] = useState('');
    const [entered, setEntered] = useState<Record<string, Entered>>({});
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [duplicateReason, setDuplicateReason] = useState('');
    const [sentAs, setSentAs] = useState('');
    const [disposition, setDisposition] = useState<Disposition>('KEEP');
    const [documentReason, setDocumentReason] = useState('');
    const reverse = useCommand<Record<string, FormDataConvertible>>(() => `/gudang/barang-keluar/${record.id}/balikkan`, 'post');
    const frozen = reverse.pending || reverse.unknown;
    const rows = record.consumptions.filter((one) => one.open !== '0').flatMap((one): ReturnRow[] => (one.serials.length > 0
        ? one.serials.map((serial) => ({ key: serial.handle, consumption: one, serial: serial.handle, label: serial.serial, unitOnly: true }))
        : [{ key: one.handle, consumption: one, serial: null, label: one.lot, unitOnly: false }]));
    const value = (key: string, fallback: string | null): Entered => entered[key] ?? { qty: '', condition: 'USABLE', rack: fallback ?? racks[0]?.id ?? '' };
    const chosen = rows.filter((row) => Number(value(row.key, row.consumption.location).qty) > 0);
    const matches = Array.isArray(reverse.state.matches) ? (reverse.state.matches as DuplicateMatch[]) : [];
    const signature = JSON.stringify([entered, reserve, date.date]);
    const matched = reverse.answer?.kind === 'REJECTED' && matches.length > 0;
    // A likely duplicate answers what was sent: once the entry changes, it is asked again.
    const warned = matched && sentAs === signature;
    const send = () => {
        const unit = chosen.map((row) => {
            const one = value(row.key, row.consumption.location);

            return { konsumsi: row.consumption.handle, jumlah: one.qty, satuan_dasar: true, kondisi: one.condition, rak: one.rack, ...(row.serial === null ? {} : { seri: row.serial }) };
        });
        setSentAs(signature);
        reverse.submit({ jenis: kind, unit, ...(reserve === '' ? {} : { reservasi: reserve }), alasan: reason, ...(warned ? { duplikat: acknowledged, alasan_duplikat: duplicateReason } : {}), ...(record.note === null ? {} : { dokumen: disposition, alasan_dokumen: documentReason }), kebijakan_seri: record.serialized, kebijakan_skala: record.scale, tanggal: date.date, tanggal_otomatis: !date.chosen }, onDone);
    };
    const title: TranslationKey = kind === 'DISPATCH_REVERSAL' ? 'dispatch.reverse.cancel' : 'dispatch.reverse.returned';

    return (
        <ConfirmCommand
            open
            onOpenChange={(next) => { if (!next) onClose(); }}
            title={t(title)}
            consequence={t('dispatch.reverse.consequence')}
            confirmLabel={t(title)}
            destructive
            pending={reverse.pending}
            answer={matched ? null : reverse.answer}
            blocked={chosen.length === 0 || (record.note !== null && documentReason.trim() === '') || (warned && (!matches.every((match) => acknowledged.includes(match.id)) || duplicateReason.trim() === ''))}
            reason={{ value: reason, onChange: setReason, error: reverse.errors.alasan ?? null }}
            onConfirm={send}
            items={(
                <div className="flex min-w-0 flex-col gap-4">
                    {warned && (
                        <DuplicateWarning matches={matches} message={reverse.answer?.text} acknowledged={acknowledged} onAcknowledge={setAcknowledged} canContinue
                            reason={{ value: duplicateReason, onChange: setDuplicateReason, error: reverse.errors.alasan_duplikat ?? null }} />
                    )}
                    <p className="text-caption text-text-secondary">{t('dispatch.reverse.undelivered', { sisa: record.undelivered })}</p>
                    <BusinessDateControl today={today} value={date} onChange={setDate} />
                    {rows.map((row) => {
                        const one = value(row.key, row.consumption.location);
                        const set = (patch: Partial<Entered>) => { setEntered({ ...entered, [row.key]: { ...one, ...patch } }); };

                        return (
                            <fieldset key={row.key} disabled={frozen} data-slot="reverse-row" className="flex min-w-0 flex-col gap-2 border-t border-border-subtle pt-3">
                                <legend className="flex min-w-0 flex-wrap items-center gap-2 text-body font-medium"><Code>{row.label}</Code>{row.consumption.other && <RelationMarker />}</legend>
                                <FormField label={t('dispatch.reverse.quantity', { sisa: row.unitOnly ? '1' : row.consumption.open })}>
                                    {(control) => <DecimalInput {...control} scale={record.scale} value={one.qty} onValueChange={(next) => { set({ qty: next ?? '' }); }} />}
                                </FormField>
                                <FormField label={t('stock.column.condition')}>
                                    {(control) => (
                                        <NativeSelect {...control} value={one.condition} onChange={(event) => { set({ condition: event.target.value === 'UNUSABLE' ? 'UNUSABLE' : 'USABLE' }); }}>
                                            <option value="USABLE">{t('stock.condition.USABLE')}</option>
                                            <option value="UNUSABLE">{t('stock.condition.UNUSABLE')}</option>
                                        </NativeSelect>
                                    )}
                                </FormField>
                                <FormField label={t('stock.column.rack')}>
                                    {(control) => (
                                        <NativeSelect {...control} value={one.rack} onChange={(event) => { set({ rack: event.target.value }); }}>
                                            {racks.map((rack) => <option key={rack.id} value={rack.id}>{`${rack.code} · ${rack.name}`}</option>)}
                                        </NativeSelect>
                                    )}
                                </FormField>
                            </fieldset>
                        );
                    })}
                    {canReserve && (
                        <FormField label={t('dispatch.reverse.reserve')} help={t('dispatch.reverse.reserve_help')} error={reverse.errors.reservasi}>
                            {(control) => <DecimalInput {...control} scale={record.scale} disabled={frozen} value={reserve} onValueChange={(next) => { setReserve(next ?? ''); }} />}
                        </FormField>
                    )}
                    {record.note !== null && <NoteDisposition note={record.note} canCorrect={canCorrect} choice={disposition} reason={documentReason} disabled={frozen} errors={reverse.errors} onChoice={setDisposition} onReason={setDocumentReason} />}
                    {Object.entries(reverse.errors).filter(([field]) => !['alasan', 'alasan_duplikat', 'reservasi', 'dokumen', 'alasan_dokumen'].includes(field)).slice(0, 1).map(([field, message]) => <p key={field} role="alert" className="text-body font-medium text-danger-fg">{message}</p>)}
                </div>
            )}
        />
    );
}

export function SubstituteDialog({ record, choices, proofs, company, canEvidence, canCorrect, today, onClose, onDone, onFind, onFindProof }: { record: DispatchRecord; choices: Choices | null; proofs: Proofs | null; company: string; canEvidence: boolean; canCorrect: boolean; today: string; onClose: () => void; onDone: () => void; onFind: (text: string) => void; onFindProof: (text: string) => void }) {
    const { t } = useTranslation();
    const open = record.consumptions.filter((one) => one.open !== '0');
    const [reason, setReason] = useState('');
    const [consumption, setConsumption] = useState(open[0]?.handle ?? '');
    const [recorded, setRecorded] = useState('');
    const [quantity, setQuantity] = useState(record.serialized ? '1' : '');
    const [shipped, setShipped] = useState('');
    const [serial, setSerial] = useState('');
    const [allocation, setAllocation] = useState('');
    const [evidence, setEvidence] = useState<string | null>(null);
    const [uploaded, setUploaded] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);
    const [disposition, setDisposition] = useState<Disposition>('KEEP');
    const [documentReason, setDocumentReason] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const substitute = useCommand<Record<string, FormDataConvertible>>(() => `/gudang/barang-keluar/${record.id}/ganti-identitas`, 'post');
    const frozen = substitute.pending || substitute.unknown;
    const current = open.find((one) => one.handle === consumption);
    const lots = choices?.lots ?? [];
    const chosenSerial = choices?.serials.find((one) => one.handle === serial);
    const place = record.serialized ? chosenSerial : lots.find((lot) => `${lot.lot}|${lot.location}` === shipped);
    // The Surat Jalan names the serials: only a serial's substitution affects what it says.
    const affects = record.note !== null && record.serialized;
    const ready = place !== undefined && quantity !== '' && evidence !== null && (!record.serialized || recorded !== '') && (!place.other || allocation.trim() !== '') && (!affects || documentReason.trim() !== '');
    const send = () => {
        if (place === undefined || evidence === null) return;
        substitute.submit({ konsumsi: consumption, jumlah: quantity, ...(recorded === '' ? {} : { seri_tercatat: recorded }), lot: place.lot, rak: place.location, ...(serial === '' ? {} : { seri_terkirim: serial }), ...(place.other ? { alasan_alokasi: allocation } : {}), bukti: evidence, alasan: reason, ...(affects ? { dokumen: disposition, alasan_dokumen: documentReason } : {}), kebijakan_seri: record.serialized, kebijakan_skala: record.scale, tanggal: date.date, tanggal_otomatis: !date.chosen }, onDone);
    };

    return (
        <>
            {/* The proof's sheet takes this dialog's place while it is open, each modal in turn; what was entered stays. */}
            <ConfirmCommand
                open={!uploading}
                onOpenChange={(next) => { if (!next) onClose(); }}
                title={t('dispatch.substitute.action')}
                consequence={t('dispatch.substitute.consequence')}
                confirmLabel={t('dispatch.substitute.action')}
                pending={substitute.pending}
                answer={substitute.answer}
                blocked={!ready}
                reason={{ value: reason, onChange: setReason, error: substitute.errors.alasan ?? null }}
                onConfirm={send}
                items={choices === null ? <RegionLoading /> : (
                    <fieldset disabled={frozen} className="flex min-w-0 flex-col gap-3">
                        <BusinessDateControl today={today} value={date} onChange={setDate} />
                        <FormField label={t('dispatch.substitute.recorded')}>
                            {(control) => (
                                <NativeSelect {...control} value={consumption} onChange={(event) => { setConsumption(event.target.value); setRecorded(''); }}>
                                    {open.map((one) => <option key={one.handle} value={one.handle}>{`${one.lot} · ${one.open}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        {choices.more && <ChoicesFind disabled={frozen} onFind={onFind} />}
                        {record.serialized ? (
                            <>
                                <FormField label={t('dispatch.substitute.recorded_serial')}>
                                    {(control) => (
                                        <NativeSelect {...control} value={recorded} onChange={(event) => { setRecorded(event.target.value); }}>
                                            <option value="">{t('dispatch.substitute.choose')}</option>
                                            {(current?.serials ?? []).map((one) => <option key={one.handle} value={one.handle}>{one.serial}</option>)}
                                        </NativeSelect>
                                    )}
                                </FormField>
                                <FormField label={t('dispatch.substitute.shipped_serial')}>
                                    {(control) => (
                                        <NativeSelect {...control} value={serial} onChange={(event) => { setSerial(event.target.value); }}>
                                            <option value="">{t('dispatch.substitute.choose')}</option>
                                            {choices.serials.map((one) => <option key={one.handle} value={one.handle}>{`${one.serial} · ${one.code} · ${one.rack}`}</option>)}
                                        </NativeSelect>
                                    )}
                                </FormField>
                            </>
                        ) : (
                            <>
                                <FormField label={t('dispatch.field.quantity')}>
                                    {(control) => <DecimalInput {...control} scale={record.scale} value={quantity} onValueChange={(next) => { setQuantity(next ?? ''); }} />}
                                </FormField>
                                <FormField label={t('dispatch.substitute.shipped_lot')}>
                                    {(control) => (
                                        <NativeSelect {...control} value={shipped} onChange={(event) => { setShipped(event.target.value); }}>
                                            <option value="">{t('dispatch.substitute.choose')}</option>
                                            {lots.map((lot) => <option key={`${lot.lot}|${lot.location}`} value={`${lot.lot}|${lot.location}`}>{`${lot.code} · ${lot.rack} · ${lot.qty}`}</option>)}
                                        </NativeSelect>
                                    )}
                                </FormField>
                            </>
                        )}
                        {place?.other === true && (
                            <FormField label={t('forms.intercompany_reason')} error={substitute.errors.alasan_alokasi}>
                                {(control) => <Input {...control} value={allocation} maxLength={500} onChange={(event) => { setAllocation(event.target.value); }} />}
                            </FormField>
                        )}
                        {proofs?.more === true && <ChoicesFind disabled={frozen} onFind={onFindProof} label={t('dispatch.substitute.find_evidence')} help={t('dispatch.substitute.find_evidence_help')} />}
                        {proofs === null ? <RegionLoading /> : (
                            <FormField label={t('dispatch.substitute.existing_evidence')} error={substitute.errors.bukti}>
                                {(control) => (
                                    <NativeSelect {...control} value={evidence ?? ''} onChange={(event) => { setEvidence(event.target.value === '' ? null : event.target.value); }}>
                                        <option value="">{t('dispatch.substitute.choose')}</option>
                                        {uploaded !== null && <option value={uploaded}>{t('dispatch.substitute.evidence_uploaded')}</option>}
                                        {proofs.rows.filter((row) => row.id !== uploaded).map((row) => <option key={row.id} value={row.id}>{row.label}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                        )}
                        {canEvidence && <Button variant="outline" className="self-start" onClick={() => { setUploading(true); }}>{t('dispatch.substitute.evidence')}</Button>}
                        <p className="text-caption text-text-secondary">{evidence === null ? t('dispatch.substitute.evidence_needed') : t('dispatch.substitute.evidence_ready')}</p>
                        {affects && record.note !== null && <NoteDisposition note={record.note} canCorrect={canCorrect} choice={disposition} reason={documentReason} disabled={frozen} errors={substitute.errors} onChoice={setDisposition} onReason={setDocumentReason} />}
                        {Object.entries(substitute.errors).filter(([field]) => !['alasan', 'alasan_alokasi', 'bukti', 'dokumen', 'alasan_dokumen'].includes(field)).slice(0, 1).map(([field, message]) => <p key={field} role="alert" className="text-body font-medium text-danger-fg">{message}</p>)}
                    </fieldset>
                )}
            />
            {canEvidence && <ProofSheet open={uploading} onOpenChange={setUploading} company={company} types={['DELIVERY_PROOF', 'WAREHOUSE_RECORD']} today={today} back={window.location.pathname} onRegistered={(id) => { setUploaded(id); setEvidence(id); setUploading(false); }} />}
        </>
    );
}
