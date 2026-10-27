// PG-056 *Produk & Jasa* (ROUTE-056, ROUTE-145–ROUTE-147; NAV-056; SCR-52; IA §8.6; J-MD-04; WF-MD-04; TASK-027): every
// active account's catalogue, search-first through ROUTE-281's lookup, then one product by its address key: its units —
// the base the smallest counted, alternates exact integer multiples (L-31) —, barcodes with "Buat Barcode Internal" and
// "Cetak Label" (the browser's print of the label, nothing written), its serial policy and scale, each price default
// with its history to its family only (PJ-14), its images and, with `stock.view`, its pooled figures. Each edit is its
// own command answered in place (IP-19). `[desk]`: the phone looks up, reads and registers a barcode.
import { router, usePage } from '@inertiajs/react';
import { Plus, Printer } from 'lucide-react';
import { type ReactNode, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { DataList, ListContinuation } from '@/components/data-list';
import { FileThumbnail } from '@/components/file-thumbnail';
import { FormField, FormSection } from '@/components/form';
import { UploadField, type UploadState } from '@/components/notices';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { CommandAnswer, usePartySearch } from '@/components/parties';
import { DataMasterNav } from '@/components/party-nav';
import { Lookup } from '@/components/lookup';
import { RegionEmpty } from '@/components/region-state';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { DateText, Money, Quantity } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { bars, encodable } from '@/lib/code39';
import { uploadFile } from '@/lib/file-upload';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Listed = { id: string; sku: string | null; name: string; kind: string; active: boolean; thumbnail: string | null };
type PriceRow = { amount: string; date: string; reason: string | null };
type Product = {
    id: string;
    kind: 'GOODS' | 'SERVICE';
    sku: string | null;
    name: string;
    base_unit: string | null;
    quantity_scale: number;
    serialized: boolean;
    active: boolean;
    lock_version: number;
    units: { code: string; name: string; ratio: number; base: boolean; lock_version: number }[];
    barcodes: { code: string; kind: string; active: boolean }[];
    images: { file: string; primary: boolean }[];
    prices: { kind: string; history: PriceRow[] }[];
    stock?: { on_hand: string; reserved: string; unusable: string; available: string } | null;
};
type Props = { list: { rows: Listed[]; more: string | null }; product: Product | null; can: { edit: boolean; prices: string[] } };
type Payload = Record<string, string | number | boolean | null>;
type Values = Record<string, string>;

const LIST = '/data-master/produk';

function visit(product: string | null) {
    router.get(product === null ? LIST : `${LIST}?produk=${product}`, {}, { preserveScroll: product !== null });
}

/** A command's success on the page its answer opened, once (IP-01). */
function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

/** One form in a sheet: its fields, its answer in place, "Batal" and every dismissal asking first with unsent input
    (PT-09; MSG-35). It keeps the values and version it opened with: a stale answer leaves the input in place with MSG-03,
    and only "Muat Versi Terbaru" takes the record's latest values and version — never a silent resend over another
    user's change (IP-03; ST-01). */
function FormSheet({
    title,
    url,
    method,
    initial,
    fixed,
    submitLabel,
    onClose,
    children,
}: {
    title: string;
    url: string;
    method: 'post' | 'put';
    initial: Values;
    fixed: Payload;
    submitLabel: string;
    onClose: () => void;
    children: (values: Values, set: (field: string, value: string) => void, errors: Record<string, string>) => ReactNode;
}) {
    const { t } = useTranslation();
    const [opened, setOpened] = useState({ initial, fixed });
    const [values, setValues] = useState<Values>(initial);
    const [leaving, setLeaving] = useState(false);
    const command = useCommand<Payload>(() => url, method);
    const dirty = JSON.stringify(values) !== JSON.stringify(opened.initial);
    const stale = command.answer?.kind === 'CONFLICT' && command.answer.cause === 'stale';

    return (
        <Sheet open onOpenChange={(open) => { if (!open) { if (dirty) setLeaving(true); else onClose(); } }}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                </SheetHeader>
                <form
                    className="flex min-w-0 flex-col gap-6"
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        command.submit({ ...opened.fixed, ...values }, onClose);
                    }}
                >
                    {children(values, (field, value) => { setValues((current) => ({ ...current, [field]: value })); }, command.errors)}
                    <CommandAnswer command={command} />
                    {stale && (
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setOpened({ initial, fixed });
                                    setValues(initial);
                                    command.clear();
                                }}
                            >
                                {t('forms.load_latest')}
                            </Button>
                        </div>
                    )}
                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="ghost" onClick={() => { if (dirty) setLeaving(true); else onClose(); }}>
                            {t('actions.cancel')}
                        </Button>
                        <Button type="submit" pending={command.pending} disabled={command.frozen}>
                            {submitLabel}
                        </Button>
                    </div>
                    <UnsentInputDialog open={leaving} kind="leave" onStay={() => { setLeaving(false); }} onLeave={onClose} />
                </form>
            </SheetContent>
        </Sheet>
    );
}

function TextField({ name, values, set, errors, required = false, multiline = false }: { name: string; values: Values; set: (field: string, value: string) => void; errors: Record<string, string>; required?: boolean; multiline?: boolean }) {
    const { t } = useTranslation();

    return (
        <FormField label={t(`products.field.${name}` as TranslationKey)} required={required} error={errors[name]}>
            {(control) =>
                multiline ? (
                    <Textarea {...control} value={values[name] ?? ''} maxLength={500} onChange={(event) => { set(name, event.target.value); }} />
                ) : (
                    <Input {...control} value={values[name] ?? ''} inputMode={name === 'ratio' || name === 'amount' ? 'decimal' : undefined} onChange={(event) => { set(name, event.target.value); }} />
                )
            }
        </FormField>
    );
}

function ScaleField({ values, set, errors }: { values: Values; set: (field: string, value: string) => void; errors: Record<string, string> }) {
    const { t } = useTranslation();

    return (
        <FormField label={t('products.field.quantity_scale')} required help={t('products.field.quantity_scale_help')} error={errors.quantity_scale}>
            {(control) => (
                <NativeSelect {...control} value={values.quantity_scale ?? '0'} onChange={(event) => { set('quantity_scale', event.target.value); }}>
                    {['0', '1', '2', '3'].map((scale) => (
                        <option key={scale} value={scale}>
                            {t(`products.scale.${scale}` as TranslationKey)}
                        </option>
                    ))}
                </NativeSelect>
            )}
        </FormField>
    );
}

/** "Cetak Label" (ROUTE_CONTRACTS §4; DESIGN_SYSTEM §17): the label view the browser prints — the Code 39 symbol, the
    code and the product's name —, shown only on paper; nothing is written. */
function Label({ product, code }: { product: Product; code: string }) {
    const symbol = bars(code);

    return createPortal(
        <div id="label-print" className="hidden print:flex print:flex-col print:items-start print:gap-1 print:p-4">
            <svg role="img" aria-label={code} viewBox={`0 0 ${String(symbol.width + 20)} 60`} width={`${String((symbol.width + 20) * 0.6)}mm`} height="18mm" preserveAspectRatio="none">
                <rect x="0" y="0" width={symbol.width + 20} height="60" fill="#fff" />
                {symbol.bars.map((bar) => (
                    <rect key={bar.x} x={bar.x + 10} y="0" width={bar.width} height="60" fill="#000" />
                ))}
            </svg>
            <span className="font-mono text-body">{code}</span>
            <span className="text-caption">{product.name}</span>
        </div>,
        document.body,
    );
}

function Barcodes({ product, edit }: { product: Product; edit: boolean }) {
    const { t } = useTranslation();
    const [registering, setRegistering] = useState(false);
    const [label, setLabel] = useState<string | null>(null);
    const internal = useCommand<Payload>(() => `${LIST}/${product.id}/barcode`, 'post');
    const print = (code: string) => {
        setLabel(code);
        window.setTimeout(() => { window.print(); }, 0);
    };

    return (
        <FormSection title={t('products.barcodes.title')}>
            {product.barcodes.length === 0 ? (
                <p className="text-body text-text-secondary">{t('products.barcodes.empty')}</p>
            ) : (
                <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                    {product.barcodes.map((barcode) => (
                        <li key={barcode.code} className="flex min-w-0 flex-wrap items-center gap-3 py-2">
                            <span className="min-w-0 flex-1 font-mono text-body text-text-primary">{barcode.code}</span>
                            <Badge>{t(barcode.kind === 'INTERNAL' ? 'products.barcodes.internal' : 'products.barcodes.manufacturer')}</Badge>
                            {!barcode.active && <Badge>{t('products.archived_badge')}</Badge>}
                            {barcode.active && encodable(barcode.code) && (
                                <Button variant="outline" size="sm" onClick={() => { print(barcode.code); }}>
                                    <Icon icon={Printer} size="sm" />
                                    {t('products.barcodes.print')}
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            <CommandAnswer command={internal} />
            {edit && (
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" pending={internal.pending} disabled={internal.frozen} onClick={() => { internal.submit({ kind: 'INTERNAL' }); }}>
                        {t('products.barcodes.internal_create')}
                    </Button>
                    <Button variant="outline" onClick={() => { setRegistering(true); }}>
                        {t('products.barcodes.register')}
                    </Button>
                </div>
            )}
            {registering && (
                <FormSheet title={t('products.barcodes.register')} url={`${LIST}/${product.id}/barcode`} method="post" initial={{ code: '' }} fixed={{ kind: 'MANUFACTURER' }} submitLabel={t('products.barcodes.register')} onClose={() => { setRegistering(false); }}>
                    {(values, set, errors) => <TextField name="code" values={values} set={set} errors={errors} required />}
                </FormSheet>
            )}
            {label !== null && <Label product={product} code={label} />}
        </FormSection>
    );
}

function Images({ product, edit }: { product: Product; edit: boolean }) {
    const { t } = useTranslation();
    const [state, setState] = useState<UploadState>({ phase: 'idle' });
    const [file, setFile] = useState<string | null>(null);
    const abort = useRef<AbortController | null>(null);
    const last = useRef<File | null>(null);
    const attach = useCommand<Payload>(() => `${LIST}/${product.id}`, 'put');
    const arrange = useCommand<Payload>(() => `${LIST}/${product.id}`, 'put');
    const choose = (chosen: File) => {
        last.current = chosen;
        abort.current = new AbortController();
        setState({ phase: 'uploading', progress: 0 });
        void uploadFile('/berkas', chosen, 'file', (progress) => { setState({ phase: 'uploading', progress }); }, abort.current.signal, { purpose: 'gambar-produk' }).then((answer) => {
            if (answer.kind === 'stored') {
                setFile(answer.id);
                setState({ phase: 'done', name: answer.name });
            } else if (answer.kind === 'refused') {
                setState({ phase: 'error', message: answer.message });
            } else {
                setState(answer.kind === 'cancelled' ? { phase: 'idle' } : { phase: 'error', message: t('messages.msg_07') });
            }
        });
    };

    return (
        <FormSection title={t('products.images.title')}>
            {product.images.length === 0 ? (
                <p className="text-body text-text-secondary">{t('products.images.empty')}</p>
            ) : (
                <ul role="list" className="flex min-w-0 flex-wrap gap-3">
                    {product.images.map((image, index) => (
                        <li key={image.file} className="flex flex-col items-start gap-1">
                            <FileThumbnail fileId={image.file} alt={product.name} />
                            {image.primary && <Badge>{t('products.images.primary')}</Badge>}
                            {edit && (
                                <span className="flex flex-wrap gap-1 max-md:hidden">
                                    {!image.primary && (
                                        <Button variant="ghost" size="sm" disabled={arrange.frozen} onClick={() => { arrange.submit({ action: 'arrange', file: image.file, move: 'primary' }); }}>
                                            {t('products.images.make_primary')}
                                        </Button>
                                    )}
                                    {index > 0 && (
                                        <Button variant="ghost" size="sm" disabled={arrange.frozen} onClick={() => { arrange.submit({ action: 'arrange', file: image.file, move: 'earlier' }); }}>
                                            {t('products.images.earlier')}
                                        </Button>
                                    )}
                                    {index < product.images.length - 1 && (
                                        <Button variant="ghost" size="sm" disabled={arrange.frozen} onClick={() => { arrange.submit({ action: 'arrange', file: image.file, move: 'later' }); }}>
                                            {t('products.images.later')}
                                        </Button>
                                    )}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {edit && (
                <div className="flex min-w-0 flex-col gap-3 max-md:hidden">
                    <UploadField
                        label={t('products.images.add')}
                        accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                        limits={t('products.images.limits')}
                        state={state}
                        onChoose={choose}
                        onCancel={() => abort.current?.abort()}
                        onRemove={() => {
                            setFile(null);
                            setState({ phase: 'idle' });
                        }}
                        onRetry={() => { if (last.current !== null) choose(last.current); }}
                    />
                    <CommandAnswer command={attach} />
                    <CommandAnswer command={arrange} />
                    <div>
                        <Button variant="outline" disabled={file === null || attach.frozen} pending={attach.pending} onClick={() => { if (file !== null) attach.submit({ action: 'image', file }, () => { setFile(null); setState({ phase: 'idle' }); }); }}>
                            {t('products.images.attach')}
                        </Button>
                    </div>
                </div>
            )}
        </FormSection>
    );
}

function Prices({ product, editable }: { product: Product; editable: string[] }) {
    const { t } = useTranslation();
    const [changing, setChanging] = useState<string | null>(null);

    if (product.prices.length === 0) return null;

    return (
        <FormSection title={t('products.prices.title')}>
            <p className="text-body text-text-secondary">{t('products.prices.line')}</p>
            <div className="grid min-w-0 grid-cols-1 gap-6 md:grid-cols-3">
                {product.prices.map((price) => (
                    <div key={price.kind} className="flex min-w-0 flex-col gap-2" data-slot="price-default">
                        <p className="text-caption text-text-tertiary">{t(`products.prices.${price.kind.toLowerCase()}` as TranslationKey)}</p>
                        <p className="text-heading-sm text-text-primary">{price.history[0] === undefined ? '—' : <Money value={price.history[0].amount} />}</p>
                        {price.history.length > 0 && (
                            <ul role="list" aria-label={t('products.prices.history')} className="flex flex-col gap-1">
                                {price.history.map((row, index) => (
                                    <li key={`${row.date}-${String(index)}`} className="flex flex-wrap gap-2 text-caption text-text-secondary">
                                        <DateText value={row.date} />
                                        <Money value={row.amount} />
                                        {row.reason !== null && <span>{row.reason}</span>}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {editable.includes(price.kind) && product.active && (
                            <div>
                                <Button variant="outline" size="sm" onClick={() => { setChanging(price.kind); }}>
                                    {t('products.prices.change')}
                                </Button>
                            </div>
                        )}
                    </div>
                ))}
            </div>
            {changing !== null && (
                <FormSheet
                    title={t(`products.prices.${changing.toLowerCase()}` as TranslationKey)}
                    url={`${LIST}/${product.id}`}
                    method="put"
                    initial={{ amount: '', reason: '' }}
                    fixed={{ action: 'price', price_kind: changing }}
                    submitLabel={t('products.prices.change')}
                    onClose={() => { setChanging(null); }}
                >
                    {(values, set, errors) => (
                        <>
                            <p className="text-body text-text-secondary">{t('products.prices.forward')}</p>
                            <TextField name="amount" values={values} set={set} errors={errors} required />
                            <TextField name="reason" values={values} set={set} errors={errors} multiline />
                        </>
                    )}
                </FormSheet>
            )}
        </FormSection>
    );
}

function Detail({ product, can }: { product: Product; can: Props['can'] }) {
    const { t } = useTranslation();
    const [sheet, setSheet] = useState<'details' | 'policy' | 'unit' | null>(null);
    const [ratio, setRatio] = useState<Product['units'][number] | null>(null);
    const [archiving, setArchiving] = useState(false);
    const [reason, setReason] = useState('');
    const archive = useCommand<Payload>(() => `${LIST}/${product.id}`, 'put');
    const edit = can.edit && product.active;
    const base = product.base_unit ?? '';

    return (
        <div className="flex min-w-0 flex-col gap-6">
            <PageHeader
                title={product.name}
                facts={
                    <>
                        {product.sku !== null && <span className="font-mono">{product.sku}</span>}
                        <Badge>{t(product.kind === 'SERVICE' ? 'products.kind.service' : 'products.kind.goods')}</Badge>
                        {!product.active && <Badge>{t('products.archived_badge')}</Badge>}
                    </>
                }
                actions={edit ? [{ label: t('products.edit'), onSelect: () => { setSheet('details'); } }, { label: t('glossary.gl_058'), tone: 'danger', onSelect: () => { setArchiving(true); } }] : []}
            />
            <PageAnswer />
            {product.stock !== undefined && product.stock !== null && (
                <FormSection title={t('products.stock.title')}>
                    <dl className="grid min-w-0 grid-cols-2 gap-3 md:grid-cols-4">
                        {(['on_hand', 'reserved', 'unusable', 'available'] as const).map((figure) => (
                            <div key={figure} className="flex flex-col gap-1">
                                <dt className="text-caption text-text-tertiary">{t(`products.stock.${figure}` as TranslationKey)}</dt>
                                <dd className="text-body text-text-primary">
                                    <Quantity value={product.stock?.[figure] ?? '0'} unit={base} />
                                </dd>
                            </div>
                        ))}
                    </dl>
                </FormSection>
            )}
            <FormSection title={t('products.units.title')}>
                <ul role="list" className="flex min-w-0 flex-col gap-1">
                    {product.units.map((unit) => (
                        <li key={unit.code} className="flex min-w-0 flex-wrap items-center gap-2 text-body text-text-primary">
                            {unit.base ? t('products.units.base', { satuan: `${unit.code} · ${unit.name}` }) : t('products.units.alternate', { satuan: unit.code, jumlah: String(unit.ratio), dasar: base })}
                            {edit && !unit.base && (
                                <Button variant="ghost" size="sm" onClick={() => { setRatio(unit); }}>
                                    {t('products.units.change')}
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
                {product.units.length === 1 && <p className="text-body text-text-secondary">{t('products.units.empty')}</p>}
                {edit && product.kind === 'GOODS' && (
                    <div>
                        <Button variant="outline" onClick={() => { setSheet('unit'); }}>
                            <Icon icon={Plus} size="sm" />
                            {t('products.units.add')}
                        </Button>
                    </div>
                )}
            </FormSection>
            <Barcodes product={product} edit={edit} />
            <FormSection title={t('products.policy.title')}>
                <p className="text-body text-text-primary">
                    {t(product.serialized ? 'products.policy.serialized' : 'products.policy.not_serialized')} · {t(`products.scale.${String(product.quantity_scale)}` as TranslationKey)}
                </p>
                {edit && product.kind === 'GOODS' && (
                    <div>
                        <Button variant="outline" onClick={() => { setSheet('policy'); }}>
                            {t('products.policy.change')}
                        </Button>
                    </div>
                )}
            </FormSection>
            <Prices product={product} editable={edit ? can.prices : []} />
            <Images product={product} edit={edit} />
            {sheet === 'details' && (
                <FormSheet title={t('products.edit')} url={`${LIST}/${product.id}`} method="put" initial={{ name: product.name, sku: product.sku ?? '' }} fixed={{ action: 'details', lock_version: product.lock_version }} submitLabel={t('glossary.gl_157')} onClose={() => { setSheet(null); }}>
                    {(values, set, errors) => (
                        <>
                            <p className="text-body text-text-secondary">{t('products.issued_keep')}</p>
                            <TextField name="name" values={values} set={set} errors={errors} required />
                            <TextField name="sku" values={values} set={set} errors={errors} />
                        </>
                    )}
                </FormSheet>
            )}
            {ratio !== null && (
                <FormSheet
                    title={t('products.units.change')}
                    url={`${LIST}/${product.id}`}
                    method="put"
                    initial={{ ratio: String(product.units.find((unit) => unit.code === ratio.code)?.ratio ?? ratio.ratio) }}
                    fixed={{ action: 'ratio', unit_code: ratio.code, lock_version: product.units.find((unit) => unit.code === ratio.code)?.lock_version ?? ratio.lock_version }}
                    submitLabel={t('products.units.change')}
                    onClose={() => { setRatio(null); }}
                >
                    {(values, set, errors) => (
                        <>
                            <p className="text-body text-text-secondary">{t('products.units.change_line')}</p>
                            <TextField name="ratio" values={values} set={set} errors={errors} required />
                        </>
                    )}
                </FormSheet>
            )}
            {sheet === 'unit' && (
                <FormSheet title={t('products.units.add')} url={`${LIST}/${product.id}`} method="put" initial={{ unit_code: '', unit_name: '', ratio: '' }} fixed={{ action: 'unit' }} submitLabel={t('products.units.add')} onClose={() => { setSheet(null); }}>
                    {(values, set, errors) => (
                        <>
                            <p className="text-body text-text-secondary">{t('products.units.line', { dasar: base })}</p>
                            <TextField name="unit_code" values={values} set={set} errors={errors} required />
                            <TextField name="unit_name" values={values} set={set} errors={errors} />
                            <TextField name="ratio" values={values} set={set} errors={errors} required />
                        </>
                    )}
                </FormSheet>
            )}
            {sheet === 'policy' && (
                <FormSheet
                    title={t('products.policy.change')}
                    url={`${LIST}/${product.id}`}
                    method="put"
                    initial={{ serialized: product.serialized ? '1' : '0', quantity_scale: String(product.quantity_scale) }}
                    fixed={{ action: 'policy', lock_version: product.lock_version }}
                    submitLabel={t('glossary.gl_157')}
                    onClose={() => { setSheet(null); }}
                >
                    {(values, set, errors) => (
                        <>
                            <p className="text-body text-text-secondary">{t('products.policy.line')}</p>
                            <div className="flex items-center gap-2">
                                <Checkbox id="product-serialized" checked={values.serialized === '1'} onCheckedChange={(checked) => { set('serialized', checked ? '1' : '0'); if (checked) set('quantity_scale', '0'); }} />
                                <label htmlFor="product-serialized" className="text-body text-text-primary">
                                    {t('products.policy.serialized')}
                                </label>
                            </div>
                            {values.serialized !== '1' && <ScaleField values={values} set={set} errors={errors} />}
                        </>
                    )}
                </FormSheet>
            )}
            <ConfirmCommand
                open={archiving}
                onOpenChange={setArchiving}
                title={t('products.archive_title')}
                consequence={t('products.archive_line')}
                reason={{ value: reason, onChange: setReason, error: archive.errors.reason }}
                confirmLabel={t('glossary.gl_058')}
                destructive
                pending={archive.pending}
                answer={archive.answer}
                onConfirm={() => { archive.submit({ action: 'archive', lock_version: product.lock_version, reason }, () => { setArchiving(false); }); }}
            />
        </div>
    );
}

export default function Products({ list, product, can }: Props) {
    const { t } = useTranslation();
    const [creating, setCreating] = useState<{ name: string; key: number } | null>(null);
    const found = usePartySearch('product');
    // PT-08: "Muat Lagi" asks for the page after the rows shown on a partial reload and adds it below them.
    const [base, setBase] = useState(list);
    const [shown, setShown] = useState(list.rows);
    const [appending, setAppending] = useState(false);

    if (base !== list) {
        setBase(list);
        setShown(appending ? [...shown, ...list.rows.filter((row) => !shown.some((known) => known.id === row.id))] : list.rows);
        setAppending(false);
    }

    const create = (name: string) => { setCreating({ name, key: Date.now() }); };
    const createAction = can.edit ? (
        <Button onClick={() => { create(''); }}>
            <Icon icon={Plus} size="sm" />
            {t('products.create')}
        </Button>
    ) : undefined;

    return (
        <AppShell title={product?.name ?? t('pages.pg_056.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <DataMasterNav current="products" />
                {product === null ? (
                    <>
                        <PageHeader title={t('pages.pg_056.title')} primary={createAction} />
                        <PageAnswer />
                        <div className="min-w-0 md:max-w-xl">
                            <Lookup
                                label={t('products.search')}
                                placeholder={t('products.search')}
                                term={found.term}
                                onTermChange={found.find}
                                groups={found.rows.length === 0 ? [] : [{ value: 'product', label: t('pages.pg_056.title'), items: found.rows.map((row) => ({ id: row.id, code: row.code ?? '', label: row.label })) }]}
                                cut={found.cut}
                                loading={found.loading}
                                value={null}
                                onChoose={(chosen) => { if (chosen !== null) visit(chosen.id); }}
                                {...(can.edit ? { create: { object: t('products.object'), onCreate: () => { create(found.term.trim()); } } } : {})}
                            />
                        </div>
                        {shown.length === 0 ? (
                            <RegionEmpty text={t('products.empty')} action={createAction} />
                        ) : (
                            <>
                                <DataList<Listed>
                                    caption={t('pages.pg_056.title')}
                                    rows={shown}
                                    rowKey={(row) => row.id}
                                    rowHref={(row) => `${LIST}?produk=${row.id}`}
                                    columns={[
                                        { key: 'image', header: t('products.images.title'), cell: (row) => (row.thumbnail === null ? <span className="text-text-tertiary">—</span> : <FileThumbnail fileId={row.thumbnail} alt={row.name} />) },
                                        {
                                            key: 'name',
                                            header: t('products.field.name'),
                                            cell: (row) => (
                                                <span className="flex min-w-0 items-center gap-2">
                                                    <span className="min-w-0 truncate">{row.name}</span>
                                                    {!row.active && <Badge>{t('products.archived_badge')}</Badge>}
                                                </span>
                                            ),
                                        },
                                        { key: 'sku', header: t('products.field.sku'), cell: (row) => <span className="font-mono">{row.sku ?? '—'}</span> },
                                        { key: 'kind', header: t('products.field.kind'), priority: 'low', cell: (row) => t(row.kind === 'SERVICE' ? 'products.kind.service' : 'products.kind.goods') },
                                    ]}
                                    dense={(row) => ({ title: row.name, secondary: row.sku ?? undefined, ...(row.active ? {} : { status: <Badge>{t('products.archived_badge')}</Badge> }) })}
                                />
                                <ListContinuation
                                    state={base.more === null ? 'end' : 'more'}
                                    loading={appending}
                                    onLoadMore={() => {
                                        setAppending(true);
                                        router.reload({ only: ['list'], data: { setelah: base.more }, preserveUrl: true });
                                    }}
                                    onReload={() => { router.reload(); }}
                                />
                            </>
                        )}
                    </>
                ) : (
                    <>
                        <div>
                            <Button variant="ghost" size="sm" onClick={() => { visit(null); }}>
                                {t('products.back')}
                            </Button>
                        </div>
                        <Detail key={product.id} product={product} can={can} />
                    </>
                )}
            </div>
            {creating !== null && (
                <FormSheet
                    key={creating.key}
                    title={t('products.create')}
                    url={LIST}
                    method="post"
                    initial={{ kind: 'GOODS', name: creating.name, sku: '', unit_code: '', unit_name: '', quantity_scale: '0' }}
                    fixed={{}}
                    submitLabel={t('products.create')}
                    onClose={() => { setCreating(null); }}
                >
                    {(values, set, errors) => (
                        <>
                            <FormField label={t('products.field.kind')} required error={errors.kind}>
                                {(control) => (
                                    <NativeSelect {...control} value={values.kind} onChange={(event) => { set('kind', event.target.value); if (event.target.value === 'SERVICE') set('quantity_scale', '0'); }}>
                                        <option value="GOODS">{t('products.kind.goods')}</option>
                                        <option value="SERVICE">{t('products.kind.service')}</option>
                                    </NativeSelect>
                                )}
                            </FormField>
                            <TextField name="name" values={values} set={set} errors={errors} required />
                            <TextField name="sku" values={values} set={set} errors={errors} />
                            <TextField name="unit_code" values={values} set={set} errors={errors} required />
                            <TextField name="unit_name" values={values} set={set} errors={errors} />
                            {values.kind === 'GOODS' && <ScaleField values={values} set={set} errors={errors} />}
                        </>
                    )}
                </FormSheet>
            )}
        </AppShell>
    );
}
