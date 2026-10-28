// PG-057 *Lokasi Rak* (ROUTE-057, ROUTE-148; NAV-057; SCR-53; IA §8.6; J-MD-05; WF-MD-05; TASK-028): `stock.view`'s page of
// the shared warehouse's racks — pool records of no company, so no company label applies (CS-05) — and the products that
// carry a minimum stock in base units; `warehouse.manage` adds a rack, edits its name and active state, and sets or clears
// a product's minimum, which only advises restocking and never creates a purchase (BR-INV-10). `[desk]`.
import { usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

import { FormField, FormSection } from '@/components/form';
import { FormSheet } from '@/components/form-sheet';
import { Lookup, type LookupResult } from '@/components/lookup';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { usePartySearch } from '@/components/parties';
import { DataMasterNav } from '@/components/party-nav';
import { RegionEmpty } from '@/components/region-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { Quantity } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';

type Rack = { code: string; name: string; active: boolean; lock_version: number };
type Minimum = { id: string; sku: string | null; name: string; minimum: string; unit: string; lock_version: number };
type Props = { locations: { rows: Rack[]; cut: boolean }; minimums: { rows: Minimum[]; cut: boolean }; can: { manage: boolean } };
type Values = Record<string, string>;
type Sheet = { kind: 'create' } | { kind: 'edit'; rack: Rack } | { kind: 'minimum'; product: { id: string; label: string; minimum: string; lock_version: number | null } };

const PAGE = '/data-master/lokasi-rak';

/** A command's success on the page its answer opened, once (IP-01). */
function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

function Field({ name, values, set, errors, required = false, help }: { name: string; values: Values; set: (field: string, value: string) => void; errors: Record<string, string>; required?: boolean; help?: string }) {
    const { t } = useTranslation();

    return (
        <FormField label={t(`locations.field.${name}` as TranslationKey)} required={required} help={help} error={errors[name]}>
            {(control) => <Input {...control} value={values[name] ?? ''} inputMode={name === 'minimum' ? 'decimal' : undefined} onChange={(event) => { set(name, event.target.value); }} />}
        </FormField>
    );
}

/** A product chosen by ROUTE-281's lookup before its minimum is set. A product listed with its minimum is changed at the
    version the page read; one chosen here is sent without a version, set only while it still has none — otherwise the
    command answers CONFLICT, never overwriting a minimum the page did not show (ST-01). */
function ProductPicker({ onChoose }: { onChoose: (product: LookupResult) => void }) {
    const { t } = useTranslation();
    const found = usePartySearch('product');

    return (
        <Lookup
            label={t('locations.field.product')}
            placeholder={t('locations.field.product')}
            term={found.term}
            onTermChange={found.find}
            groups={found.rows.length === 0 ? [] : [{ value: 'product', label: t('pages.pg_056.title'), items: found.rows.map((row) => ({ id: row.id, code: row.code ?? '', label: row.label })) }]}
            cut={found.cut}
            loading={found.loading}
            value={null}
            onChoose={(chosen) => { if (chosen !== null) onChoose(chosen); }}
        />
    );
}

export default function Locations({ locations, minimums, can }: Props) {
    const { t } = useTranslation();
    const [sheet, setSheet] = useState<Sheet | null>(null);
    const [picking, setPicking] = useState(false);
    const close = () => { setSheet(null); };
    const createAction = can.manage ? (
        <Button onClick={() => { setSheet({ kind: 'create' }); }}>
            <Icon icon={Plus} size="sm" />
            {t('locations.create')}
        </Button>
    ) : undefined;

    return (
        <AppShell title={t('pages.pg_057.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <DataMasterNav current="locations" />
                <PageHeader title={t('pages.pg_057.title')} primary={createAction} />
                <PageAnswer />
                <FormSection title={t('locations.racks')}>
                    <p className="text-body text-text-secondary">{t('locations.racks_line')}</p>
                    {locations.rows.length === 0 ? (
                        <RegionEmpty text={t('locations.empty')} action={createAction} />
                    ) : (
                        <ul role="list" aria-label={t('locations.racks')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {locations.rows.map((rack) => (
                                <li key={rack.code} className="flex min-w-0 flex-wrap items-center gap-3 py-2">
                                    <span className="font-mono font-medium text-text-primary">{rack.code}</span>
                                    <span className="min-w-0 flex-1 truncate text-body text-text-primary">{rack.name}</span>
                                    {!rack.active && <Badge>{t('locations.inactive')}</Badge>}
                                    {can.manage && (
                                        <Button variant="outline" size="sm" onClick={() => { setSheet({ kind: 'edit', rack }); }}>
                                            {t('locations.edit')}
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                    {locations.cut && <p className="text-caption text-text-secondary">{t('locations.cut')}</p>}
                </FormSection>
                <FormSection title={t('locations.minimums')}>
                    <p className="text-body text-text-secondary">{t('locations.minimums_line')}</p>
                    {minimums.rows.length === 0 ? (
                        <p className="text-body text-text-secondary">{t('locations.minimums_empty')}</p>
                    ) : (
                        <ul role="list" aria-label={t('locations.minimums')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {minimums.rows.map((product) => (
                                <li key={product.id} className="flex min-w-0 flex-wrap items-center gap-3 py-2">
                                    <span className="min-w-0 flex-1 truncate text-body text-text-primary">{product.name}</span>
                                    {product.sku !== null && <span className="font-mono text-caption text-text-secondary">{product.sku}</span>}
                                    <Quantity value={product.minimum} unit={product.unit} />
                                    {can.manage && (
                                        <Button variant="outline" size="sm" onClick={() => { setSheet({ kind: 'minimum', product: { id: product.id, label: product.name, minimum: product.minimum, lock_version: product.lock_version } }); }}>
                                            {t('locations.minimum_set')}
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                    {minimums.cut && <p className="text-caption text-text-secondary">{t('locations.cut')}</p>}
                    {can.manage && (
                        <div className="flex min-w-0 flex-col gap-2 md:max-w-xl">
                            {picking ? (
                                <ProductPicker onChoose={(chosen) => { setPicking(false); setSheet({ kind: 'minimum', product: { id: chosen.id, label: chosen.label, minimum: '', lock_version: null } }); }} />
                            ) : (
                                <div>
                                    <Button variant="outline" onClick={() => { setPicking(true); }}>
                                        {t('locations.minimum_set')}
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}
                </FormSection>
            </div>
            {sheet?.kind === 'create' && (
                <FormSheet title={t('locations.create')} url={PAGE} method="post" initial={{ code: '', name: '' }} fixed={{ kind: 'create' }} submitLabel={t('locations.create')} onClose={close}>
                    {(values, set, errors) => (
                        <>
                            <Field name="code" values={values} set={set} errors={errors} required help={t('locations.field.code_help')} />
                            <Field name="name" values={values} set={set} errors={errors} required />
                        </>
                    )}
                </FormSheet>
            )}
            {sheet?.kind === 'edit' && (
                <FormSheet
                    title={`${t('locations.edit')} ${sheet.rack.code}`}
                    url={PAGE}
                    method="post"
                    initial={{ name: sheet.rack.name, active: sheet.rack.active ? '1' : '0' }}
                    fixed={{ kind: 'edit', code: sheet.rack.code, lock_version: locations.rows.find((rack) => rack.code === sheet.rack.code)?.lock_version ?? sheet.rack.lock_version }}
                    submitLabel={t('glossary.gl_157')}
                    onClose={close}
                >
                    {(values, set, errors) => (
                        <>
                            <Field name="name" values={values} set={set} errors={errors} required />
                            <div className="flex items-center gap-2">
                                <Checkbox id="rack-active" checked={values.active === '1'} onCheckedChange={(checked) => { set('active', checked ? '1' : '0'); }} />
                                <label htmlFor="rack-active" className="text-body text-text-primary">
                                    {t('locations.field.active')}
                                </label>
                            </div>
                        </>
                    )}
                </FormSheet>
            )}
            {sheet?.kind === 'minimum' && (
                <FormSheet
                    title={`${t('locations.minimum_set')} — ${sheet.product.label}`}
                    url={PAGE}
                    method="post"
                    initial={{ minimum: sheet.product.minimum }}
                    fixed={{ kind: 'minimum', product: sheet.product.id, lock_version: minimums.rows.find((row) => row.id === sheet.product.id)?.lock_version ?? sheet.product.lock_version }}
                    submitLabel={t('locations.minimum_set')}
                    onClose={close}
                >
                    {(values, set, errors) => <Field name="minimum" values={values} set={set} errors={errors} help={t('locations.field.minimum_help')} />}
                </FormSheet>
            )}
        </AppShell>
    );
}
