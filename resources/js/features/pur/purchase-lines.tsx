// PG-017's and PG-016's line editor (PT-10; PT-27; UXS-35; TASK-037): product, quantity, entered unit, actual price and tax components, thirty lines by keyboard on the desktop, dense rows on the phone. A goods line may name its project's demand line and "Kirim langsung ke klien"; a non-stock line needs a project (L-18). The value is the server's: *Perkiraan* until saved (UXI-14). SH-01's previous price sits beside the price, no score or rank.
import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { type ReactNode } from 'react';

import { DecimalInput, FormField } from '@/components/form';
import { LineEditor } from '@/components/line-editor';
import { Lookup, type LookupResult } from '@/components/lookup';
import { usePartySearch } from '@/components/parties';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Money } from '@/components/values';
import * as decimal from '@/lib/decimal';
import { dateLong, money } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

export type Product = { id: string; name: string; sku: string | null; kind: 'GOODS' | 'SERVICE'; scale: number; units: { code: string; name: string; ratio: number }[] };
export type Tax = { code: string; name: string; treatment: string; rate: string };
export type Demand = { handle: string; label: string; product: string; no: number; shortage: string };
export type Previous = Record<string, { price: string; date: string; unit: string; purchase: string }>;
export type LineTax = { code: string; rate: string; amount: string };
export type Line = {
    key: string;
    handle: string | null;
    kind: 'GOODS' | 'NONSTOCK';
    term: string;
    product: Product | null;
    quantity: string | null;
    unit: string;
    price: string | null;
    direct: boolean;
    demand: string;
    description: string;
    taxes: LineTax[];
};

export const blankLine = (key: string): Line => ({ key, handle: null, kind: 'GOODS', term: '', product: null, quantity: null, unit: '', price: null, direct: false, demand: '', description: '', taxes: [] });

export const lineValue = (line: Line): string | null => (line.quantity === null || line.price === null ? null : decimal.round(decimal.multiply(line.quantity, line.price), 2));

export const estimatedTotal = (lines: Line[]): string => decimal.round(lines.reduce((sum, line) => decimal.add(sum, lineValue(line) ?? '0'), '0'), 2);

/** The request's lines (ROUTE-171; ROUTE-172). */
export const linePayload = (lines: Line[]) => lines.map((line) => ({
    handle: line.handle,
    jenis: line.kind,
    produk: line.product?.id ?? '',
    satuan: line.unit,
    jumlah: line.quantity ?? '',
    harga: line.price ?? '',
    langsung: line.direct,
    item_proyek: line.demand === '' ? null : line.demand,
    uraian: line.product !== null && line.description === line.product.name ? '' : line.description,
    pajak: line.taxes.map((tax) => ({ kode: tax.code, tarif: tax.rate, nilai: tax.amount })),
}));

function Cell({ error, children }: { error: string | undefined; children: ReactNode }) {
    return (
        <div className="flex min-w-0 flex-col gap-1">
            {children}
            {error !== undefined && <p className="text-caption text-danger-fg">{error}</p>}
        </div>
    );
}

function ProductCell({ line, error, onTerm, onChoose }: { line: Line; error: string | undefined; onTerm: (term: string) => void; onChoose: (next: LookupResult | null) => void }) {
    const { t } = useTranslation();
    const found = usePartySearch('product');

    return (
        <Cell error={error}>
            <Lookup
                label={t('pt10.columns.product')}
                term={found.term === '' ? line.term : found.term}
                onTermChange={(term) => { found.find(term); onTerm(term); }}
                loading={found.loading}
                cut={found.cut}
                groups={found.rows.length === 0 ? [] : [{ value: 'product', label: t('pt10.columns.product'), items: found.rows.map((row) => ({ id: row.id, code: row.code ?? '', label: row.label })) }]}
                value={line.product === null ? null : { id: line.product.id, code: line.product.sku ?? '', label: line.product.name }}
                onChoose={onChoose}
            />
        </Cell>
    );
}

/** The product arrives by a partial reload of the page's `product` prop; the line that named it takes its units. */
export function useLineProducts(setLines: (update: (current: Line[]) => Line[]) => void) {
    return (key: string, next: LookupResult | null) => {
        if (next === null) {
            setLines((current) => current.map((line) => (line.key === key ? { ...line, product: null, unit: '', demand: '' } : line)));

            return;
        }

        router.reload({
            only: ['product'],
            data: { produk: next.id },
            preserveUrl: true,
            onSuccess: (visited) => {
                const found = (visited.props as unknown as { product: Product | null }).product;

                if (found === null) return;
                setLines((current) => current.map((line) => (line.key === key ? { ...line, product: found, unit: found.units[0]?.code ?? '', kind: found.kind === 'SERVICE' ? 'NONSTOCK' : line.kind, direct: found.kind === 'SERVICE' ? false : line.direct, demand: '', description: found.name } : line)));
            },
        });
    };
}

export function PurchaseLines({ lines, setLines, onTouch, chooseProduct, error, taxes, demand, previous, nonStock, canAddNonStock = true }: {
    lines: Line[];
    setLines: (update: (current: Line[]) => Line[]) => void;
    onTouch: () => void;
    chooseProduct: (key: string, next: LookupResult | null) => void;
    error: (index: number, field: string) => string | undefined;
    taxes: Tax[];
    demand: Demand[];
    previous: Previous;
    nonStock: boolean;
    canAddNonStock?: boolean;
}) {
    const { t } = useTranslation();
    const taxName = (code: string) => taxes.find((tax) => tax.code === code)?.name ?? code;

    return (
        <LineEditor<Line>
            label={t('purchase.lines')}
            lines={lines}
            onChange={(next) => { setLines(() => next); onTouch(); }}
            blank={blankLine}
            isBlank={(line) => line.product === null && line.quantity === null && line.term === ''}
            columns={[
                {
                    key: 'product',
                    header: t('pt10.columns.product'),
                    className: 'min-w-64',
                    cell: (line, update, index) => (
                        <ProductCell
                            line={line}
                            error={error(index, 'produk')}
                            onTerm={(term) => {
                                if (lines.some((other) => other.key === line.key)) {
                                    setLines((current) => current.map((other) => (other.key === line.key ? { ...other, term } : other)));
                                    onTouch();
                                } else {
                                    update({ term });
                                }
                            }}
                            onChoose={(next) => {
                                if (!lines.some((other) => other.key === line.key)) update({ term: next?.label ?? '' });
                                chooseProduct(line.key, next);
                            }}
                        />
                    ),
                },
                {
                    key: 'quantity',
                    header: t('pt10.columns.quantity'),
                    numeric: true,
                    className: 'w-28',
                    cell: (line, update, index) => (
                        <Cell error={error(index, 'jumlah')}>
                            <DecimalInput aria-label={t('pt10.columns.quantity')} scale={3} value={line.quantity ?? ''} aria-invalid={error(index, 'jumlah') !== undefined ? true : undefined} onValueChange={(value) => { update({ quantity: value }); }} />
                        </Cell>
                    ),
                },
                {
                    key: 'unit',
                    header: t('pt10.columns.unit'),
                    className: 'w-28',
                    cell: (line, update, index) => (
                        <Cell error={error(index, 'satuan')}>
                            <NativeSelect aria-label={t('pt10.columns.unit')} value={line.unit} disabled={line.product === null} onChange={(event) => { update({ unit: event.target.value }); }}>
                                {(line.product?.units ?? []).map((option) => <option key={option.code} value={option.code}>{option.code}</option>)}
                            </NativeSelect>
                        </Cell>
                    ),
                },
                {
                    key: 'price',
                    header: t('pt10.columns.price'),
                    numeric: true,
                    className: 'w-40',
                    cell: (line, update, index) => {
                        const before = line.product === null ? undefined : previous[line.product.id];

                        return (
                            <Cell error={error(index, 'harga')}>
                                <DecimalInput aria-label={t('pt10.columns.price')} kind="money" scale={2} value={line.price ?? ''} aria-invalid={error(index, 'harga') !== undefined ? true : undefined} onValueChange={(value) => { update({ price: value }); }} />
                                {before !== undefined && <p className="text-caption text-text-secondary">{t('purchases.line.previous', { harga: money(before.price), tanggal: dateLong(before.date) })}</p>}
                            </Cell>
                        );
                    },
                },
                {
                    key: 'subtotal',
                    header: t('pt10.columns.subtotal'),
                    numeric: true,
                    className: 'w-36',
                    cell: (line) => {
                        const value = lineValue(line);

                        return value === null ? '' : <Money value={value} />;
                    },
                },
            ]}
            summary={(line) => ({
                title: line.product?.name ?? t('purchases.line.new'),
                secondary: line.quantity === null ? undefined : `${line.quantity} ${line.unit}${line.kind === 'NONSTOCK' ? ` · ${t('purchases.line.nonstock')}` : ''}${line.direct ? ` · ${t('purchases.line.direct')}` : ''}`,
                ...(lineValue(line) !== null ? { figure: <Money value={lineValue(line) ?? '0'} /> } : {}),
            })}
            details={(line, update) => (
                <div className="flex min-w-0 flex-col gap-4">
                    <FormField label={t('purchases.line.description')}>
                        {(control) => <Input {...control} value={line.description} maxLength={500} onChange={(event) => { update({ description: event.target.value }); }} />}
                    </FormField>
                    {nonStock && canAddNonStock && line.handle === null && (
                        <label className="flex items-center gap-2 text-body">
                            <Checkbox checked={line.kind === 'NONSTOCK'} disabled={line.product?.kind === 'SERVICE'} onCheckedChange={(checked) => { update({ kind: checked ? 'NONSTOCK' : 'GOODS', direct: checked ? false : line.direct }); }} />
                            {t('purchases.line.nonstock_toggle')}
                        </label>
                    )}
                    {line.kind === 'GOODS' && nonStock && (
                        <label className="flex items-center gap-2 text-body">
                            <Checkbox checked={line.direct} onCheckedChange={(checked) => { update({ direct: checked }); }} />
                            {t('purchases.line.direct')}
                        </label>
                    )}
                    {demand.length > 0 && (
                        <FormField label={t('purchases.line.demand')}>
                            {(control) => (
                                <NativeSelect {...control} value={line.demand} onChange={(event) => { update({ demand: event.target.value }); }}>
                                    <option value="">{t('purchases.line.no_demand')}</option>
                                    {demand.filter((option) => line.product === null || option.product === line.product.id).map((option) => <option key={option.handle} value={option.handle}>{`${String(option.no)}. ${option.label}`}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}
                    <fieldset className="flex min-w-0 flex-col gap-2">
                        <legend className="text-body font-medium">{t('pt10.columns.tax')}</legend>
                        {line.taxes.map((tax, index) => (
                            <div key={tax.code} className="flex min-w-0 flex-wrap items-end gap-2">
                                <span className="min-w-32 flex-1 text-body">{taxName(tax.code)}</span>
                                <FormField label={t('purchases.line.rate')} className="w-24">
                                    {(control) => <DecimalInput {...control} scale={6} value={tax.rate} onValueChange={(value) => { update({ taxes: line.taxes.map((other, at) => (at === index ? { ...other, rate: value ?? '' } : other)) }); }} />}
                                </FormField>
                                <FormField label={t('purchases.line.tax_amount')} className="w-36">
                                    {(control) => <DecimalInput {...control} kind="money" scale={2} value={tax.amount} onValueChange={(value) => { update({ taxes: line.taxes.map((other, at) => (at === index ? { ...other, amount: value ?? '' } : other)) }); }} />}
                                </FormField>
                                <Button variant="ghost" size="sm" aria-label={t('purchases.line.remove_tax')} onClick={() => { update({ taxes: line.taxes.filter((_, at) => at !== index) }); }}>
                                    <Icon icon={Trash2} size="sm" />
                                </Button>
                            </div>
                        ))}
                        {taxes.filter((tax) => !line.taxes.some((own) => own.code === tax.code)).length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {taxes.filter((tax) => !line.taxes.some((own) => own.code === tax.code)).map((tax) => (
                                    <Button key={tax.code} variant="outline" size="sm" onClick={() => {
                                        const base = lineValue(line) ?? '0';

                                        update({ taxes: [...line.taxes, { code: tax.code, rate: tax.rate, amount: decimal.quotient(decimal.multiply(base, tax.rate), '100', 2, 'HALF_UP') }] });
                                    }}>
                                        <Icon icon={Plus} size="sm" />
                                        {tax.name}
                                    </Button>
                                ))}
                            </div>
                        )}
                    </fieldset>
                </div>
            )}
        />
    );
}
