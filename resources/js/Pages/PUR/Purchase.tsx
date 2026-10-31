// PG-016 *Detail Pembelian* (ROUTE-016, ROUTE-172–ROUTE-175, ROUTE-178–ROUTE-180; NAV-016; SCR-16; CALC-03; J-PUR-01–J-PUR-03; TASK-037): its lines with ordered, received and remaining quantities, charges with their allocation, receipts, disbursements with `finance.view` and documents; "Terbitkan PO" opening PG-036 with the draft (`DIR-081` OB-02); the amendment before the first effect, "Batalkan Pembelian" only with nothing received, confirmed, performed or paid, otherwise "Tutup Sisa Pembelian"; each correction listing what it re-attributes, with its reason (IP-09). A control without its capability is absent (H7-03); a control without its route is never shown (RC-06): "Terima Barang" and "Retur ke Pemasok" arrive with TASK-039 and TASK-044.
import { router } from '@inertiajs/react';
import { useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { type BusinessDate, BusinessDateControl } from '@/components/date-field';
import { DecimalInput, FormField, FormSection } from '@/components/form';
import { OutcomeMessage, type Answer } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { FactsList, Timeline } from '@/components/record-blocks';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateText, Money, Quantity } from '@/components/values';
import { ProofSheet } from '@/features/fin/proof-sheet';
import { BASES, type Bill, type Charge, blankCharge, BasisValues, chargePayload, ChargeFields } from '@/features/pur/charges';
import { estimatedTotal, type Line, linePayload, type Product, PurchaseLines, type Tax, useLineProducts } from '@/features/pur/purchase-lines';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type LineRow = {
    handle: string; no: number; kind: 'GOODS' | 'NONSTOCK'; mode: 'WAREHOUSE' | 'DROPSHIP'; description: string; product: Product; quantity: string; unit: string; base_unit: string; price: string; value: string; tax: string;
    demand: string | null; demand_no: string | null; ordered: string; received: string; closed: string; remaining: string; performed: string | null; taxes: { code: string; name: string; treatment: string; rate: string; amount: string }[];
};
type ChargeRow = { handle: string; classification: 'ATTRIBUTABLE' | 'NON_ATTRIBUTABLE'; basis: string | null; amount: string; allocated: string; expensed: string; bill: string; issuer: string; reference: string | null; shares: { line: number; share: string; posted: string }[] };
type Props = {
    purchase: { id: string; company_id: string; number: string; state: 'OPEN' | 'CLOSED' | 'CANCELLED'; lock_version: number; date: string; company: string; supplier: string; reference: string | null; project: { id: string; number: string; title: string } | null; cancelled_reason: string | null; cancelled_at: string | null };
    lines: LineRow[];
    charges: ChargeRow[];
    receipts: { line: number; quantity: string; date: string; lot: string | null }[];
    disbursements: { id: string; amount: string; date: string }[] | null;
    documents: { id: string; type: string; state: string | null; number: string | null }[];
    cascade: { lots: { code: string; line: number | null }[]; consumptions: { lot: string; line: number | null; kind: string; quantity: string; project: string | null }[]; projects: { number: string; line: number | null }[] };
    timeline: { action: string; by: string | null; at: string; reason: string | null }[];
    bills: Bill[];
    taxes: Tax[];
    product?: Product | null;
    first_effect: boolean;
    today: string;
    can: { amend: boolean; supplement: boolean; charge: boolean; mark: boolean; unmark: boolean; cancel: boolean; close: boolean; correct: boolean; issue: boolean; evidence: boolean };
};
type Dialog = { kind: 'cancel' } | { kind: 'close' } | { kind: 'correct'; target: string; correction: 'PRICE_CORRECTION' | 'TAX_CORRECTION' | 'CHARGE_CORRECTION'; label: string; current: string; line: number | null } | { kind: 'basis'; charge: string } | { kind: 'unmark'; line: string };

const positive = (value: string): boolean => !/^0*(\.0*)?$/.test(value);

function Answered({ command }: { command: { answer: Answer | null; unknown: boolean; retry: () => void } }) {
    const { t } = useTranslation();

    return command.answer === null ? null : <OutcomeMessage answer={command.answer} action={command.unknown ? <Button variant="outline" size="sm" onClick={command.retry}>{t('actions.try_again')}</Button> : undefined} />;
}

/** What a correction re-attributes, before it is sent: for a line, its holders and whatever depends on them; for a charge, all of them (ADMIN_FLOW §11; criterion 14). */
function Cascade({ cascade, line }: { cascade: Props['cascade']; line: number | null }) {
    const { t } = useTranslation();
    const reaches = (row: { line: number | null }) => line === null || row.line === null || row.line === line;
    const lots = cascade.lots.filter(reaches);
    const consumptions = cascade.consumptions.filter(reaches);
    const projects = cascade.projects.filter(reaches);

    return (
        <dl data-slot="purchase-cascade" className="flex min-w-0 flex-col gap-1 text-caption">
            <div className="flex flex-wrap gap-2"><dt className="text-text-secondary">{t('purchases.cascade.lots')}</dt><dd>{lots.length === 0 ? '—' : lots.map((lot) => lot.code).join(', ')}</dd></div>
            <div className="flex flex-col gap-1">
                <dt className="text-text-secondary">{t('purchases.cascade.consumptions')}</dt>
                {consumptions.length === 0 ? <dd>—</dd> : consumptions.map((row, index) => <dd key={index}>{`${row.lot} · ${row.quantity.replace(/\.?0+$/, '')}${row.project === null ? '' : ` · ${row.project}`}`}</dd>)}
            </div>
            <div className="flex flex-wrap gap-2"><dt className="text-text-secondary">{t('purchases.cascade.projects')}</dt><dd>{projects.length === 0 ? '—' : projects.map((project) => project.number).join(', ')}</dd></div>
        </dl>
    );
}

export default function Purchase({ purchase, lines, charges, receipts, disbursements, documents, cascade, timeline, bills, taxes, first_effect, today, can }: Props) {
    const { t } = useTranslation();
    const base = `/pembelian/${purchase.id}`;
    const [dialog, setDialog] = useState<Dialog | null>(null);
    const [reason, setReason] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [value, setValue] = useState('');
    const [closures, setClosures] = useState<Record<string, string>>({});
    const [basis, setBasis] = useState<string>('LINE_VALUE');
    const [basisValues, setBasisValues] = useState<string[]>([]);
    const [amending, setAmending] = useState<Line[] | null>(null);
    const [late, setLate] = useState<Charge | null>(null);
    const [uploading, setUploading] = useState(false);
    const send = useCommand<Record<string, never>>(() => (dialog?.kind === 'cancel' ? `${base}/batalkan` : dialog?.kind === 'close' ? `${base}/tutup-sisa` : dialog?.kind === 'correct' ? `${base}/koreksi-biaya` : dialog?.kind === 'basis' ? `${base}/koreksi-dasar-alokasi` : `${base}/baris/${dialog?.kind === 'unmark' ? dialog.line : ''}/terlaksana`), 'post');
    const mark = useCommand<Record<string, never>>(() => `${base}/baris/${marking}/terlaksana`, 'post');
    const [marking, setMarking] = useState('');
    const amend = useCommand<Record<string, never>>(() => base, 'put');
    const charge = useCommand<Record<string, never>>(() => `${base}/biaya`, 'post');
    const prepare = useCommand<Record<string, never>>(() => '/dokumen', 'post');
    const chooseProduct = useLineProducts((update) => { setAmending((current) => (current === null ? current : update(current))); });
    const goodsLines = lines.filter((line) => line.kind === 'GOODS');
    const openLines = goodsLines.filter((line) => positive(line.remaining));
    const chargeLabels = lines.map((line) => ({ key: line.handle, label: `${String(line.no)}. ${line.product.name}` }));
    const open = (next: Dialog) => {
        send.clear();
        setReason('');
        setValue(next.kind === 'correct' ? next.current : '');
        setClosures(Object.fromEntries(openLines.map((line) => [line.handle, line.remaining.replace(/\.?0+$/, '')])));
        setBasis('LINE_VALUE');
        setBasisValues([]);
        setDialog(next);
    };
    const finish = () => { setDialog(null); router.reload(); };
    const when = { tanggal: date.date, tanggal_otomatis: !date.chosen };
    const confirm = () => {
        if (dialog === null) return;
        const payload = dialog.kind === 'cancel' ? { ...when, alasan: reason }
            : dialog.kind === 'close' ? { ...when, alasan: reason, baris: Object.entries(closures).filter(([, quantity]) => quantity !== '' && positive(quantity)).map(([handle, quantity]) => ({ baris: handle, jumlah: quantity })) }
            : dialog.kind === 'correct' ? { ...when, alasan: reason, jenis: dialog.correction, catatan: dialog.target, nilai: value }
            : dialog.kind === 'basis' ? { ...when, alasan: reason, catatan: dialog.charge, dasar: basis, nilai_dasar: basis === 'WEIGHT' || basis === 'EXPLICIT' ? basisValues : [] }
            : { batalkan: true, alasan: reason };
        (send.submit as unknown as (value: typeof payload, done: () => void) => void)(payload, finish);
    };
    const startAmend = () => {
        setAmending(goodsLines.map((line): Line => ({ key: line.handle, handle: line.handle, kind: 'GOODS', term: '', product: line.product, quantity: line.quantity.replace(/\.?0+$/, ''), unit: line.unit, price: line.price, direct: line.mode === 'DROPSHIP', demand: line.demand ?? '', description: line.description, taxes: line.taxes.map((tax) => ({ code: tax.code, rate: tax.rate, amount: tax.amount })) })));
    };
    const amendError = (index: number, field: string) => amend.errors[`baris.${String(index)}.${field}`];
    const dialogConfirm: Record<string, { title: string; consequence: string; label: string; destructive: boolean }> = {
        cancel: { title: t('purchases.cancel.title'), consequence: t('purchases.cancel.consequence'), label: t('actions.cancel_purchase'), destructive: true },
        close: { title: t('purchases.close.title'), consequence: t('purchases.close.consequence'), label: t('glossary.gl_067'), destructive: true },
        correct: { title: t('purchases.correct.title'), consequence: t('purchases.correct.consequence'), label: t('actions.correct_price_tax_charge'), destructive: false },
        basis: { title: t('purchases.basis_correct.title'), consequence: t('purchases.correct.consequence'), label: t('actions.correct_allocation_basis'), destructive: false },
        unmark: { title: t('purchases.unmark.title'), consequence: t('purchases.unmark.consequence'), label: t('actions.undo_performed'), destructive: false },
    };
    const face = dialog === null ? undefined : dialogConfirm[dialog.kind];

    return (
        <AppShell title={purchase.number} back={purchase.project !== null ? { href: `/proyek/${purchase.project.id}`, label: purchase.project.number } : { href: '/pembelian', label: t('pages.pg_015.title') }}>
            <div className="flex min-w-0 flex-col gap-6 pb-12">
                <PageHeader
                    title={<span className="flex min-w-0 flex-wrap items-center gap-3"><Code>{purchase.number}</Code><StatusBadge family="purchase" state={purchase.state} /></span>}
                    crumbs={[{ label: t('pages.pg_015.title'), href: '/pembelian' }]}
                />
                <Answered command={mark} />
                <Answered command={prepare} />
                <FactsList facts={[
                    { label: t('purchases.field.supplier'), value: purchase.supplier },
                    { label: t('purchases.field.company'), value: purchase.company },
                    { label: t('purchases.field.date'), value: <DateText value={purchase.date} /> },
                    { label: t('purchases.field.project'), value: purchase.project === null ? t('purchases.list.replenishment') : <a className="underline" href={`/proyek/${purchase.project.id}`}><Code>{purchase.project.number}</Code></a> },
                    { label: t('pages.pg_017.supplier_quote'), value: purchase.reference ?? '—' },
                ]} />
                <div className="flex min-w-0 flex-wrap gap-2" data-slot="purchase-actions">
                    {can.issue && <Button variant="outline" disabled={prepare.pending} onClick={() => { (prepare.submit as unknown as (value: unknown) => void)({ jenis: 'DOC-02', subjek: { purchase_id: purchase.id } }); }}>{t('actions.issue_po')}</Button>}
                    {can.issue && <Button variant="outline" disabled={prepare.pending} onClick={() => { (prepare.submit as unknown as (value: unknown) => void)({ jenis: 'DOC-14', subjek: { purchase_id: purchase.id } }); }}>{t('purchases.action.issue_order')}</Button>}
                    {can.amend && amending === null && <Button variant="outline" onClick={startAmend}>{t('purchases.action.amend')}</Button>}
                    {can.supplement && <Button variant="outline" render={<a href={`/pembelian/baru?dari=${purchase.id}`} />}>{t('actions.add_follow_up_purchase')}</Button>}
                    {can.charge && late === null && <Button variant="outline" onClick={() => { charge.clear(); setLate(blankCharge('late', '')); }}>{t('actions.record_late_charge')}</Button>}
                    {can.close && openLines.length > 0 && <Button variant="outline" onClick={() => { open({ kind: 'close' }); }}>{t('glossary.gl_067')}</Button>}
                    {can.cancel && <Button variant="outline" onClick={() => { open({ kind: 'cancel' }); }}>{t('actions.cancel_purchase')}</Button>}
                </div>
                {purchase.state === 'OPEN' && !can.cancel && can.close && <p className="text-caption text-text-secondary">{t('purchases.cancel.points_to_closure')}</p>}
                {purchase.cancelled_reason !== null && <p className="text-body text-text-secondary">{t('purchases.cancel.reason_line', { alasan: purchase.cancelled_reason })}</p>}
                {amending !== null && (
                    <FormSection title={t('purchases.action.amend')}>
                        <PurchaseLines lines={amending} setLines={(update) => { setAmending((current) => (current === null ? current : update(current))); }} onTouch={() => undefined} chooseProduct={chooseProduct} error={amendError} taxes={taxes} demand={[]} previous={{}} nonStock={purchase.project !== null} canAddNonStock={false} />
                        <p className="flex items-baseline justify-end gap-3 text-body"><span className="text-text-secondary">{t('purchases.form.estimate')}</span><Money value={estimatedTotal(amending)} className="font-semibold" /></p>
                        <Answered command={amend} />
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" onClick={() => { setAmending(null); amend.clear(); }}>{t('dialogs.back')}</Button>
                            <Button pending={amend.pending} pendingLabel={t('purchases.action.amend_save')} onClick={() => { (amend.submit as unknown as (value: unknown, done: () => void) => void)({ lock_version: purchase.lock_version, baris: linePayload(amending) }, () => { setAmending(null); router.reload(); }); }}>{t('purchases.action.amend_save')}</Button>
                        </div>
                    </FormSection>
                )}
                <FormSection title={t('purchase.lines')}>
                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {lines.map((line) => (
                            <li key={line.handle} data-line={line.no} className="flex min-w-0 flex-col gap-2 py-3">
                                <div className="flex min-w-0 flex-wrap items-baseline justify-between gap-2">
                                    <span className="min-w-0 text-body font-medium">{`${String(line.no)}. ${line.product.name}`}</span>
                                    <Money value={line.value} />
                                </div>
                                {line.description !== line.product.name && <p className="text-caption text-text-secondary">{line.description}</p>}
                                <div className="flex min-w-0 flex-wrap gap-x-6 gap-y-1 text-caption text-text-secondary">
                                    <span>{`${t('purchases.line.entered')}: ${line.quantity.replace(/\.?0+$/, '')} ${line.unit}`}</span>
                                    <span>{t('purchases.line.ordered')}: <Quantity value={line.ordered} unit={line.base_unit} /></span>
                                    {line.kind === 'GOODS' && <span>{t('purchases.line.received')}: <Quantity value={line.received} unit={line.base_unit} /></span>}
                                    {line.kind === 'GOODS' && <span>{t('pages.pg_016.remaining')}: <Quantity value={line.remaining} unit={line.base_unit} /></span>}
                                    {positive(line.closed) && <span>{t('purchases.line.closed')}: <Quantity value={line.closed} unit={line.base_unit} /></span>}
                                    {line.kind === 'NONSTOCK' && <span>{t('purchases.line.nonstock')}</span>}
                                    {line.mode === 'DROPSHIP' && <span>{t('purchases.line.direct')}</span>}
                                    {line.demand_no !== null && <span>{t('purchases.line.for_demand', { nomor: line.demand_no })}</span>}
                                    {line.taxes.map((tax) => <span key={tax.code}>{`${tax.name}: `}<Money value={tax.amount} /></span>)}
                                    {line.performed !== null && <span className="font-medium text-text-primary">{t('purchases.line.performed')}</span>}
                                </div>
                                <div className="flex min-w-0 flex-wrap gap-2">
                                    {line.kind === 'NONSTOCK' && line.performed === null && can.mark && <Button variant="outline" size="sm" pending={mark.pending && marking === line.handle} pendingLabel={t('actions.mark_performed')} onClick={() => { setMarking(line.handle); window.setTimeout(() => { (mark.submit as unknown as (value: unknown, done: () => void) => void)({}, () => { router.reload(); }); }, 0); }}>{t('actions.mark_performed')}</Button>}
                                    {line.kind === 'NONSTOCK' && line.performed !== null && can.unmark && <Button variant="outline" size="sm" onClick={() => { open({ kind: 'unmark', line: line.handle }); }}>{t('actions.undo_performed')}</Button>}
                                    {can.correct && (line.kind === 'NONSTOCK' || first_effect) && <Button variant="ghost" size="sm" onClick={() => { open({ kind: 'correct', target: line.handle, correction: 'PRICE_CORRECTION', label: `${String(line.no)}. ${line.product.name}`, current: line.value, line: line.no }); }}>{t('purchases.action.correct_price')}</Button>}
                                    {can.correct && (line.kind === 'NONSTOCK' || first_effect) && <Button variant="ghost" size="sm" onClick={() => { open({ kind: 'correct', target: line.handle, correction: 'TAX_CORRECTION', label: `${String(line.no)}. ${line.product.name}`, current: line.tax, line: line.no }); }}>{t('purchases.action.correct_tax')}</Button>}
                                </div>
                            </li>
                        ))}
                    </ul>
                </FormSection>
                <FormSection title={t('pages.pg_017.charge')}>
                    {charges.length === 0 ? <p className="text-body text-text-secondary">{t('purchases.charge.none')}</p> : (
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {charges.map((row) => (
                                <li key={row.handle} data-charge={row.handle} className="flex min-w-0 flex-col gap-1 py-3">
                                    <div className="flex min-w-0 flex-wrap items-baseline justify-between gap-2">
                                        <span className="min-w-0 text-body">{row.classification === 'ATTRIBUTABLE' ? t('pages.pg_017.class_attributable') : t('pages.pg_017.class_expense')}{row.basis !== null ? ` · ${t(`purchases.basis.${row.basis}` as TranslationKey)}` : ''}</span>
                                        <Money value={row.amount} />
                                    </div>
                                    <p className="text-caption text-text-secondary">{`${t('pages.pg_017.source_bill')}: ${row.issuer}${row.reference === null ? '' : ` · ${row.reference}`}`}</p>
                                    {row.shares.length > 0 && (
                                        <p className="flex min-w-0 flex-wrap gap-x-4 text-caption text-text-secondary">
                                            {row.shares.map((share) => <span key={share.line}>{`${t('purchases.charge.share_of', { baris: String(share.line) })}: `}<Money value={share.share} /></span>)}
                                        </p>
                                    )}
                                    {can.correct && row.classification === 'ATTRIBUTABLE' && (
                                        <div className="flex flex-wrap gap-2">
                                            <Button variant="ghost" size="sm" onClick={() => { open({ kind: 'correct', target: row.handle, correction: 'CHARGE_CORRECTION', label: row.issuer, current: row.amount, line: null }); }}>{t('purchases.action.correct_charge')}</Button>
                                            <Button variant="ghost" size="sm" onClick={() => { open({ kind: 'basis', charge: row.handle }); }}>{t('actions.correct_allocation_basis')}</Button>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                    {late !== null && (
                        <div className="flex min-w-0 flex-col gap-3">
                            <ChargeFields charge={late} bills={bills} lines={chargeLabels} error={(field) => charge.errors[field === 'tagihan' ? 'tagihan' : field]} onChange={(patch) => { setLate({ ...late, ...patch }); }} {...(can.evidence ? { onUpload: () => { setUploading(true); } } : {})} />
                            <BusinessDateControl today={today} value={date} onChange={setDate} />
                            <Answered command={charge} />
                            <div className="flex flex-wrap gap-2">
                                <Button variant="outline" onClick={() => { setLate(null); }}>{t('dialogs.back')}</Button>
                                <Button pending={charge.pending} pendingLabel={t('actions.record_late_charge')} onClick={() => { const { tagihan, klasifikasi, jumlah, dasar, nilai_dasar: nilai } = chargePayload(late); (charge.submit as unknown as (value: unknown, done: () => void) => void)({ ...when, tagihan, klasifikasi, jumlah, dasar, nilai_dasar: nilai }, () => { setLate(null); router.reload(); }); }}>{t('actions.record_late_charge')}</Button>
                            </div>
                        </div>
                    )}
                </FormSection>
                <FormSection title={t('purchases.section.receipts')}>
                    {receipts.length === 0 ? <RegionEmpty text={t('purchases.section.no_receipts')} /> : (
                        <ul role="list" className="flex flex-col gap-1 text-body">
                            {receipts.map((row, index) => <li key={index}>{t('purchases.charge.share_of', { baris: String(row.line) })}: <Quantity value={row.quantity} unit="" /></li>)}
                        </ul>
                    )}
                </FormSection>
                {disbursements !== null && (
                    <FormSection title={t('purchases.section.disbursements')}>
                        {disbursements.length === 0 ? <RegionEmpty text={t('purchases.section.no_disbursements')} /> : (
                            <ul role="list" className="flex flex-col gap-1 text-body">
                                {disbursements.map((row) => <li key={row.id} className="flex flex-wrap gap-3"><DateText value={row.date} /><Money value={row.amount} /></li>)}
                            </ul>
                        )}
                    </FormSection>
                )}
                <FormSection title={t('purchases.section.documents')}>
                    {documents.length === 0 ? <RegionEmpty text={t('purchases.section.no_documents')} /> : (
                        <ul role="list" className="flex flex-col gap-1 text-body">
                            {documents.map((row) => <li key={row.id}><a className="underline" href={`/dokumen/${row.id}`}>{`${t(`documents.${row.type.toLowerCase().replace('-', '_')}` as TranslationKey)}${row.number === null ? '' : ` · ${row.number}`}`}</a></li>)}
                        </ul>
                    )}
                </FormSection>
                <FormSection title={t('purchases.section.timeline')}>
                    <Timeline label={t('purchases.section.timeline')} entries={timeline.map((entry, index) => ({ id: String(index), sequence: timeline.length - index, recordedAt: entry.at, actor: entry.by, sentence: t(`purchases.timeline.${entry.action.replace('PUR.', '')}` as TranslationKey), ...(entry.reason !== null ? { reason: entry.reason } : {}) }))} />
                </FormSection>
            </div>
            {face !== undefined && dialog !== null && (
                <ConfirmCommand
                    open
                    onOpenChange={(next) => { if (!next) setDialog(null); }}
                    title={face.title}
                    consequence={face.consequence}
                    confirmLabel={face.label}
                    destructive={face.destructive}
                    pending={send.pending}
                    answer={send.answer}
                    reason={{ value: reason, onChange: setReason, error: send.errors.alasan ?? null }}
                    onConfirm={confirm}
                    items={(
                        <div className="flex min-w-0 flex-col gap-3">
                            {dialog.kind !== 'unmark' && <BusinessDateControl today={today} value={date} onChange={setDate} />}
                            {dialog.kind === 'close' && openLines.map((line) => (
                                <FormField key={line.handle} label={`${String(line.no)}. ${line.product.name}`} help={t('purchases.close.open_of', { sisa: line.remaining.replace(/\.?0+$/, ''), satuan: line.base_unit })}>
                                    {(control) => <DecimalInput {...control} scale={3} value={closures[line.handle] ?? ''} onValueChange={(next) => { setClosures({ ...closures, [line.handle]: next ?? '' }); }} />}
                                </FormField>
                            ))}
                            {dialog.kind === 'correct' && (
                                <>
                                    <FormField label={t(dialog.correction === 'PRICE_CORRECTION' ? 'purchases.correct.goods_value' : dialog.correction === 'TAX_CORRECTION' ? 'purchases.correct.tax_value' : 'purchases.correct.charge_value', { baris: dialog.label })} error={send.errors.nilai}>
                                        {(control) => <DecimalInput {...control} kind="money" scale={2} value={value} onValueChange={(next) => { setValue(next ?? ''); }} />}
                                    </FormField>
                                    <Cascade cascade={cascade} line={dialog.line} />
                                </>
                            )}
                            {dialog.kind === 'basis' && (
                                <>
                                    <FormField label={t('purchases.charge.basis')} error={send.errors.dasar}>
                                        {(control) => (
                                            <NativeSelect {...control} value={basis} onChange={(event) => { setBasis(event.target.value); }}>
                                                {BASES.map((option) => <option key={option} value={option}>{t(`purchases.basis.${option}` as TranslationKey)}</option>)}
                                            </NativeSelect>
                                        )}
                                    </FormField>
                                    {(basis === 'WEIGHT' || basis === 'EXPLICIT') && <BasisValues charge={{ key: 'x', bill: '', classification: 'ATTRIBUTABLE', amount: '', basis, values: basisValues }} lines={chargeLabels} onChange={setBasisValues} />}
                                    <Cascade cascade={cascade} line={null} />
                                </>
                            )}
                        </div>
                    )}
                />
            )}
            {can.evidence && <ProofSheet open={uploading} onOpenChange={setUploading} company={purchase.company_id} types={['SUPPLIER_BILL']} proven today={today} back={base} onRegistered={(id) => { setUploading(false); router.reload({ only: ['bills'], onSuccess: () => { setLate((current) => (current === null ? current : { ...current, bill: id })); } }); }} />}
        </AppShell>
    );
}
