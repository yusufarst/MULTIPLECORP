// PG-055 *Pemasok* (ROUTE-055, ROUTE-143, ROUTE-144; NAV-055; SCR-51; IA §8.6; J-MD-03; WF-MD-03; TASK-026): every active
// account's suppliers, chosen directly — no comparison, scoring or ranking step (CAP-02; SH-01) —, then one supplier by
// its address key: its tax id, address and contact to a viewer who may see projects or costs (PJ-10), and its
// purchase-price history per company in scope with `cost.view`, each company apart, never pooled (PJ-09). Create, edit
// and archive are `parties.manage`; an archive blocks new purchases only (J-MD-03). `[desk]`, readable on the phone.
import { router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

import { CompanyTag } from '@/components/company';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { DataList, ListContinuation } from '@/components/data-list';
import { FormSection } from '@/components/form';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { CommandAnswer, PartyField, PartyLookup, SUPPLIER_FIELDS, SupplierCreateSheet } from '@/components/parties';
import { DataMasterNav } from '@/components/party-nav';
import { RegionEmpty } from '@/components/region-state';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { DateText, Money } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Listed = { id: string; name: string; active: boolean };
type Details = { npwp: string | null; address_lines: string | null; city: string | null; phone: string | null; email: string | null };
type Price = { company: string; product: string; unit: string; unit_price: string; date: string; purchase: string };
type Supplier = { id: string; name: string; active: boolean; deactivated_at: string | null; lock_version: number; details?: Details; prices?: Price[] };
type Props = { list: { rows: Listed[]; more: string | null }; supplier: Supplier | null; can: { manage: boolean } };
type Payload = Record<string, string | number | null>;

const LIST = '/data-master/pemasok';

function visit(supplier: string | null) {
    router.get(supplier === null ? LIST : `${LIST}?pemasok=${supplier}`, {}, { preserveScroll: supplier !== null });
}

/** A command's success on the page its answer opened, once (IP-01). */
function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

/** The supplier's fields as the page shows them now. */
function fieldsOf(supplier: Supplier): Record<string, string> {
    return { name: supplier.name, npwp: supplier.details?.npwp ?? '', address_lines: supplier.details?.address_lines ?? '', city: supplier.details?.city ?? '', phone: supplier.details?.phone ?? '', email: supplier.details?.email ?? '' };
}

/** The supplier's own fields, changed at the version the page read (ST-01); a purchase keeps its snapshot (BR-SN-01).
    The sheet keeps the values and version it opened with: a stale answer leaves the input in place with MSG-03, and only
    "Muat Versi Terbaru" takes the latest values and version (IP-03); every dismissal with unsent input asks first (PT-09). */
function EditSheet({ supplier, onClose }: { supplier: Supplier; onClose: () => void }) {
    const { t } = useTranslation();
    const [opened, setOpened] = useState(() => ({ values: fieldsOf(supplier), version: supplier.lock_version }));
    const [values, setValues] = useState(opened.values);
    const [leaving, setLeaving] = useState(false);
    const update = useCommand<Payload>(() => `${LIST}/${supplier.id}`, 'put');
    const dirty = JSON.stringify(values) !== JSON.stringify(opened.values);

    return (
        <Sheet open onOpenChange={(open) => { if (!open) { if (dirty) setLeaving(true); else onClose(); } }}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t('parties.supplier.edit_title')}</SheetTitle>
                </SheetHeader>
                <form
                    className="flex min-w-0 flex-col gap-6"
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        update.submit({ action: 'change', lock_version: opened.version, ...values }, onClose);
                    }}
                >
                    <p className="text-body text-text-secondary">{t('parties.issued_keep')}</p>
                    <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2">
                        {SUPPLIER_FIELDS.map((field) => (
                            <PartyField key={field} name={field} value={values[field] ?? ''} error={update.errors[field]} onChange={(value) => { setValues((current) => ({ ...current, [field]: value })); }} />
                        ))}
                    </div>
                    <CommandAnswer command={update} />
                    {update.answer?.kind === 'CONFLICT' && update.answer.cause === 'stale' && (
                        <div>
                            <Button type="button" variant="outline" onClick={() => { const latest = { values: fieldsOf(supplier), version: supplier.lock_version }; setOpened(latest); setValues(latest.values); update.clear(); }}>
                                {t('forms.load_latest')}
                            </Button>
                        </div>
                    )}
                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="ghost" onClick={() => { if (dirty) setLeaving(true); else onClose(); }}>
                            {t('actions.cancel')}
                        </Button>
                        <Button type="submit" pending={update.pending} disabled={update.frozen}>
                            {t('glossary.gl_157')}
                        </Button>
                    </div>
                    <UnsentInputDialog open={leaving} kind="leave" onStay={() => { setLeaving(false); }} onLeave={onClose} />
                </form>
            </SheetContent>
        </Sheet>
    );
}

function Detail({ supplier, can }: { supplier: Supplier; can: Props['can'] }) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState(false);
    const [archiving, setArchiving] = useState(false);
    const [reason, setReason] = useState('');
    const archive = useCommand<Payload>(() => `${LIST}/${supplier.id}`, 'put');
    const manage = can.manage && supplier.active;
    const details = supplier.details;

    return (
        <div className="flex min-w-0 flex-col gap-6">
            <PageHeader
                title={supplier.name}
                facts={supplier.active ? undefined : <Badge>{t('parties.archived_badge')}</Badge>}
                actions={manage ? [{ label: t('parties.edit'), onSelect: () => { setEditing(true); } }, { label: t('glossary.gl_058'), tone: 'danger', onSelect: () => { setArchiving(true); } }] : []}
            />
            <PageAnswer />
            <FormSection title={t('parties.supplier.details')}>
                {details === undefined ? (
                    <p className="text-body text-text-secondary">{t('parties.supplier.details_hidden')}</p>
                ) : (
                    <dl className="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-2">
                        {(['npwp', 'address_lines', 'city', 'phone', 'email'] as const).map((field) => (
                            <div key={field} className="flex min-w-0 flex-col gap-1">
                                <dt className="text-caption text-text-tertiary">{t(`parties.field.${field}` as TranslationKey)}</dt>
                                <dd className="min-w-0 break-words text-body text-text-primary">{details[field] ?? '—'}</dd>
                            </div>
                        ))}
                    </dl>
                )}
            </FormSection>
            {supplier.prices !== undefined && (
                <FormSection title={t('parties.supplier.prices')}>
                    <p className="text-body text-text-secondary">{t('parties.supplier.prices_line')}</p>
                    {supplier.prices.length === 0 ? (
                        <RegionEmpty text={t('parties.supplier.prices_empty')} />
                    ) : (
                        <ul role="list" aria-label={t('parties.supplier.prices')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {supplier.prices.map((row) => (
                                <li key={`${row.company}-${row.product}`} className="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-1 py-3">
                                    <CompanyTag code={row.company} />
                                    <span className="min-w-0 flex-1 text-body text-text-primary">{row.product}</span>
                                    <span className="text-body text-text-primary tabular-nums">
                                        <span className="sr-only">{t('parties.price.price')}: </span>
                                        <Money value={row.unit_price} /> / {row.unit}
                                    </span>
                                    <span className="flex gap-2 text-caption text-text-secondary">
                                        <DateText value={row.date} />
                                        <span>
                                            <span className="sr-only">{t('parties.price.purchase')}: </span>
                                            {row.purchase}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </FormSection>
            )}
            {editing && <EditSheet supplier={supplier} onClose={() => { setEditing(false); }} />}
            <ConfirmCommand
                open={archiving}
                onOpenChange={setArchiving}
                title={t('parties.supplier.archive_title')}
                consequence={t('parties.supplier.archive_line')}
                reason={{ value: reason, onChange: setReason, error: archive.errors.reason }}
                confirmLabel={t('glossary.gl_058')}
                destructive
                pending={archive.pending}
                answer={archive.answer}
                onConfirm={() => { archive.submit({ action: 'archive', lock_version: supplier.lock_version, reason }, () => { setArchiving(false); }); }}
            />
        </div>
    );
}

export default function Suppliers({ list, supplier, can }: Props) {
    const { t } = useTranslation();
    const [creating, setCreating] = useState<{ name: string; key: number } | null>(null);
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
    const createAction = can.manage ? (
        <Button onClick={() => { create(''); }}>
            <Icon icon={Plus} size="sm" />
            {t('actions.create_object', { objek: t('glossary.gl_051') })}
        </Button>
    ) : undefined;

    return (
        <AppShell title={supplier?.name ?? t('pages.pg_055.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <DataMasterNav current="suppliers" />
                {supplier === null ? (
                    <>
                        <PageHeader title={t('pages.pg_055.title')} primary={createAction} />
                        <PageAnswer />
                        <div className="min-w-0 md:max-w-xl">
                            <PartyLookup type="supplier" label={t('parties.search_suppliers')} value={null} onChoose={(chosen) => { if (chosen !== null) visit(chosen.id); }} canCreate={can.manage} onCreate={create} />
                        </div>
                        {shown.length === 0 ? (
                            <RegionEmpty text={t('parties.supplier.empty')} action={createAction} />
                        ) : (
                            <>
                                <DataList<Listed>
                                    caption={t('pages.pg_055.title')}
                                    rows={shown}
                                    rowKey={(row) => row.id}
                                    rowHref={(row) => `${LIST}?pemasok=${row.id}`}
                                    columns={[
                                        {
                                            key: 'name',
                                            header: t('parties.field.name'),
                                            cell: (row) => (
                                                <span className="flex min-w-0 items-center gap-2">
                                                    <span className="min-w-0 truncate">{row.name}</span>
                                                    {!row.active && <Badge>{t('parties.archived_badge')}</Badge>}
                                                </span>
                                            ),
                                        },
                                    ]}
                                    dense={(row) => ({ title: row.name, ...(row.active ? {} : { status: <Badge>{t('parties.archived_badge')}</Badge> }) })}
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
                                {t('parties.supplier.back')}
                            </Button>
                        </div>
                        <Detail key={supplier.id} supplier={supplier} can={can} />
                    </>
                )}
            </div>
            {creating !== null && (
                <SupplierCreateSheet
                    key={creating.key}
                    open
                    onOpenChange={(open) => { if (!open) setCreating(null); }}
                    name={creating.name}
                    onCreated={(created) => { if (created !== null) visit(created.id); }}
                />
            )}
        </AppShell>
    );
}
