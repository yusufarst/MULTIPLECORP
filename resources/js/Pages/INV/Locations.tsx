// PG-057 *Lokasi Rak* (ROUTE-057, ROUTE-148; NAV-057; SCR-53; IA §8.6; J-MD-05; WF-MD-05; TASK-028): `stock.view`'s page of
// the shared warehouse's racks — pool records of no company, so no company label applies (CS-05) — and the products that
// carry a minimum stock in base units, each list continued by "Muat Lagi" (PT-08); `warehouse.manage` adds a rack, edits its
// name and active state, and sets or clears a product's minimum, which only advises restocking and never creates a
// purchase (BR-INV-10). A product the lookup chooses is loaded by its address key with its current minimum and version,
// and every sheet reads its values and version from the same latest record (ST-01; IP-03). `[desk]`.
import { router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

import { ListContinuation } from '@/components/data-list';
import { FormField, FormSection } from '@/components/form';
import { FormSheet } from '@/components/form-sheet';
import { Lookup } from '@/components/lookup';
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
type Picked = Omit<Minimum, 'minimum'> & { minimum: string | null };
type Props = { locations: { rows: Rack[]; more: string | null }; minimums: { rows: Minimum[]; more: string | null }; picked: Picked | null; can: { manage: boolean } };
type Values = Record<string, string>;
type Sheet = { kind: 'create' } | { kind: 'edit'; code: string } | { kind: 'minimum'; id: string };

const PAGE = '/data-master/lokasi-rak';

/** A command's success on the page its answer opened, once (IP-01). */
function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

/** PT-08: the rows shown, a partial reload asking for the page after the last and adding it below them. */
function useContinued<Row>(list: { rows: Row[]; more: string | null }, prop: 'locations' | 'minimums', cursor: string, key: (row: Row) => string) {
    const [base, setBase] = useState(list);
    const [shown, setShown] = useState(list.rows);
    const [appending, setAppending] = useState(false);

    if (base !== list) {
        setBase(list);
        setShown(appending ? [...shown, ...list.rows.filter((row) => !shown.some((known) => key(known) === key(row)))] : list.rows);
        setAppending(false);
    }

    return {
        shown,
        continuation: (
            <ListContinuation
                state={base.more === null ? 'end' : 'more'}
                loading={appending}
                onLoadMore={() => {
                    setAppending(true);
                    router.reload({ only: [prop], data: { [cursor]: base.more }, preserveUrl: true });
                }}
                onReload={() => { router.reload(); }}
            />
        ),
    };
}

function Field({ name, values, set, errors, required = false, help }: { name: string; values: Values; set: (field: string, value: string) => void; errors: Record<string, string>; required?: boolean; help?: string }) {
    const { t } = useTranslation();

    return (
        <FormField label={t(`locations.field.${name}` as TranslationKey)} required={required} help={help} error={errors[name]}>
            {(control) => <Input {...control} value={values[name] ?? ''} inputMode={name === 'minimum' ? 'decimal' : undefined} onChange={(event) => { set(name, event.target.value); }} />}
        </FormField>
    );
}

/** A product chosen by ROUTE-281's lookup, then loaded by the address key `produk` with its current minimum and version. */
function ProductPicker() {
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
            onChoose={(chosen) => {
                if (chosen !== null) router.get(PAGE, { produk: chosen.id }, { preserveState: true, preserveScroll: true, only: ['picked'] });
            }}
        />
    );
}

export default function Locations({ locations, minimums, picked, can }: Props) {
    const { t } = useTranslation();
    const [sheet, setSheet] = useState<Sheet | null>(null);
    const [picking, setPicking] = useState(false);
    const [opened, setOpened] = useState<string | null>(null);
    const racks = useContinued(locations, 'locations', 'setelah_rak', (rack) => rack.code);
    const products = useContinued(minimums, 'minimums', 'setelah_minimum', (product) => product.id);
    const close = () => {
        setSheet(null);

        // The chosen product leaves the address once its sheet closes, so choosing it again opens it again.
        if (picked !== null) {
            router.get(PAGE, {}, { preserveState: true, preserveScroll: true, only: ['picked'] });
        }
    };
    // The latest record each sheet reads its values and version from: the page's lists after any answer, the chosen product.
    const rackOf = (code: string) => racks.shown.find((rack) => rack.code === code) ?? locations.rows.find((rack) => rack.code === code);
    const productOf = (id: string): Picked | undefined => (picked?.id === id ? picked : undefined) ?? minimums.rows.find((row) => row.id === id) ?? products.shown.find((row) => row.id === id);

    if (picked === null && opened !== null) setOpened(null);

    if (picked !== null && picked.id !== opened) {
        setOpened(picked.id);
        setPicking(false);
        setSheet({ kind: 'minimum', id: picked.id });
    }

    const createAction = can.manage ? (
        <Button onClick={() => { setSheet({ kind: 'create' }); }}>
            <Icon icon={Plus} size="sm" />
            {t('locations.create')}
        </Button>
    ) : undefined;
    const editing = sheet?.kind === 'edit' ? rackOf(sheet.code) : undefined;
    const minimum = sheet?.kind === 'minimum' ? productOf(sheet.id) : undefined;

    return (
        <AppShell title={t('pages.pg_057.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <DataMasterNav current="locations" />
                <PageHeader title={t('pages.pg_057.title')} primary={createAction} />
                <PageAnswer />
                <FormSection title={t('locations.racks')}>
                    <p className="text-body text-text-secondary">{t('locations.racks_line')}</p>
                    {racks.shown.length === 0 ? (
                        <RegionEmpty text={t('locations.empty')} action={createAction} />
                    ) : (
                        <>
                            <ul role="list" aria-label={t('locations.racks')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                {racks.shown.map((rack) => (
                                    <li key={rack.code} className="flex min-w-0 flex-wrap items-center gap-3 py-2">
                                        <span className="font-mono font-medium text-text-primary">{rack.code}</span>
                                        <span className="min-w-0 flex-1 truncate text-body text-text-primary">{rack.name}</span>
                                        {!rack.active && <Badge>{t('locations.inactive')}</Badge>}
                                        {can.manage && (
                                            <Button variant="outline" size="sm" onClick={() => { setSheet({ kind: 'edit', code: rack.code }); }}>
                                                {t('locations.edit')}
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                            {racks.continuation}
                        </>
                    )}
                </FormSection>
                <FormSection title={t('locations.minimums')}>
                    <p className="text-body text-text-secondary">{t('locations.minimums_line')}</p>
                    {products.shown.length === 0 ? (
                        <p className="text-body text-text-secondary">{t('locations.minimums_empty')}</p>
                    ) : (
                        <>
                            <ul role="list" aria-label={t('locations.minimums')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                {products.shown.map((product) => (
                                    <li key={product.id} className="flex min-w-0 flex-wrap items-center gap-3 py-2">
                                        <span className="min-w-0 flex-1 truncate text-body text-text-primary">{product.name}</span>
                                        {product.sku !== null && <span className="font-mono text-caption text-text-secondary">{product.sku}</span>}
                                        <Quantity value={product.minimum} unit={product.unit} />
                                        {can.manage && (
                                            <Button variant="outline" size="sm" onClick={() => { setSheet({ kind: 'minimum', id: product.id }); }}>
                                                {t('locations.minimum_set')}
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                            {products.continuation}
                        </>
                    )}
                    {can.manage && (
                        <div className="flex min-w-0 flex-col gap-2 md:max-w-xl">
                            {picking ? (
                                <ProductPicker />
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
            {sheet?.kind === 'edit' && editing !== undefined && (
                <FormSheet
                    title={`${t('locations.edit')} ${editing.code}`}
                    url={PAGE}
                    method="post"
                    initial={{ name: editing.name, active: editing.active ? '1' : '0' }}
                    fixed={{ kind: 'edit', code: editing.code, lock_version: editing.lock_version }}
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
                    title={`${t('locations.minimum_set')} — ${minimum?.name ?? ''}`}
                    url={PAGE}
                    method="post"
                    // A product whose minimum was cleared meanwhile leaves the list: it is sent without a version, set only
                    // while it still has none (ST-01).
                    initial={{ minimum: minimum?.minimum ?? '' }}
                    fixed={{ kind: 'minimum', product: sheet.id, lock_version: minimum === undefined || (minimum.minimum === null && picked?.id !== sheet.id) ? null : minimum.lock_version }}
                    submitLabel={t('locations.minimum_set')}
                    onClose={close}
                >
                    {(values, set, errors) => <Field name="minimum" values={values} set={set} errors={errors} help={t('locations.field.minimum_help')} />}
                </FormSheet>
            )}
        </AppShell>
    );
}
