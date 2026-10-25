// The local stack's component gallery (routes/fixtures.php; TASK-015): every primitive and feature component of the
// design system on one page in both languages, so the browser suite verifies their behaviour and keyboard (TL-09),
// the language switch keeping the unsent input in every field (TL-10; LOCALIZATION §3.7), the bands (TL-11),
// accessibility (TL-12) and the CSP (TL-14; HO-37) before the first pages use them. The values are sample data
// passed as the server would; the copy is the catalogue's. The page is not shipped in a production build (TL-13).
import { Boxes, ReceiptText, Wallet } from 'lucide-react';
import { type ReactNode, useRef, useState } from 'react';

import { ActionBar } from '@/components/action-bar';
import { CompanyFilter, CompanyTag, RelationMarker } from '@/components/company';
import { ConfirmCommand, useConfirmKey } from '@/components/confirm-dialog';
import { DataList, ListContinuation, type Continuation } from '@/components/data-list';
import { BusinessDateControl, type BusinessDate, CodeEntry, DateField, DateRangeControl, type DayRange } from '@/components/date-field';
import { FilterToolbar } from '@/components/filters';
import { DecimalInput, FormField, FormSection, ValidationSummary } from '@/components/form';
import LanguageSwitch from '@/components/LanguageSwitch';
import { LineEditor } from '@/components/line-editor';
import { Lookup, type LookupGroup, type LookupResult } from '@/components/lookup';
import { CommandResult, CommandSteps, DuplicateWarning, Prerequisite, ReservationCut, SplitAction, StaleComparison, UploadField, type UploadState } from '@/components/notices';
import type { Answer } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import {
    Disclosure,
    FactsList,
    InventoryBlock,
    KpiTile,
    LongText,
    MetricStrip,
    NowStrip,
    PartStrip,
    PositionBlock,
    SegmentedStage,
    StageTrack,
    Timeline,
} from '@/components/record-blocks';
import { RegionEmpty, RegionError, RegionLoading, RegionNoMatch, SystemPage } from '@/components/region-state';
import { ScanField, ScanNotice, useScanGuard } from '@/components/scan-field';
import { IdleWarning, ReauthDialog, StepUpDialog, UnsentInputDialog } from '@/components/session-dialogs';
import { CapabilityPreview, ReadinessList, SignalGroup, Thumbnail } from '@/components/signals';
import { Mark, StatusBadge, StatusText } from '@/components/status-badge';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import { NativeSelect } from '@/components/ui/native-select';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Progress } from '@/components/ui/progress';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { ToastViewport, useToastManager } from '@/components/ui/toast';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Money, Quantity } from '@/components/values';
import * as decimal from '@/lib/decimal';
import { useTranslation } from '@/lib/i18n';

// Sample data, as a server would send it: business values, not copy.
const SAMPLE = {
    today: '2026-10-05',
    company: 'ARJ',
    invoice: 'ARJ/INV/2026/0147',
    account: 'Rina Wulandari',
    email: 'rina@example.test',
    reference: 'ERR-7F3K-2Q9M',
    companies: [
        { code: 'ARJ', name: 'PT Arjuna Sarana' },
        { code: 'BTN', name: 'PT Bintang Niaga' },
        { code: 'KSM', name: 'CV Kusuma Mandiri' },
    ],
    suppliers: [
        { value: 'supplier', label: 'Pemasok', items: [{ id: 's1', code: 'SUP-0012', label: 'PT Sinar Kertas', exact: true }, { id: 's2', code: 'SUP-0040', label: 'CV Sinar Jaya' }] },
    ] satisfies LookupGroup[],
    products: [{ value: 'product', label: 'Produk', items: [{ id: 'p1', code: 'KRT-A4-80', label: 'Kertas A4 80 gsm', exact: true }, { id: 'p2', code: 'KRT-F4-70', label: 'Kertas F4 70 gsm' }] }] satisfies LookupGroup[],
    projects: [
        { value: 'PRJ-0147', label: 'ARJ/PRJ/2026/0147 — Sekolah Harapan' },
        { value: 'PRJ-0152', label: 'ARJ/PRJ/2026/0152 — Dinas Pendidikan' },
    ],
    rows: Array.from({ length: 6 }, (_, index) => ({
        id: `inv-${String(index)}`,
        number: `ARJ/INV/2026/01${String(40 + index)}`,
        client: index % 2 === 0 ? 'SD Negeri 4 Harapan' : 'Dinas Pendidikan Kota',
        amount: index % 3 === 0 ? '12500000' : '3250000.50',
        state: (['ISSUED_NOT_BILLED', 'BILLED_OUTSTANDING', 'PAST_DUE_DATE'] as const)[index % 3] ?? 'ISSUED_NOT_BILLED',
    })),
    units: ['rim', 'dus'],
    keys: ['Ctrl', 'K'],
    channels: [
        { value: 'regular', label: 'Reguler' },
        { value: 'siplah', label: 'SIPLAH' },
    ],
    long: 'Pengiriman kedua menunggu konfirmasi gudang bersama karena rak A-02-03 sedang dihitung ulang setelah selisih ditemukan pada hitungan sebelumnya.',
    presets: [
        { id: 'month', label: 'Okt 2026', range: { from: '2026-10-01', to: '2026-10-31' } },
        { id: 'quarter', label: 'Q3 2026', range: { from: '2026-07-01', to: '2026-09-30' } },
    ],
} as const;

type Line = { key: string; product: LookupResult | null; term: string; quantity: string | null; price: string | null; unit: string };

const blankLine = (key: string): Line => ({ key, product: null, term: '', quantity: null, price: null, unit: 'rim' });

/** One gallery section, named by the pattern it shows — a test anchor, not copy. */
function Section({ id, children }: { id: string; children: ReactNode }) {
    return (
        <section aria-label={id} data-gallery={id} className="flex min-w-0 flex-col gap-4 border-t border-border-subtle pt-6">
            {children}
        </section>
    );
}

export default function ComponentGallery() {
    const { t } = useTranslation();
    const toast = useToastManager();
    const form = useRef<HTMLFormElement>(null);

    // Unsent input in every kind of field (criterion 6).
    const [name, setName] = useState('');
    const [term, setTerm] = useState('');
    const [supplier, setSupplier] = useState<LookupResult | null>(null);
    const [project, setProject] = useState<string | null>(null);
    const [quantity, setQuantity] = useState<string | null>(null);
    const [amount, setAmount] = useState<string | null>(null);
    const [day, setDay] = useState<string | null>(null);
    const [notes, setNotes] = useState('');
    const [direct, setDirect] = useState(false);
    const [channel, setChannel] = useState('regular');
    const [notify, setNotify] = useState(false);
    const [code, setCode] = useState('');
    const [unit, setUnit] = useState('rim');
    const [business, setBusiness] = useState<BusinessDate>({ date: SAMPLE.today, chosen: false });
    const [range, setRange] = useState<DayRange>(SAMPLE.presets[0].range);
    const [errors, setErrors] = useState<{ id: string; label: string; message: string }[]>([]);
    const [lines, setLines] = useState<Line[]>([]);
    const [scan, setScan] = useState('');
    const [scanned, setScanned] = useState<{ code: string; count: number } | null>(null);
    const [scanError, setScanError] = useState<string | null>(null);
    const guard = useScanGuard({ hasScanField: true });
    const [search, setSearch] = useState('');
    const [companies, setCompanies] = useState<string[]>([]);
    const [continuation, setContinuation] = useState<Continuation>('more');
    const [answer, setAnswer] = useState<Answer | null>(null);
    const [dialog, setDialog] = useState<'confirm' | 'step_up' | 'reauth' | 'idle' | 'unsent' | null>(null);
    const [reason, setReason] = useState('');
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [duplicateReason, setDuplicateReason] = useState('');
    const [cuts, setCuts] = useState<Record<string, string>>({});
    const [upload, setUpload] = useState<UploadState>({ phase: 'idle' });
    const [view, setView] = useState('details');

    useConfirmKey(form, () => {
        setDialog('confirm');
    });

    const subtotal = (line: Line) => (line.quantity !== null && line.price !== null ? decimal.round(decimal.multiply(line.quantity, line.price), 2) : null);
    const total = decimal.add('0', ...lines.map((line) => subtotal(line) ?? '0'));

    const answers: [string, Answer][] = [
        [t('outcome.committed'), { kind: 'COMMITTED', text: t('messages.msg_01', { objek: t('glossary.gl_018'), kata_kerja_pasif: t('outcome.committed').toLowerCase() }) }],
        [t('outcome.rejected'), { kind: 'REJECTED', text: t('messages.msg_10') }],
        [t('outcome.conflict_stale'), { kind: 'CONFLICT', cause: 'stale', text: t('messages.msg_03') }],
        [t('outcome.conflict_recorded'), { kind: 'CONFLICT', cause: 'recorded', text: t('messages.msg_04') }],
        [t('outcome.pending'), { kind: 'PENDING', text: t('messages.msg_05', { hasil_turunan: 'PDF', tindakan_utama: SAMPLE.invoice }) }],
        [t('outcome.failed_unknown'), { kind: 'FAILED', cause: 'unknown', text: t('messages.msg_07'), reference: SAMPLE.reference }],
        [t('outcome.failed_not_processed'), { kind: 'FAILED', cause: 'not_processed', text: t('messages.msg_06') }],
        [t('outcome.offline'), { kind: 'OFFLINE', text: t('messages.msg_13') }],
    ];

    return (
        <main className="mx-auto flex w-full max-w-page-standard min-w-0 flex-col gap-6 px-m-gutter pt-6 pb-18 md:px-gutter">
            <div className="flex justify-end">
                <LanguageSwitch />
            </div>

            <PageHeader
                title={SAMPLE.invoice}
                crumbs={[
                    { label: t('glossary.gl_016'), href: '#keuangan' },
                    { label: t('glossary.gl_017'), href: '#piutang' },
                ]}
                back={{ label: t('shell.back'), href: '#piutang' }}
                facts={
                    <>
                        <CompanyTag code={SAMPLE.company} />
                        <StatusBadge family="invoice_and_receivable" state="BILLED_OUTSTANDING" size="lg" />
                        <RelationMarker />
                    </>
                }
                primary={
                    <Button
                        variant="primary"
                        size="lg"
                        onClick={() => {
                            setDialog('confirm');
                        }}
                    >
                        {t('actions.record_payment')}
                    </Button>
                }
                actions={[
                    { label: t('actions.print'), onSelect: () => undefined },
                    { label: t('actions.mark_disputed'), onSelect: () => undefined, tone: 'danger' },
                ]}
                date={<BusinessDateControl today={SAMPLE.today} value={business} onChange={setBusiness} periodOf={(value) => value.slice(0, 7).split('-').reverse().join('/')} />}
            />

            <Section id="PT-09">
                <form
                    ref={form}
                    noValidate
                    className="flex min-w-0 flex-col gap-6 md:max-w-page-form"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const found = [
                            ...(name.trim() === '' ? [{ id: 'gallery-name', label: t('forms.company'), message: t('forms.max_exceeded') }] : []),
                            ...(quantity === null ? [{ id: 'gallery-quantity', label: t('pt10.columns.quantity'), message: t('forms.max_exceeded_tpl', { v: '15' }) }] : []),
                        ];
                        setErrors(found);
                    }}
                >
                    <ValidationSummary fields={errors} />
                    <FormSection title={t('forms.more_details')}>
                        <div className="grid min-w-0 gap-4 md:grid-cols-2">
                            <FormField id="gallery-name" label={t('forms.company')} required error={errors.find((error) => error.id === 'gallery-name')?.message}>
                                {(control) => <Input {...control} name="name" value={name} onChange={(event) => {
                                    setName(event.target.value);
                                }} />}
                            </FormField>
                            <FormField label={t('forms.supplier')}>
                                {() => <Lookup label={t('forms.supplier')} term={term} onTermChange={setTerm} groups={term === '' ? [] : SAMPLE.suppliers} value={supplier} onChoose={setSupplier} create={{ object: t('forms.supplier'), onCreate: () => undefined }} />}
                            </FormField>
                            <FormField label={t('forms.project')} dependsOn={supplier === null ? t('forms.supplier') : undefined}>
                                {(control) => (
                                    <Select value={project} onValueChange={setProject} disabled={control.disabled} items={SAMPLE.projects}>
                                        <SelectTrigger id={control.id} aria-describedby={control['aria-describedby']}>
                                            <SelectValue placeholder={t('forms.choose')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {SAMPLE.projects.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </FormField>
                            <FormField id="gallery-quantity" label={t('pt10.columns.quantity')} required error={errors.find((error) => error.id === 'gallery-quantity')?.message}>
                                {(control) => <DecimalInput {...control} scale={3} unit={unit} value={quantity ?? ''} onValueChange={(value) => {
                                    setQuantity(value);
                                }} />}
                            </FormField>
                            <FormField label={t('pt10.columns.unit')}>
                                {(control) => (
                                    <NativeSelect
                                        {...control}
                                        value={unit}
                                        onChange={(event) => {
                                            setUnit(event.target.value);
                                        }}
                                    >
                                        {SAMPLE.units.map((option) => (
                                            <option key={option} value={option}>
                                                {option}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                )}
                            </FormField>
                            <FormField label={t('pt10.columns.price')}>
                                {(control) => <DecimalInput {...control} kind="money" scale={2} value={amount ?? ''} onValueChange={(value) => {
                                    setAmount(value);
                                }} />}
                            </FormField>
                            <FormField label={t('glossary.gl_167')}>
                                {(control) => <DateField {...control} value={day} latest={SAMPLE.today} onValueChange={(value) => {
                                    setDay(value);
                                }} />}
                            </FormField>
                            <FormField label={t('pages.pg_002.code_label')}>
                                {(control) => <CodeEntry {...control} value={code} onValueChange={setCode} />}
                            </FormField>
                        </div>
                        <FormField label={t('glossary.gl_166')} help={t('messages.msg_40')}>
                            {(control) => <Textarea {...control} value={notes} onChange={(event) => {
                                setNotes(event.target.value);
                            }} />}
                        </FormField>
                        <label className="flex items-center gap-3 text-body">
                            <Checkbox checked={direct} onCheckedChange={setDirect} />
                            {t('actions.ship_direct')}
                        </label>
                        <RadioGroup
                            value={channel}
                            onValueChange={(value) => {
                                setChannel(String(value));
                            }}
                        aria-label={t('glossary.gl_060')}>
                            {SAMPLE.channels.map((option) => (
                                <label key={option.value} className="flex items-center gap-3 text-body">
                                    <RadioGroupItem value={option.value} />
                                    {option.label}
                                </label>
                            ))}
                        </RadioGroup>
                        <label className="flex items-center gap-3 text-body">
                            <Switch checked={notify} onCheckedChange={setNotify} />
                            {t('glossary.gl_036')}
                        </label>
                        <DateRangeControl label={t('glossary.gl_020')} presets={[...SAMPLE.presets]} value={range} onChange={setRange} latest={SAMPLE.today} />
                    </FormSection>
                    <div className="flex gap-2">
                        <Button type="submit" variant="outline" size="md">
                            {t('actions.continue')}
                        </Button>
                    </div>
                </form>
            </Section>

            <Section id="PT-10">
                <LineEditor<Line>
                    label={t('purchase.lines')}
                    lines={lines}
                    onChange={setLines}
                    blank={blankLine}
                    isBlank={(line) => line.product === null && line.quantity === null && line.price === null}
                    columns={[
                        {
                            key: 'product',
                            header: t('pt10.columns.product'),
                            className: 'min-w-64',
                            cell: (line, update) => (
                                <Lookup
                                    label={t('pt10.columns.product')}
                                    term={line.term}
                                    onTermChange={(value) => { update({ term: value }); }}
                                    groups={line.term === '' ? [] : SAMPLE.products}
                                    value={line.product}
                                    onChoose={(product) => { update({ product }); }}
                                />
                            ),
                        },
                        {
                            key: 'quantity',
                            header: t('pt10.columns.quantity'),
                            numeric: true,
                            className: 'w-32',
                            cell: (line, update) => <DecimalInput aria-label={t('pt10.columns.quantity')} scale={3} value={line.quantity ?? ''} onValueChange={(value) => { update({ quantity: value }); }} />,
                        },
                        { key: 'unit', header: t('pt10.columns.unit'), cell: (line) => line.unit },
                        {
                            key: 'price',
                            header: t('pt10.columns.price'),
                            numeric: true,
                            className: 'w-40',
                            cell: (line, update) => <DecimalInput aria-label={t('pt10.columns.price')} kind="money" scale={2} value={line.price ?? ''} onValueChange={(value) => { update({ price: value }); }} />,
                        },
                        {
                            key: 'subtotal',
                            header: t('pt10.columns.subtotal'),
                            numeric: true,
                            cell: (line) => {
                                const value = subtotal(line);

                                return value === null ? '' : <Money value={value} />;
                            },
                        },
                    ]}
                    summary={(line) => ({
                        title: line.product?.label ?? '',
                        figure: subtotal(line) === null ? undefined : <Money value={subtotal(line) ?? '0'} />,
                        secondary: line.quantity === null ? undefined : <Quantity value={line.quantity} unit={line.unit} />,
                    })}
                    details={() => (
                        <label className="flex items-center gap-3 text-body">
                            <Checkbox />
                            {t('actions.ship_direct')}
                        </label>
                    )}
                />
                <p className="flex items-baseline justify-end gap-3 text-body">
                    <span className="text-text-secondary">
                        {t('purchase.total')} {t('finance.estimate')}
                    </span>
                    <Money value={total} className="font-semibold" />
                </p>
            </Section>

            <Section id="PT-20">
                <ScanField
                    value={scan}
                    onValueChange={setScan}
                    onLookup={(value) => {
                        if (value === 'XXXX') {
                            setScanError(t('messages.msg_25', { kode: value }));
                        } else {
                            setScanError(null);
                            setScanned((last) => ({ code: value, count: last?.code === value ? last.count + 1 : 1 }));
                        }
                    }}
                    onSearch={() => undefined}
                    result={scanned === null ? undefined : `${scanned.code} · ${String(scanned.count)}×`}
                    error={scanError}
                />
                <ScanNotice notice={guard.notice} />
            </Section>

            <Section id="PT-05">
                <FilterToolbar
                    search={{ label: t('shell.search'), value: search, onChange: setSearch }}
                    company={<CompanyFilter companies={[...SAMPLE.companies]} selected={companies} onChange={setCompanies} />}
                    applied={companies.map((value) => ({ id: value, label: value, onRemove: () => {
                        setCompanies(companies.filter((other) => other !== value));
                    } }))}
                    onReset={() => {
                        setCompanies([]);
                    }}
                    more={<DateRangeControl label={t('glossary.gl_020')} presets={[...SAMPLE.presets]} value={range} onChange={setRange} />}
                />
                {search !== '' && !SAMPLE.rows.some((row) => row.number.includes(search)) ? (
                    <RegionNoMatch
                        term={search}
                        onClear={() => {
                            setSearch('');
                        }}
                    clearLabel={t('actions.reset')} />
                ) : (
                    <DataList
                        caption={t('glossary.gl_017')}
                        columns={[
                            { key: 'number', header: t('purchase.col_no'), cell: (row) => row.number },
                            { key: 'client', header: t('glossary.gl_048'), cell: (row) => row.client, priority: 'low' },
                            { key: 'state', header: t('glossary.gl_017'), cell: (row) => <StatusText family="invoice_and_receivable" state={row.state} /> },
                            { key: 'amount', header: t('pages.pg_043.value'), numeric: true, cell: (row) => <Money value={row.amount} withSen /> },
                        ]}
                        rows={SAMPLE.rows.filter((row) => row.number.includes(search))}
                        rowKey={(row) => row.id}
                        rowHref={(row) => `#${row.id}`}
                        rowActions={() => [
                            { label: t('actions.print'), onSelect: () => undefined },
                            { label: t('actions.mark_disputed'), onSelect: () => undefined, tone: 'danger' },
                        ]}
                        dense={(row) => ({
                            title: row.number,
                            figure: <Money value={row.amount} withSen />,
                            secondary: row.client,
                            status: <StatusText family="invoice_and_receivable" state={row.state} />,
                        })}
                    />
                )}
                <ListContinuation
                    state={continuation}
                    onLoadMore={() => {
                        setContinuation((state) => (state === 'more' ? 'partial' : state === 'partial' ? 'end' : 'cursor_failed'));
                    }}
                    onReload={() => {
                        setContinuation('more');
                    }}
                />
            </Section>

            <Section id="PT-13">
                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge family="project" state="ACTIVE" />
                    <StatusBadge family="project" state="COMPLETED_FORCED" />
                    <StatusBadge family="rendition" state="FAILED" />
                    <StatusBadge family="payment" state="WITHOUT_PROOF" size="sm" />
                    <StatusText family="reservation" state="ACTIVE" />
                    <Mark kind="backdated" />
                    <Mark kind="code_match" />
                </div>
            </Section>

            <Section id="PT-14">
                <div className="flex flex-wrap gap-2">
                    {answers.map(([label, value]) => (
                        <Button
                            key={label}
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setAnswer(value);
                            }}
                        >
                            {label}
                        </Button>
                    ))}
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            toast.add({ title: t('outcome.committed'), description: SAMPLE.invoice, type: 'success' });
                        }}
                    >
                        {t('toast.close')}
                    </Button>
                </div>
            </Section>

            <Section id="PT-26">
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="md"
                        onClick={() => {
                            setDialog('confirm');
                        }}
                    >
                        {t('actions.record_payment')}
                    </Button>
                    <Button
                        variant="outline"
                        size="md"
                        onClick={() => {
                            setDialog('step_up');
                        }}
                    >
                        {t('dialogs.step_up_title')}
                    </Button>
                    <Button
                        variant="outline"
                        size="md"
                        onClick={() => {
                            setDialog('reauth');
                        }}
                    >
                        {t('dialogs.reauth_title')}
                    </Button>
                    <Button
                        variant="outline"
                        size="md"
                        onClick={() => {
                            setDialog('idle');
                        }}
                    >
                        {t('dialogs.idle_title')}
                    </Button>
                    <Button
                        variant="outline"
                        size="md"
                        onClick={() => {
                            setDialog('unsent');
                        }}
                    >
                        {t('dialogs.unsent_input')}
                    </Button>
                </div>
                <ConfirmCommand
                    open={dialog === 'confirm'}
                    onOpenChange={(open) => {
                        setDialog(open ? 'confirm' : null);
                    }}
                    title={`${t('actions.record_payment')}?`}
                    consequence={t('messages.msg_39_payment')}
                    items={<Money value="300000" />}
                    reason={{ value: reason, onChange: setReason }}
                    confirmLabel={t('actions.record_payment')}
                    answer={answer}
                    onConfirm={() => {
                        setAnswer(answers[0]?.[1] ?? null);
                    }}
                />
                <StepUpDialog
                    open={dialog === 'step_up'}
                    account={SAMPLE.account}
                    onConfirm={() => {
                        setDialog(null);
                    }}
                    onCancel={() => {
                        setDialog(null);
                    }}
                />
                <ReauthDialog
                    open={dialog === 'reauth'}
                    email={SAMPLE.email}
                    onSubmit={() => {
                        setDialog(null);
                    }}
                    onSignOut={() => {
                        setDialog(null);
                    }}
                />
                <IdleWarning
                    open={dialog === 'idle'}
                    minutes={5}
                    onStay={() => {
                        setDialog(null);
                    }}
                />
                <UnsentInputDialog
                    open={dialog === 'unsent'}
                    kind="leave"
                    onStay={() => {
                        setDialog(null);
                    }}
                    onLeave={() => {
                        setDialog(null);
                    }}
                />
            </Section>

            <Section id="PT-19">
                <RegionLoading />
                <RegionEmpty text={t('signals.qs_02_empty')} />
                <RegionError reference={SAMPLE.reference} onReload={() => undefined} />
                <SystemPage kind="not_found" message={t('messages.msg_09')} reference={SAMPLE.reference} back={<Button variant="outline">{t('actions.back_to_work')}</Button>} />
            </Section>

            <Section id="PT-12">
                <Tabs
                    value={view}
                    onValueChange={(value) => {
                        setView(String(value));
                    }}
                >
                    <TabsList>
                        <TabsTab value="details">{t('journey.details')}</TabsTab>
                        <TabsTab value="history">{t('glossary.gl_026')}</TabsTab>
                    </TabsList>
                    <TabsPanel value="details" className="flex flex-col gap-6 pt-4">
                        <NowStrip
                            now={t('journey.stage.agreement')}
                            next={t('journey.next_invoice')}
                            action={
                                <Button variant="primary" size="md">
                                    {t('actions.create_invoice')}
                                </Button>
                            }
                            blockers={t('journey.blocker_overdue')}
                        />
                        <StageTrack
                            label={t('journey.details')}
                            stages={[
                                { key: 'a', label: t('journey.stage.agreement'), state: 'complete' },
                                { key: 'b', label: t('journey.pred.fulfilment'), state: 'current' },
                                { key: 'c', label: t('journey.pred.billing'), state: 'attention' },
                                { key: 'd', label: t('journey.pred.payment'), state: 'upcoming' },
                            ]}
                        />
                        <SegmentedStage stages={[{ key: 'a', done: true }, { key: 'b', done: true }, { key: 'c', done: false }]} current={t('journey.pred.billing')} />
                        <PartStrip
                            parts={[
                                { name: t('journey.pred.delivery'), state: 'IN_PROGRESS', fact: '12 / 20 rim' },
                                { name: t('journey.pred.billing'), state: 'NOT_STARTED', fact: 'Rp0' },
                                { name: t('journey.pred.documents'), state: 'DONE', fact: '3' },
                            ]}
                        />
                        <FactsList
                            columns={4}
                            facts={[
                                { label: t('glossary.gl_048'), value: SAMPLE.rows[0]?.client },
                                { label: t('glossary.gl_033'), value: <CompanyTag code={SAMPLE.company} /> },
                                { label: t('glossary.gl_167'), value: '05 Okt 2026' },
                                { label: t('pages.pg_043.value'), value: <Money value="12500000" />, numeric: true },
                            ]}
                        />
                        <MetricStrip
                            metrics={[
                                { label: t('journey.pos.confirmed'), value: <Money value="45000000" /> },
                                { label: t('journey.pos.invoiced'), value: <Money value="12500000" /> },
                                { label: t('journey.pos.cash_in'), value: <Money value="3000000" /> },
                            ]}
                        />
                        <div className="grid gap-4 md:grid-cols-3">
                            <KpiTile icon={ReceiptText} tone="blue" label={t('glossary.gl_017')} value={<Money value="12500000" />} href="#piutang" />
                            <KpiTile icon={Wallet} tone="mint" label={t('journey.pos.cash_in')} value={<Money value="3000000" />} href="#kas" />
                            <KpiTile icon={Boxes} tone="peach" label={t('glossary.gl_008')} value="20+" href="#stok" />
                        </div>
                        <div className="grid gap-6 md:grid-cols-2">
                            <PositionBlock
                                title={t('journey.financial')}
                                lines={[
                                    { label: t('pages.pg_043.value'), value: <Money value="12500000" /> },
                                    { label: t('finance.paid'), value: <Money value="3000000" /> },
                                    { label: t('finance.proven_deduction'), value: <Money value="0" /> },
                                    { label: t('journey.pos.outstanding'), value: <Money value="9500000" />, emphasis: true },
                                ]}
                            />
                            <InventoryBlock figures={{ physical: '120', reserved: '15', unusable: '2', available: '103', unit: 'rim', alternate: { physical: '24', reserved: '3', unusable: '0.4', available: '20.6', unit: 'dus' } }} belowMinimum="150 rim" />
                        </div>
                        <Disclosure title={t('journey.not_needed')}>
                            <LongText text={SAMPLE.long} className="max-w-sheet-sm" />
                        </Disclosure>
                    </TabsPanel>
                    <TabsPanel value="history" className="pt-4">
                        <Timeline
                            label={t('glossary.gl_026')}
                            entries={[
                                {
                                    id: 'e2',
                                    sequence: 2,
                                    recordedAt: '2026-10-01T07:05:00Z',
                                    businessDate: '2026-09-28',
                                    actor: SAMPLE.account,
                                    sentence: t('actions.record_payment'),
                                    reason: SAMPLE.long,
                                    changes: [{ field: t('pt10.columns.price'), before: 'Rp3.000.000', after: 'Rp3.250.000' }],
                                    corrects: { label: SAMPLE.invoice, href: '#e1' },
                                },
                                { id: 'e1', sequence: 1, recordedAt: '2026-09-28T02:00:00Z', actor: null, sentence: t('status.rendition.ready') },
                            ]}
                        />
                    </TabsPanel>
                </Tabs>
            </Section>

            <Section id="PT-21">
                <div className="grid gap-6 md:grid-cols-2">
                    <SignalGroup
                        title={t('glossary.gl_002')}
                        counts={[
                            { company: 'ARJ', count: '20+' },
                            { company: 'BTN', count: '3' },
                        ]}
                        state={{
                            phase: 'ready',
                            rows: SAMPLE.rows.slice(0, 3).map((row) => ({ id: row.id, condition: t('status.invoice_and_receivable.past_due_date'), company: SAMPLE.company, subject: row.number, age: '4 hari', href: `#${row.id}` })),
                        }}
                        empty={t('signals.qs_02_empty')}
                        seeAll="#antrean"
                        onReload={() => undefined}
                    />
                    <SignalGroup title={t('glossary.gl_003')} counts={[]} state={{ phase: 'error', reference: SAMPLE.reference }} empty={t('signals.qs_21_empty')} seeAll="#tinjauan" onReload={() => undefined} />
                </div>
                <ReadinessList
                    label={t('journey.docs_readiness')}
                    items={[
                        { key: 'po', label: 'PO', complete: true },
                        { key: 'bast', label: 'BAST', complete: false, missing: t('journey.next_bast'), href: '#bast', linkLabel: t('journey.primary_bast') },
                    ]}
                />
                <CapabilityPreview
                    companies={['ARJ', 'BTN']}
                    rows={[
                        { key: 'pay', area: t('glossary.gl_016'), label: t('actions.record_payment'), marks: { ARJ: 'kept', BTN: 'added' } },
                        { key: 'bill', area: t('glossary.gl_016'), label: t('actions.bill'), prerequisite: t('actions.record_payment'), marks: { ARJ: 'removed', BTN: 'inactive' } },
                    ]}
                    summaries={{ ARJ: t('capability.removed'), BTN: t('capability.added') }}
                />
                <div className="flex items-center gap-3">
                    <Thumbnail src={null} alt={SAMPLE.products[0]?.items[0]?.label ?? ''} />
                    <Avatar initial="R" />
                    <KbdGroup>
                        {SAMPLE.keys.map((key) => (
                            <Kbd key={key}>{key}</Kbd>
                        ))}
                    </KbdGroup>
                    <Progress label={t('files.uploading')} value={40} valueText="40%" className="w-40" />
                </div>
            </Section>

            <Section id="IP-05">
                <DuplicateWarning
                    matches={SAMPLE.rows.slice(0, 2).map((row) => ({ id: row.id, label: row.number, href: `#${row.id}` }))}
                    message={t('messages.msg_15', { daftar_catatan_yang_cocok: SAMPLE.rows[0]?.number ?? '' })}
                    acknowledged={acknowledged}
                    onAcknowledge={setAcknowledged}
                    reason={{ value: duplicateReason, onChange: setDuplicateReason }}
                    canContinue
                />
                <StaleComparison
                    fields={[
                        { key: 'price', label: t('pt10.columns.price'), current: 'Rp3.000.000', mine: 'Rp3.250.000', differs: true },
                        { key: 'qty', label: t('pt10.columns.quantity'), current: '20 rim', mine: '20 rim', differs: false },
                    ]}
                    onReapply={() => undefined}
                    onDiscard={() => undefined}
                />
                <ReservationCut
                    shortfall={<Quantity value="5" unit="rim" />}
                    unit="rim"
                    scale={3}
                    cuts={cuts}
                    onCut={(id, value) => { setCuts({ ...cuts, [id]: value ?? '' }); }}
                    reservations={[
                        { id: 'r1', reserved: '15', project: { label: 'ARJ/PRJ/2026/0147', href: '#prj' } },
                        { id: 'r2', reserved: '4', project: null },
                    ]}
                />
                <UploadField
                    label={t('glossary.gl_014')}
                    accept="application/pdf,image/jpeg,image/png,image/webp"
                    limits={t('files.limits')}
                    state={upload}
                    onChoose={() => {
                        setUpload({ phase: 'uploading', progress: 35 });
                    }}
                    onCancel={() => { setUpload({ phase: 'idle' }); }}
                    onRemove={() => { setUpload({ phase: 'idle' }); }}
                    onRetry={() => { setUpload({ phase: 'idle' }); }}
                />
                <CommandSteps
                    steps={[
                        { key: 'pay', label: t('actions.record_payment'), answer: answers[0]?.[1] ?? null },
                        { key: 'apply', label: t('actions.apply_to_invoice'), answer: answers[6]?.[1] ?? null, action: <Button size="sm">{t('actions.try_again')}</Button> },
                    ]}
                />
                <CommandResult title={t('outcome.committed')} number={SAMPLE.invoice} rendition={<StatusBadge family="rendition" state="PENDING" />} next={<Button variant="primary">{t('actions.open_invoice')}</Button>} />
                <Prerequisite capability={t('glossary.gl_036')} />
            </Section>

            <Section id="PT-11">
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="primary">{t('actions.create_invoice')}</Button>
                    <Button variant="outline">{t('actions.print')}</Button>
                    <Button variant="ghost">{t('actions.cancel')}</Button>
                    <Button variant="sunken">{t('actions.filter')}</Button>
                    <Button variant="accent">{t('shell.create')}</Button>
                    <Button variant="danger">{t('actions.mark_disputed')}</Button>
                    <Button variant="primary" pending pendingLabel={t('states.loading')}>
                        {t('actions.record_payment')}
                    </Button>
                    <SplitAction
                        primary={<Button variant="primary">{t('actions.issue_po')}</Button>}
                        actions={[
                            { label: t('actions.print'), onSelect: () => undefined },
                            { label: t('actions.cancel_purchase'), onSelect: () => undefined, tone: 'danger' },
                        ]}
                    />
                    <ToggleGroup value={[view]} onValueChange={(next: string[]) => {
                        setView(next[0] ?? view);
                    }}>
                        <ToggleGroupItem value="details">{t('journey.details')}</ToggleGroupItem>
                        <ToggleGroupItem value="history">{t('glossary.gl_026')}</ToggleGroupItem>
                    </ToggleGroup>
                    <Tooltip>
                        <TooltipTrigger render={<Button variant="ghost" size="icon-md" aria-label={t('actions.copy_number')} />}>#</TooltipTrigger>
                        <TooltipContent>{t('actions.copy_number')}</TooltipContent>
                    </Tooltip>
                    <Popover>
                        <PopoverTrigger render={<Button variant="outline" size="md" />}>{t('readiness.missing')}</PopoverTrigger>
                        <PopoverContent>{t('journey.other_met')}</PopoverContent>
                    </Popover>
                </div>
            </Section>

            <ActionBar answer={answer} label={t('actions.record_payment')}>
                <Button
                    variant="outline"
                    size="lg"
                    onClick={() => {
                        setAnswer(null);
                    }}
                >
                    {t('actions.cancel')}
                </Button>
                <Button
                    variant="primary"
                    size="lg"
                    onClick={() => {
                        setDialog('confirm');
                    }}
                >
                    {t('actions.record_payment')}
                </Button>
            </ActionBar>
            <ToastViewport closeLabel={t('toast.close')} />
        </main>
    );
}
