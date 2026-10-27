// PG-054 *Klien* (ROUTE-054, ROUTE-141, ROUTE-142; NAV-054; SCR-50; IA §8.6; J-MD-02; WF-MD-02; TASK-026): every active
// account's client organizations, search-first — the lookup's "Buat Klien" is the page's primary action (PT-27) —, then
// one organization by its address key: its units, each unit's addresses and PICs to a viewer who may see projects
// (PJ-10), and its related projects as a scope-filtered list without a count (UXN-23). UNY, UGM and every other client
// are ordinary rows (REF-009). Create, edit and archive are `parties.manage`, each its own command answered in place
// (IP-19); an archive says what continues and deletes nothing (BR-CR-06). `[both]`: one column on the phone.
import { router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { DataList, ListContinuation } from '@/components/data-list';
import { FormSection } from '@/components/form';
import { type Answer, OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { CLIENT_FIELDS, ClientCreateSheet, CommandAnswer, PartyField, PartyLookup } from '@/components/parties';
import { DataMasterNav } from '@/components/party-nav';
import { RegionEmpty } from '@/components/region-state';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Kind = 'organization' | 'unit' | 'address' | 'contact';
type Listed = { id: string; name: string; active: boolean };
type Address = { handle: string; active: boolean; lock_version: number; label: string | null; address_lines: string | null; city: string | null };
type Contact = { handle: string; active: boolean; lock_version: number; name: string | null; position: string | null; phone: string | null; email: string | null };
type Unit = { handle: string; name: string; active: boolean; lock_version: number; addresses: Address[]; contacts: Contact[] };
type Project = { id: string; number: string; title: string; state: string; company: string };
type Client = { id: string; name: string; active: boolean; deactivated_at: string | null; lock_version: number; npwp?: string | null; units?: Unit[]; projects?: { rows: Project[]; more: boolean } };
type Props = { list: { rows: Listed[]; more: string | null }; client: Client | null; can: { manage: boolean; relations: boolean } };
type Payload = Record<string, string | number | null>;
type Values = Record<string, string>;
/** The row a sheet adds under or edits: the kind, its handle and version, its current values. */
type Target = { mode: 'add' | 'edit'; kind: Kind; unit?: string; row?: string; lock_version?: number; values: Values };

const LIST = '/data-master/klien';

function visit(client: string | null) {
    router.get(client === null ? LIST : `${LIST}?klien=${client}`, {}, { preserveScroll: client !== null });
}

/** A command's success on the page its answer opened, once (IP-01). */
function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

/** The row's version as the page shows it now. */
function versionOf(client: Client, target: Target): number {
    if (target.kind === 'organization') return client.lock_version;

    for (const unit of client.units ?? []) {
        const row = target.kind === 'unit' ? (unit.handle === target.row ? unit : undefined)
            : target.kind === 'address' ? unit.addresses.find((address) => address.handle === target.row)
            : unit.contacts.find((contact) => contact.handle === target.row);

        if (row !== undefined) return row.lock_version;
    }

    return target.lock_version ?? 0;
}

/** Adding a unit with its address, an address or a PIC; editing any client row — at the version the page read (ST-01).
    A stale answer keeps the sheet and its input open with MSG-03; the page then shows the latest version, and sending
    again applies the input to it (IP-03). Every dismissal with unsent input asks first (PT-09). */
function RowSheet({ client, target, onClose }: { client: Client; target: Target; onClose: () => void }) {
    const { t } = useTranslation();
    const [values, setValues] = useState<Values>(target.values);
    const [leaving, setLeaving] = useState(false);
    const [version, setVersion] = useState(target.lock_version ?? 0);
    const [seen, setSeen] = useState<Answer | null>(null);
    const command = useCommand<Payload>(() => (target.mode === 'add' ? LIST : `${LIST}/${client.id}`), target.mode === 'add' ? 'post' : 'put');
    const fields: readonly string[] = target.mode === 'add' && target.kind === 'unit' ? [...CLIENT_FIELDS.unit, ...CLIENT_FIELDS.address] : CLIENT_FIELDS[target.kind];
    const title = t(`parties.${target.mode === 'add' ? `client.add_${target.kind}` : 'edit'}` as TranslationKey);
    const dirty = JSON.stringify(values) !== JSON.stringify(target.values);

    if (command.answer !== seen) {
        setSeen(command.answer);

        if (command.answer?.kind === 'CONFLICT' && command.answer.cause === 'stale') setVersion(versionOf(client, target));
    }

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
                        command.submit(
                            target.mode === 'add'
                                ? { kind: target.kind, klien: client.id, ...(target.unit !== undefined ? { unit: target.unit } : {}), ...values }
                                : { action: 'change', kind: target.kind, row: target.row ?? null, lock_version: version, ...values },
                            onClose,
                        );
                    }}
                >
                    {target.mode === 'edit' && <p className="text-body text-text-secondary">{t('parties.issued_keep')}</p>}
                    <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2">
                        {fields.map((field) => (
                            <PartyField
                                key={field}
                                name={field}
                                required={field === 'address_lines' ? target.kind === 'address' : undefined}
                                value={values[field] ?? ''}
                                error={command.errors[field]}
                                onChange={(value) => { setValues((current) => ({ ...current, [field]: value })); }}
                            />
                        ))}
                    </div>
                    <CommandAnswer command={command} />
                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="ghost" onClick={() => { if (dirty) setLeaving(true); else onClose(); }}>
                            {t('actions.cancel')}
                        </Button>
                        <Button type="submit" pending={command.pending} disabled={command.frozen}>
                            {target.mode === 'add' ? title : t('glossary.gl_157')}
                        </Button>
                    </div>
                    <UnsentInputDialog open={leaving} kind="leave" onStay={() => { setLeaving(false); }} onLeave={onClose} />
                </form>
            </SheetContent>
        </Sheet>
    );
}

/** The archive's confirmation, saying what continues (J-MD-02), with its reason. */
function ArchiveDialog({ client, target, onClose }: { client: Client; target: { kind: Kind; row?: string; lock_version: number } | null; onClose: () => void }) {
    const { t } = useTranslation();
    const [reason, setReason] = useState('');
    const archive = useCommand<Payload>(() => `${LIST}/${client.id}`, 'put');

    return (
        <ConfirmCommand
            open={target !== null}
            onOpenChange={(open) => { if (!open) onClose(); }}
            title={t('parties.client.archive_title')}
            consequence={target === null ? null : t(`parties.client.archive_line.${target.kind}` as TranslationKey)}
            reason={{ value: reason, onChange: setReason, error: archive.errors.reason }}
            confirmLabel={t('glossary.gl_058')}
            destructive
            pending={archive.pending}
            answer={archive.answer}
            onConfirm={() => {
                if (target !== null) archive.submit({ action: 'archive', kind: target.kind, row: target.row ?? null, lock_version: target.lock_version, reason }, onClose);
            }}
        />
    );
}

function RowActions({ manage, active, onEdit, onArchive }: { manage: boolean; active: boolean; onEdit: () => void; onArchive: () => void }) {
    const { t } = useTranslation();

    if (!active) return <Badge>{t('parties.archived_badge')}</Badge>;
    if (!manage) return null;

    return (
        <span className="flex shrink-0 gap-2">
            <Button variant="outline" size="sm" onClick={onEdit}>
                {t('parties.edit')}
            </Button>
            <Button variant="ghost" size="sm" onClick={onArchive}>
                {t('glossary.gl_058')}
            </Button>
        </span>
    );
}

function UnitBlock({ unit, manage, onTarget, onArchive }: { unit: Unit; manage: boolean; onTarget: (target: Target) => void; onArchive: (target: { kind: Kind; row: string; lock_version: number }) => void }) {
    const { t } = useTranslation();
    const editable = manage && unit.active;

    return (
        <li className="flex min-w-0 flex-col gap-3 py-4" data-slot="client-unit">
            <div className="flex min-w-0 flex-wrap items-center gap-3">
                <h3 className="min-w-0 flex-1 text-heading-sm text-text-primary">{unit.name}</h3>
                <RowActions
                    manage={manage}
                    active={unit.active}
                    onEdit={() => { onTarget({ mode: 'edit', kind: 'unit', row: unit.handle, lock_version: unit.lock_version, values: { unit_name: unit.name } }); }}
                    onArchive={() => { onArchive({ kind: 'unit', row: unit.handle, lock_version: unit.lock_version }); }}
                />
            </div>
            <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2">
                <div className="flex min-w-0 flex-col gap-2">
                    <p className="text-caption text-text-tertiary">{t('parties.client.addresses')}</p>
                    {unit.addresses.length === 0 ? (
                        <p className="text-body text-text-secondary">{t('parties.client.no_addresses')}</p>
                    ) : (
                        <ul role="list" className="flex min-w-0 flex-col gap-2">
                            {unit.addresses.map((address) => (
                                <li key={address.handle} className="flex min-w-0 flex-wrap items-start gap-2">
                                    <span className="flex min-w-0 flex-1 flex-col">
                                        {address.label !== null && <span className="text-body font-medium text-text-primary">{address.label}</span>}
                                        <span className="break-words text-body text-text-primary">{[address.address_lines, address.city].filter((part) => part !== null).join(', ')}</span>
                                    </span>
                                    <RowActions
                                        manage={editable}
                                        active={address.active}
                                        onEdit={() => {
                                            onTarget({ mode: 'edit', kind: 'address', row: address.handle, lock_version: address.lock_version,
                                                values: { address_label: address.label ?? '', address_lines: address.address_lines ?? '', city: address.city ?? '' } });
                                        }}
                                        onArchive={() => { onArchive({ kind: 'address', row: address.handle, lock_version: address.lock_version }); }}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                <div className="flex min-w-0 flex-col gap-2">
                    <p className="text-caption text-text-tertiary">{t('parties.client.contacts')}</p>
                    {unit.contacts.length === 0 ? (
                        <p className="text-body text-text-secondary">{t('parties.client.no_contacts')}</p>
                    ) : (
                        <ul role="list" className="flex min-w-0 flex-col gap-2">
                            {unit.contacts.map((contact) => (
                                <li key={contact.handle} className="flex min-w-0 flex-wrap items-start gap-2">
                                    <span className="flex min-w-0 flex-1 flex-col">
                                        <span className="text-body font-medium text-text-primary">{contact.name}</span>
                                        <span className="break-words text-caption text-text-secondary">{[contact.position, contact.phone, contact.email].filter((part) => part !== null).join(' · ')}</span>
                                    </span>
                                    <RowActions
                                        manage={editable}
                                        active={contact.active}
                                        onEdit={() => {
                                            onTarget({ mode: 'edit', kind: 'contact', row: contact.handle, lock_version: contact.lock_version,
                                                values: { contact_name: contact.name ?? '', contact_position: contact.position ?? '', contact_phone: contact.phone ?? '', contact_email: contact.email ?? '' } });
                                        }}
                                        onArchive={() => { onArchive({ kind: 'contact', row: contact.handle, lock_version: contact.lock_version }); }}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
            {editable && (
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" onClick={() => { onTarget({ mode: 'add', kind: 'address', unit: unit.handle, values: { address_label: '', address_lines: '', city: '' } }); }}>
                        <Icon icon={Plus} size="sm" />
                        {t('parties.client.add_address')}
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => { onTarget({ mode: 'add', kind: 'contact', unit: unit.handle, values: { contact_name: '', contact_position: '', contact_phone: '', contact_email: '' } }); }}>
                        <Icon icon={Plus} size="sm" />
                        {t('parties.client.add_contact')}
                    </Button>
                </div>
            )}
        </li>
    );
}

function Detail({ client, can }: { client: Client; can: Props['can'] }) {
    const { t } = useTranslation();
    const [target, setTarget] = useState<Target | null>(null);
    const [archiving, setArchiving] = useState<{ kind: Kind; row?: string; lock_version: number } | null>(null);
    const manage = can.manage && client.active;

    return (
        <div className="flex min-w-0 flex-col gap-6">
            <PageHeader
                title={client.name}
                facts={client.active ? undefined : <Badge>{t('parties.archived_badge')}</Badge>}
                actions={
                    manage
                        ? [
                              { label: t('parties.edit'), onSelect: () => { setTarget({ mode: 'edit', kind: 'organization', lock_version: client.lock_version, values: { name: client.name, npwp: client.npwp ?? '' } }); } },
                              { label: t('glossary.gl_058'), tone: 'danger', onSelect: () => { setArchiving({ kind: 'organization', lock_version: client.lock_version }); } },
                          ]
                        : []
                }
            />
            <PageAnswer />
            {client.units === undefined ? (
                <p className="text-body text-text-secondary">{t('parties.client.relations_hidden')}</p>
            ) : (
                <>
                    <dl className="flex min-w-0 flex-col gap-1">
                        <dt className="text-caption text-text-tertiary">{t('parties.field.npwp')}</dt>
                        <dd className="text-body text-text-primary">{client.npwp ?? '—'}</dd>
                    </dl>
                    <FormSection title={t('parties.client.units')}>
                        {client.units.length === 0 ? (
                            <RegionEmpty text={t('parties.client.no_units')} />
                        ) : (
                            <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                {client.units.map((unit) => (
                                    <UnitBlock key={unit.handle} unit={unit} manage={manage} onTarget={setTarget} onArchive={setArchiving} />
                                ))}
                            </ul>
                        )}
                        {manage && (
                            <div>
                                <Button variant="outline" onClick={() => { setTarget({ mode: 'add', kind: 'unit', values: { unit_name: '', address_label: '', address_lines: '', city: '' } }); }}>
                                    <Icon icon={Plus} size="sm" />
                                    {t('parties.client.add_unit')}
                                </Button>
                            </div>
                        )}
                    </FormSection>
                    {client.projects !== undefined && (
                        <FormSection title={t('parties.client.projects')}>
                            {client.projects.rows.length === 0 ? (
                                <p className="text-body text-text-secondary">{t('parties.client.no_projects')}</p>
                            ) : (
                                <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                    {client.projects.rows.map((project) => (
                                        <li key={project.id} className="flex min-w-0 items-center gap-3 py-2">
                                            <span className="font-medium whitespace-nowrap text-text-primary">{project.number}</span>
                                            <span className="min-w-0 flex-1 truncate text-body text-text-primary">{project.title}</span>
                                            <span className="text-caption text-text-tertiary">{project.company}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {client.projects.more && <p className="text-caption text-text-secondary">{t('parties.client.more_projects')}</p>}
                        </FormSection>
                    )}
                </>
            )}
            {target !== null && <RowSheet key={`${target.kind}-${target.row ?? target.unit ?? 'new'}`} client={client} target={target} onClose={() => { setTarget(null); }} />}
            <ArchiveDialog key={archiving === null ? 'none' : `${archiving.kind}-${archiving.row ?? ''}`} client={client} target={archiving} onClose={() => { setArchiving(null); }} />
        </div>
    );
}

export default function Clients({ list, client, can }: Props) {
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
            {t('actions.create_object', { objek: t('glossary.gl_048') })}
        </Button>
    ) : undefined;

    return (
        <AppShell title={client?.name ?? t('pages.pg_054.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <DataMasterNav current="clients" />
                {client === null ? (
                    <>
                        <PageHeader title={t('pages.pg_054.title')} primary={createAction} />
                        <PageAnswer />
                        <div className="min-w-0 md:max-w-xl">
                            <PartyLookup type="client" label={t('parties.search_clients')} value={null} onChoose={(chosen) => { if (chosen !== null) visit(chosen.id); }} canCreate={can.manage} onCreate={create} />
                        </div>
                        {shown.length === 0 ? (
                            <RegionEmpty text={t('parties.client.empty')} action={createAction} />
                        ) : (
                            <>
                                <DataList<Listed>
                                    caption={t('pages.pg_054.title')}
                                    rows={shown}
                                    rowKey={(row) => row.id}
                                    rowHref={(row) => `${LIST}?klien=${row.id}`}
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
                                {t('parties.client.back')}
                            </Button>
                        </div>
                        <Detail key={client.id} client={client} can={can} />
                    </>
                )}
            </div>
            {creating !== null && (
                <ClientCreateSheet
                    key={creating.key}
                    open
                    onOpenChange={(open) => { if (!open) setCreating(null); }}
                    name={creating.name}
                    onCreated={(created) => { if (created !== null) visit(created.organization.id); }}
                />
            )}
        </AppShell>
    );
}
