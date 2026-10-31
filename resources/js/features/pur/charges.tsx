// A purchase's charges under "Rincian lain" (SF-CHARGE; DIR-024 D-2; L-43; DESIGN_SYSTEM Annex A.3) and PG-016's "Catat Biaya Susulan": the source bill with its issuer, reference and what it still may carry; *Masuk biaya perolehan* with an allocation basis, or *Beban*. A weight or an explicit split asks a value per line, in line order.
import { Trash2 } from 'lucide-react';

import { DecimalInput, FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { NativeSelect } from '@/components/ui/native-select';
import { money } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';

export type Bill = { id: string; issuer: string; reference: string | null; date: string; total: string; left: string };
export type Charge = { key: string; bill: string; classification: 'ATTRIBUTABLE' | 'NON_ATTRIBUTABLE'; amount: string; basis: string; values: string[] };

export const BASES = ['LINE_VALUE', 'QUANTITY', 'WEIGHT', 'EXPLICIT'] as const;

export const blankCharge = (key: string, bill: string): Charge => ({ key, bill, classification: 'ATTRIBUTABLE', amount: '', basis: 'LINE_VALUE', values: [] });

export const chargePayload = (charge: Charge) => ({
    tagihan: charge.bill,
    klasifikasi: charge.classification,
    jumlah: charge.amount,
    dasar: charge.classification === 'ATTRIBUTABLE' ? charge.basis : null,
    nilai_dasar: charge.classification === 'ATTRIBUTABLE' && (charge.basis === 'WEIGHT' || charge.basis === 'EXPLICIT') ? charge.values : [],
});

export const billLabel = (bill: Bill): string => `${bill.issuer}${bill.reference === null ? '' : ` · ${bill.reference}`} · ${money(bill.left)}`;

export function BasisValues({ charge, lines, onChange }: { charge: Charge; lines: { key: string; label: string }[]; onChange: (values: string[]) => void }) {
    const { t } = useTranslation();

    return (
        <div className="flex min-w-0 flex-col gap-2">
            {lines.map((line, index) => (
                <FormField key={line.key} label={line.label} className="sm:w-80">
                    {(control) => <DecimalInput {...control} aria-label={`${t('purchases.charge.basis_value')} ${line.label}`} scale={3} value={charge.values[index] ?? ''} onValueChange={(value) => {
                        const next = lines.map((_, at) => charge.values[at] ?? '');
                        next[index] = value ?? '';
                        onChange(next);
                    }} />}
                </FormField>
            ))}
        </div>
    );
}

export function ChargeFields({ charge, bills, lines, onChange, onRemove, onUpload, error }: {
    charge: Charge;
    bills: Bill[];
    lines: { key: string; label: string }[];
    onChange: (patch: Partial<Charge>) => void;
    onRemove?: () => void;
    onUpload?: () => void;
    error: (field: string) => string | undefined;
}) {
    const { t } = useTranslation();

    return (
        <div data-slot="purchase-charge" className="flex min-w-0 flex-col gap-3 rounded-md border border-border-subtle p-3">
            <div className="flex min-w-0 flex-wrap items-end gap-4">
                <FormField label={t('pages.pg_017.source_bill')} required error={error('tagihan')} className="min-w-0 sm:w-96">
                    {(control) => (
                        <NativeSelect {...control} value={charge.bill} onChange={(event) => { onChange({ bill: event.target.value }); }}>
                            <option value="">{t('purchases.charge.choose_bill')}</option>
                            {bills.map((bill) => <option key={bill.id} value={bill.id}>{billLabel(bill)}</option>)}
                        </NativeSelect>
                    )}
                </FormField>
                {onUpload !== undefined && <Button variant="outline" onClick={onUpload}>{t('purchases.charge.upload_bill')}</Button>}
                <FormField label={t('pages.pg_017.charge_amount')} required error={error('jumlah')} className="sm:w-44">
                    {(control) => <DecimalInput {...control} kind="money" scale={2} value={charge.amount} onValueChange={(value) => { onChange({ amount: value ?? '' }); }} />}
                </FormField>
                <FormField label={t('pages.pg_017.classification')} className="sm:w-56">
                    {(control) => (
                        <NativeSelect {...control} value={charge.classification} onChange={(event) => { onChange({ classification: event.target.value as Charge['classification'] }); }}>
                            <option value="ATTRIBUTABLE">{t('pages.pg_017.class_attributable')}</option>
                            <option value="NON_ATTRIBUTABLE">{t('pages.pg_017.class_expense')}</option>
                        </NativeSelect>
                    )}
                </FormField>
                {charge.classification === 'ATTRIBUTABLE' && (
                    <FormField label={t('purchases.charge.basis')} error={error('dasar')} className="sm:w-56">
                        {(control) => (
                            <NativeSelect {...control} value={charge.basis} onChange={(event) => { onChange({ basis: event.target.value }); }}>
                                {BASES.map((basis) => <option key={basis} value={basis}>{t(`purchases.basis.${basis}` as TranslationKey)}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                )}
                {onRemove !== undefined && (
                    <Button variant="ghost" aria-label={t('purchases.charge.remove')} onClick={onRemove}>
                        <Icon icon={Trash2} size="sm" />
                    </Button>
                )}
            </div>
            {charge.classification === 'ATTRIBUTABLE' && (charge.basis === 'WEIGHT' || charge.basis === 'EXPLICIT') && <BasisValues charge={charge} lines={lines} onChange={(values) => { onChange({ values }); }} />}
        </div>
    );
}
