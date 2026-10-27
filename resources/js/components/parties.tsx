// The party masters' shared pieces (TASK-026; J-MD-02; J-MD-03; PT-27; IP-17; IP-19): ROUTE-281's lookup of one party
// type, the search-first creation of a client — with its first unit, address and PIC — and of a supplier, and the
// pickers a form uses, whose "Buat {Objek}" opens the same creation in a sheet: one creation path per record, inline or
// on the page (ROUTE-141, ROUTE-143). The creation's own command answers first; a form opening it inline is sent back
// to with the new value chosen and its own input kept (IP-19). A likely duplicate name answers with the matches, and a
// second submission names each with its reason (PF-21; IP-05).
import { type ReactNode, useRef, useState } from 'react';

import { FormField, FormSection } from '@/components/form';
import { Lookup, type LookupGroup, type LookupResult } from '@/components/lookup';
import { DuplicateWarning, type DuplicateMatch } from '@/components/notices';
import { type Answer, OutcomeMessage } from '@/components/outcome-message';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import type { SearchResult } from '@/lib/navigation';
import { search } from '@/lib/search-client';
import { useCommand } from '@/lib/use-command';
import { cn } from '@/lib/utils';

export type Choice = { handle: string; label: string };
export type CreatedClient = { organization: { id: string; name: string }; unit: Choice | null; address: Choice | null; contact: Choice | null };
export type CreatedSupplier = { id: string; name: string };
type Payload = Record<string, string | number | boolean | string[] | null>;
type Values = Record<string, string>;

/** The form fields of each client row kind and of a supplier; a name field and an address's lines are required. */
export const CLIENT_FIELDS = {
    organization: ['name', 'npwp'],
    unit: ['unit_name'],
    address: ['address_label', 'address_lines', 'city'],
    contact: ['contact_name', 'contact_position', 'contact_phone', 'contact_email'],
} as const;
export const SUPPLIER_FIELDS = ['name', 'npwp', 'address_lines', 'city', 'phone', 'email'] as const;
const REQUIRED = new Set(['name', 'unit_name', 'contact_name']);

/** ROUTE-281's rows of one party type for the term, a quarter second after the last keystroke, an earlier request
    abandoned; a changed term shows nothing until its own answer (IP-17). */
export function usePartySearch(type: 'client' | 'supplier') {
    const [term, setTerm] = useState('');
    const [answer, setAnswer] = useState<{ term: string; rows: SearchResult[]; cut: boolean } | null>(null);
    const pending = useRef<{ timer: number; controller: AbortController } | null>(null);
    const find = (text: string) => {
        setTerm(text);

        if (pending.current !== null) {
            window.clearTimeout(pending.current.timer);
            pending.current.controller.abort();
        }

        if (text.trim() === '') {
            setAnswer(null);

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            void search(text, [type], controller.signal).then((outcome) => {
                if (outcome.kind === 'answer') {
                    setAnswer({ term: text.trim(), rows: [...outcome.answer.exact, ...outcome.answer.groups.flatMap((group) => group.rows)], cut: outcome.answer.notice === 'cut' });
                }
            });
        }, 250);
        pending.current = { timer, controller };
    };
    const current = answer !== null && answer.term === term.trim() ? answer : null;

    return { term, find, rows: current?.rows ?? [], cut: current?.cut ?? false, loading: term.trim() !== '' && current === null };
}

function groupsOf(rows: SearchResult[], label: string, value: string): LookupGroup[] {
    return rows.length === 0 ? [] : [{ value, label, items: rows.map((row) => ({ id: row.id, code: '', label: row.label, ...(row.detail !== undefined ? { detail: row.detail } : {}) })) }];
}

/** A form's refusal or failure in place, with "Coba Lagi" while the outcome is unknown (IP-02; IP-04). */
export function CommandAnswer({ command }: { command: { answer: Answer | null; unknown: boolean; retry: () => void } }) {
    const { t } = useTranslation();

    if (command.answer === null || command.answer.kind === 'COMMITTED') return null;

    return (
        <OutcomeMessage
            answer={command.answer}
            action={
                command.unknown ? (
                    <Button variant="outline" size="sm" onClick={command.retry}>
                        {t('actions.try_again')}
                    </Button>
                ) : undefined
            }
        />
    );
}

/** One party field, its label and error from the catalogue; an address's lines in a text area. */
export function PartyField({ name, value, onChange, error, required, className }: { name: string; value: string; onChange: (value: string) => void; error?: string; required?: boolean; className?: string }) {
    const { t } = useTranslation();

    return (
        <FormField label={t(`parties.field.${name}` as TranslationKey)} required={required ?? REQUIRED.has(name)} error={error} className={cn(name === 'address_lines' && 'md:col-span-2', className)}>
            {(control) =>
                name === 'address_lines' ? (
                    <Textarea {...control} value={value} maxLength={500} onChange={(event) => { onChange(event.target.value); }} />
                ) : (
                    <Input {...control} value={value} type={name.endsWith('email') ? 'email' : name.endsWith('phone') ? 'tel' : 'text'} maxLength={200} onChange={(event) => { onChange(event.target.value); }} />
                )
            }
        </FormField>
    );
}

/** The existing names like the one typed — equal and prefix names first (PF-21) —, so no duplicate is created. */
function SimilarNames({ type, rows }: { type: 'client' | 'supplier'; rows: SearchResult[] }) {
    const { t } = useTranslation();

    if (rows.length === 0) return null;

    return (
        <div className="flex min-w-0 flex-col gap-2" data-slot="similar-names">
            <p className="text-caption text-text-secondary">{t('parties.similar')}</p>
            <ul role="list" className="flex min-w-0 flex-col gap-1">
                {rows.slice(0, 5).map((row) => (
                    <li key={row.id} className="min-w-0 truncate text-body text-text-primary">
                        <a className="underline-offset-2 hover:underline" href={type === 'client' ? `/data-master/klien?klien=${row.id}` : `/data-master/pemasok?pemasok=${row.id}`}>
                            {row.label}
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** A creation's sheet: its fields, the duplicate warning or the refusal in place, and "Batal" asking first when
    input is unsent (MSG-35; NV-02). */
function CreationSheet({
    open,
    onOpenChange,
    title,
    type,
    url,
    fields,
    initial,
    extra,
    onCreated,
    inline,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    type: 'client' | 'supplier';
    url: string;
    fields: readonly string[];
    initial: Values;
    extra: Payload;
    onCreated: (state: Record<string, unknown>) => void;
    inline: boolean;
    children?: (values: Values, set: (field: string, value: string) => void, errors: Record<string, string>) => ReactNode;
}) {
    const { t } = useTranslation();
    const [values, setValues] = useState<Values>(initial);
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [reason, setReason] = useState('');
    const [leaving, setLeaving] = useState(false);
    const similar = usePartySearch(type);
    const create = useCommand<Payload>(() => url, 'post');
    const set = (field: string, value: string) => {
        setValues((current) => ({ ...current, [field]: value }));

        if (field === 'name') similar.find(value);
    };
    const matches = Array.isArray(create.state.matches) ? (create.state.matches as DuplicateMatch[]) : [];
    const warned = create.answer?.kind === 'REJECTED' && matches.length > 0;
    const changed = JSON.stringify(values) !== JSON.stringify(initial);

    return (
        <Sheet open={open} onOpenChange={(next) => { if (!next && changed) setLeaving(true); else onOpenChange(next); }}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                </SheetHeader>
                <form
                    className="flex min-w-0 flex-col gap-6"
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        create.submit({ ...extra, ...values, inline, ...(warned ? { acknowledged, reason } : {}) }, (state) => {
                            onOpenChange(false);
                            onCreated(state);
                        });
                    }}
                >
                    <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2">
                        {fields.map((field) => (
                            <PartyField key={field} name={field} value={values[field] ?? ''} error={create.errors[field]} onChange={(value) => { set(field, value); }} />
                        ))}
                    </div>
                    {children?.(values, set, create.errors)}
                    {warned ? (
                        <DuplicateWarning
                            matches={matches}
                            message={create.answer?.text}
                            acknowledged={acknowledged}
                            onAcknowledge={setAcknowledged}
                            reason={{ value: reason, onChange: setReason, error: create.errors.reason }}
                            canContinue
                        />
                    ) : (
                        <CommandAnswer command={create} />
                    )}
                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="ghost" onClick={() => { if (changed) setLeaving(true); else onOpenChange(false); }}>
                            {t('actions.cancel')}
                        </Button>
                        <Button type="submit" pending={create.pending} disabled={create.frozen}>
                            {t('actions.create_object', { objek: t(type === 'client' ? 'glossary.gl_048' : 'glossary.gl_051') })}
                        </Button>
                    </div>
                    {/* Below the actions: the list arrives after the typing, and must never move the button about to be pressed. */}
                    {!warned && <SimilarNames type={type} rows={similar.rows} />}
                    <UnsentInputDialog open={leaving} kind="leave" onStay={() => { setLeaving(false); }} onLeave={() => { setLeaving(false); onOpenChange(false); }} />
                </form>
            </SheetContent>
        </Sheet>
    );
}

/** J-MD-02's creation: the organization, and — optionally, in one command — its first unit with the unit's address
    and a PIC, one column on the phone. */
export function ClientCreateSheet({ open, onOpenChange, name, inline = false, onCreated }: { open: boolean; onOpenChange: (open: boolean) => void; name: string; inline?: boolean; onCreated: (created: CreatedClient | null) => void }) {
    const { t } = useTranslation();
    const initial: Values = { name, npwp: '', unit_name: '', address_label: '', address_lines: '', city: '', contact_name: '', contact_position: '', contact_phone: '', contact_email: '' };

    return (
        <CreationSheet
            open={open}
            onOpenChange={onOpenChange}
            title={t('actions.create_object', { objek: t('glossary.gl_048') })}
            type="client"
            url="/data-master/klien"
            fields={CLIENT_FIELDS.organization}
            initial={initial}
            extra={{ kind: 'organization' }}
            inline={inline}
            onCreated={(state) => { onCreated((state.created as CreatedClient | undefined) ?? null); }}
        >
            {(values, set, errors) => (
                <FormSection title={t('parties.client.first_unit')}>
                    <p className="text-body text-text-secondary">{t('parties.client.first_unit_line')}</p>
                    <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2">
                        {[...CLIENT_FIELDS.unit, ...CLIENT_FIELDS.address, ...CLIENT_FIELDS.contact].map((field) => (
                            <PartyField key={field} name={field} required={false} value={values[field] ?? ''} error={errors[field]} onChange={(value) => { set(field, value); }} />
                        ))}
                    </div>
                </FormSection>
            )}
        </CreationSheet>
    );
}

/** J-MD-03's creation: the supplier chosen directly — no comparison step (CAP-02). */
export function SupplierCreateSheet({ open, onOpenChange, name, inline = false, onCreated }: { open: boolean; onOpenChange: (open: boolean) => void; name: string; inline?: boolean; onCreated: (created: CreatedSupplier | null) => void }) {
    const { t } = useTranslation();

    return (
        <CreationSheet
            open={open}
            onOpenChange={onOpenChange}
            title={t('actions.create_object', { objek: t('glossary.gl_051') })}
            type="supplier"
            url="/data-master/pemasok"
            fields={SUPPLIER_FIELDS}
            initial={{ name, npwp: '', address_lines: '', city: '', phone: '', email: '' }}
            extra={{}}
            inline={inline}
            onCreated={(state) => { onCreated((state.created as { supplier?: CreatedSupplier } | undefined)?.supplier ?? null); }}
        />
    );
}

/** A party lookup with "Buat {Objek}" where the account may create (PT-27): the page's search-first entry, and a
    form's picker, whose inline creation returns with the new value chosen (IP-19). */
export function PartyLookup({
    type,
    label,
    value,
    onChoose,
    canCreate,
    onCreate,
}: {
    type: 'client' | 'supplier';
    label: string;
    value: LookupResult | null;
    onChoose: (result: LookupResult | null) => void;
    canCreate: boolean;
    /** Opens the creation with the term kept (D-DS-21). */
    onCreate: (term: string) => void;
}) {
    const { t } = useTranslation();
    const found = usePartySearch(type);
    const object = t(type === 'client' ? 'glossary.gl_048' : 'glossary.gl_051');

    return (
        <Lookup
            label={label}
            placeholder={label}
            term={found.term}
            onTermChange={found.find}
            groups={groupsOf(found.rows, object, type)}
            cut={found.cut}
            loading={found.loading}
            value={value}
            onChoose={onChoose}
            {...(canCreate ? { create: { object, onCreate: () => { onCreate(found.term.trim()); } } } : {})}
        />
    );
}
