// The administrative checklist inside PG-011's "Kesiapan dokumen" and its commands, which PG-039 shares (WF-ADM-01;
// J-ADM-01; D-IA-10; TASK-035): each requirement a derived readiness row — the item, its status by shape and label
// (DESIGN_SYSTEM §12.1), what is missing and what satisfies it —, missing items first, with no fraction, bar or
// percentage (PT-29). An Admin adds, tightens and links (ROUTE-164, ROUTE-165) and marks an eligible item *Tidak
// Berlaku* with MSG-40's reason (ROUTE-166; IP-09); the Owner's eligibility, relaxation and removal (ROUTE-167,
// ROUTE-168) and the correction of a waiver (ROUTE-169; CM-27) appear only with their capabilities (J-ADM-01; NV-08),
// the server deciding either way (AZ-01).
import { useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { FormField } from '@/components/form';
import { OutcomeMessage } from '@/components/outcome-message';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { Code, DateTimeText } from '@/components/values';
import { typeName } from '@/features/doc/labels';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

export type RequirementStatus = 'MISSING' | 'DRAFT_ONLY' | 'SATISFIED' | 'WAIVED';
type Kind = 'CATALOG_DOCUMENT' | 'EXTERNAL_DOCUMENT' | 'FREE_TEXT';
type Mode = 'GENERATED' | 'UPLOADED' | 'EITHER';
type LinkRow = { kind: 'document' | 'evidence'; id: string | null; type: string; number?: string | null; state?: string; issuer?: string | null };
type Waiver = { handle: string | null; reason: string; by: string | null; at: string; reopened: boolean; current: boolean };
export type Requirement = {
    handle: string;
    kind: Kind;
    document_type: string | null;
    evidence_type: string | null;
    description: string;
    required: boolean;
    mode: Mode;
    client_original: boolean;
    default_invoice: boolean;
    lock_version: number;
    status: RequirementStatus;
    may_waive: boolean;
    waiver_eligible?: boolean;
    links?: LinkRow[];
    reproduction?: boolean;
    waivers?: Waiver[];
};
type Options = {
    document_types: string[];
    evidence_types: string[];
    documents: { id: string; type: string; number: string | null }[];
    evidence: { id: string; type: string; issuer: string; reference: string | null; date: string }[];
};
export type Checklist = {
    items: Requirement[];
    can: { manage: boolean; waive: boolean; owner: boolean; relax: boolean; supersede: boolean };
    options: Options | null;
};
type Payload = Record<string, string | number | boolean | null>;

const MODES: Mode[] = ['GENERATED', 'UPLOADED', 'EITHER'];
const KINDS: Kind[] = ['CATALOG_DOCUMENT', 'EXTERNAL_DOCUMENT', 'FREE_TEXT'];
const evidenceName = (type: string): TranslationKey => `evidence.type.${type}` as TranslationKey;
const modeName = (mode: Mode): TranslationKey => `adm.mode.${mode.toLowerCase()}` as TranslationKey;

/** The item's name: the invoice item, a catalogue document, an external document type, or its own description. */
export function useRequirementName(): (item: Pick<Requirement, 'kind' | 'document_type' | 'evidence_type' | 'description' | 'default_invoice'>) => string {
    const { t } = useTranslation();

    return (item) => {
        if (item.default_invoice) return t('adm.invoice');
        if (item.kind === 'CATALOG_DOCUMENT' && item.document_type !== null) return t(typeName(item.document_type));
        if (item.kind === 'EXTERNAL_DOCUMENT' && item.evidence_type !== null) return t(evidenceName(item.evidence_type));

        return item.description;
    };
}

/** What the item still takes, said once under its name (J-ADM-01). */
function Missing({ item }: { item: Requirement }) {
    const { t } = useTranslation();

    if (item.status === 'SATISFIED' || item.status === 'WAIVED') return null;
    const text = item.default_invoice ? t('adm.missing.invoice') : item.status === 'DRAFT_ONLY' ? t('adm.prepared') : t(`adm.missing.${item.mode.toLowerCase()}` as TranslationKey);

    return <p className="text-body text-text-secondary">{text}</p>;
}

function Facts({ item }: { item: Requirement }) {
    const { t } = useTranslation();
    const facts = [item.required ? t('adm.required') : t('adm.optional'), ...(item.default_invoice ? [] : [t(modeName(item.mode))]), ...(item.client_original ? [t('adm.client_original')] : [])];

    return <p className="text-caption text-text-secondary">{facts.join(' · ')}</p>;
}

/** A requirement's row: its name and status, what it takes, what satisfies it, its decisions and its actions. */
function RequirementRow({ item, can, onAction }: { item: Requirement; can: Checklist['can']; onAction: (action: Action) => void }) {
    const { t } = useTranslation();
    const name = useRequirementName();
    const open = item.status !== 'SATISFIED' && item.status !== 'WAIVED';
    const editable = !item.default_invoice && item.status !== 'SATISFIED';

    return (
        <li data-requirement={item.handle} className="flex min-w-0 flex-col gap-2 py-3">
            <div className="flex min-w-0 flex-wrap items-center justify-between gap-2">
                <span className="min-w-0 font-semibold text-text-primary">{name(item)}</span>
                <StatusBadge family="administrative_requirement" state={item.status} size="sm" />
            </div>
            {item.kind !== 'FREE_TEXT' && !item.default_invoice && item.description !== '' && <p className="text-body text-text-primary">{item.description}</p>}
            <Facts item={item} />
            <Missing item={item} />
            {item.reproduction === true && <p className="text-body text-text-secondary">{t('adm.reproduction')}</p>}
            {(item.links ?? []).length > 0 && (
                <ul className="flex min-w-0 flex-col gap-1">
                    {(item.links ?? []).map((link, index) => (
                        <li key={`${link.kind}-${String(index)}`} className="text-body">
                            {link.id === null ? (
                                <span className="text-text-secondary">{t(link.kind === 'document' ? 'adm.link.document' : 'adm.link.evidence')}</span>
                            ) : link.kind === 'document' ? (
                                <a href={`/dokumen/${link.id}`} className="text-accent-link underline underline-offset-2">
                                    {t(typeName(link.type))}
                                    {link.number !== undefined && link.number !== null && <> · <Code>{link.number}</Code></>}
                                </a>
                            ) : (
                                <a href={`/dokumen/bukti?bukti=${link.id}`} className="text-accent-link underline underline-offset-2">
                                    {t(evidenceName(link.type))}
                                    {link.issuer !== undefined && link.issuer !== null && ` · ${link.issuer}`}
                                </a>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {(item.waivers ?? []).length > 0 && (
                <details className="min-w-0 text-body">
                    <summary className="cursor-pointer text-text-secondary">{t('adm.waiver.history')}</summary>
                    <ol className="mt-2 flex min-w-0 flex-col gap-2">
                        {(item.waivers ?? []).map((waiver, index) => (
                            <li key={`${waiver.at}-${String(index)}`} className="flex min-w-0 flex-col gap-1 border-l-2 border-border-subtle pl-3">
                                <span className="font-semibold text-text-primary">
                                    {waiver.reopened ? t('adm.waiver.reopened') : t('adm.waiver.waived')}
                                    {!waiver.current && ` · ${t('adm.waiver.superseded')}`}
                                </span>
                                <span className="text-text-secondary">{t('adm.waiver.reason', { alasan: waiver.reason })}</span>
                                <span className="text-caption text-text-tertiary">
                                    {waiver.by !== null && `${t('adm.waiver.by', { nama: waiver.by })} · `}
                                    <DateTimeText value={waiver.at} />
                                </span>
                                {waiver.handle !== null && (
                                    <div>
                                        <Button variant="outline" size="sm" onClick={() => { onAction({ kind: 'supersede', item, decision: waiver.handle ?? '' }); }}>
                                            {t('adm.action.supersede')}
                                        </Button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ol>
                </details>
            )}
            <div className="flex flex-wrap gap-2">
                {can.manage && open && !item.default_invoice && (
                    <Button size="sm" onClick={() => { onAction({ kind: 'link', item }); }}>{t('adm.action.link')}</Button>
                )}
                {open && !item.default_invoice && item.mode !== 'GENERATED' && can.manage && (
                    <Button variant="outline" size="sm" render={<a href="/dokumen/bukti" />}>{t('adm.action.upload')}</Button>
                )}
                {item.may_waive && open && (
                    <Button variant="outline" size="sm" onClick={() => { onAction({ kind: 'waive', item }); }}>{t('adm.action.waive')}</Button>
                )}
                {can.manage && editable && (
                    <Button variant="outline" size="sm" onClick={() => { onAction({ kind: 'tighten', item }); }}>{t('adm.action.tighten')}</Button>
                )}
                {can.owner && !item.default_invoice && item.waiver_eligible !== undefined && (
                    <Button variant="outline" size="sm" onClick={() => { onAction({ kind: 'eligibility', item }); }}>
                        {item.waiver_eligible ? t('adm.action.eligibility_off') : t('adm.action.eligibility_on')}
                    </Button>
                )}
                {can.relax && !item.default_invoice && (
                    <Button variant="outline" size="sm" onClick={() => { onAction({ kind: 'relax', item }); }}>{t('adm.action.relax')}</Button>
                )}
            </div>
        </li>
    );
}

type Action =
    | { kind: 'add' }
    | { kind: 'tighten'; item: Requirement }
    | { kind: 'link'; item: Requirement }
    | { kind: 'waive'; item: Requirement }
    | { kind: 'eligibility'; item: Requirement }
    | { kind: 'relax'; item: Requirement }
    | { kind: 'supersede'; item: Requirement; decision: string };

type Draft = { kind: Kind; documentType: string; evidenceType: string; description: string; required: boolean; mode: Mode; clientOriginal: boolean; remove: boolean; target: string };

const blankDraft = (options: Options | null): Draft => ({
    kind: 'CATALOG_DOCUMENT',
    documentType: options?.document_types[0] ?? '',
    evidenceType: options?.evidence_types[0] ?? '',
    description: '',
    required: true,
    mode: 'GENERATED',
    clientOriginal: false,
    remove: false,
    target: '',
});

/** The requirement commands' dialogs, shared by PG-011 and PG-039; `projectId` names the project of the item acted on. */
export function RequirementCommands({ projectId, action, options, onClose }: { projectId: string; action: Action | null; options: Options | null; onClose: () => void }) {
    const { t } = useTranslation();
    const name = useRequirementName();
    const item = action !== null && action.kind !== 'add' ? action.item : null;
    const save = useCommand<Payload>(() => `/proyek/${projectId}/persyaratan`, 'post');
    const link = useCommand<Payload>(() => `/proyek/${projectId}/persyaratan/${item?.handle ?? ''}/tautkan`, 'post');
    const waive = useCommand<Payload>(() => `/proyek/${projectId}/persyaratan/${item?.handle ?? ''}/tidak-berlaku`, 'post');
    const eligibility = useCommand<Payload>(() => `/proyek/${projectId}/persyaratan/${item?.handle ?? ''}/kelayakan`, 'post');
    const relax = useCommand<Payload>(() => `/proyek/${projectId}/persyaratan/${item?.handle ?? ''}/longgarkan`, 'post');
    const supersede = useCommand<Payload>(() => `/keputusan/${action?.kind === 'supersede' ? action.decision : ''}/ganti`, 'post');
    const [draft, setDraft] = useState<Draft>(blankDraft(options));
    const [reason, setReason] = useState('');
    const [shown, setShown] = useState<Action | null>(null);
    const set = (patch: Partial<Draft>) => { setDraft((current) => ({ ...current, ...patch })); };

    // A newly opened dialog starts from the item it acts on; an open one keeps its input (NV-02).
    if (action !== shown) {
        setShown(action);

        if (action !== null) {
            setReason('');
            [save, link, waive, eligibility, relax, supersede].forEach((command) => { command.clear(); });
            setDraft(action.kind === 'add' ? blankDraft(options) : {
                ...blankDraft(options),
                kind: action.item.kind,
                documentType: action.item.document_type ?? '',
                evidenceType: action.item.evidence_type ?? '',
                description: action.item.description,
                required: action.item.required,
                mode: action.item.mode,
                clientOriginal: action.item.client_original,
            });
        }
    }

    const close = (open: boolean) => { if (!open) onClose(); };
    const editing = action?.kind === 'add' || action?.kind === 'tighten';
    const documents = (options?.documents ?? []).filter((option) => item?.document_type === null || item?.document_type === undefined || option.type === item.document_type);
    const evidence = (options?.evidence ?? []).filter((option) => item?.evidence_type === null || item?.evidence_type === undefined || option.type === item.evidence_type);
    const takesDocument = item !== null && !item.client_original && item.mode !== 'UPLOADED';
    const takesEvidence = item !== null && item.mode !== 'GENERATED';
    const targets = [
        ...(takesDocument ? documents.map((option) => ({ value: `dokumen:${option.id}`, label: `${t(typeName(option.type))}${option.number === null ? '' : ` · ${option.number}`}` })) : []),
        ...(takesEvidence ? evidence.map((option) => ({ value: `bukti:${option.id}`, label: `${t(evidenceName(option.type))} · ${option.issuer}${option.reference === null ? '' : ` · ${option.reference}`}` })) : []),
    ];
    const target = draft.target !== '' && targets.some((option) => option.value === draft.target) ? draft.target : (targets[0]?.value ?? '');

    return (
        <>
            {[save, link, waive, eligibility, relax, supersede].map((command, index) => (command.answer === null || command.answer.kind === 'COMMITTED' || action !== null ? null : <OutcomeMessage key={index} answer={command.answer} />))}
            <ConfirmCommand
                open={editing}
                onOpenChange={close}
                title={action?.kind === 'tighten' ? t('adm.tighten.title') : t('adm.add.title')}
                consequence={t('adm.add.consequence')}
                items={
                    <div className="flex min-w-0 flex-col gap-4">
                        <FormField label={t('adm.field.kind')} required error={save.errors.jenis}>
                            {(control) => (
                                <NativeSelect {...control} value={draft.kind} disabled={action?.kind === 'tighten'} onChange={(event) => { set({ kind: event.target.value as Kind }); }}>
                                    {KINDS.map((kind) => <option key={kind} value={kind}>{t(`adm.kind.${kind.toLowerCase()}` as TranslationKey)}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        {draft.kind === 'CATALOG_DOCUMENT' && (
                            <FormField label={t('adm.field.document_type')} required error={save.errors.tipe_dokumen}>
                                {(control) => (
                                    <NativeSelect {...control} value={draft.documentType} disabled={action?.kind === 'tighten'} onChange={(event) => { set({ documentType: event.target.value }); }}>
                                        {(options?.document_types ?? []).map((type) => <option key={type} value={type}>{t(typeName(type))}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                        )}
                        {draft.kind === 'EXTERNAL_DOCUMENT' && (
                            <FormField label={t('adm.field.evidence_type')} required error={save.errors.tipe_bukti}>
                                {(control) => (
                                    <NativeSelect {...control} value={draft.evidenceType} disabled={action?.kind === 'tighten'} onChange={(event) => { set({ evidenceType: event.target.value }); }}>
                                        {(options?.evidence_types ?? []).map((type) => <option key={type} value={type}>{t(evidenceName(type))}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                        )}
                        <FormField label={t('adm.field.description')} required error={save.errors.uraian}>
                            {(control) => <Textarea {...control} value={draft.description} maxLength={500} disabled={action?.kind === 'tighten'} onChange={(event) => { set({ description: event.target.value }); }} />}
                        </FormField>
                        <RequirementTerms draft={draft} set={set} errors={save.errors} />
                    </div>
                }
                confirmLabel={action?.kind === 'tighten' ? t('adm.action.tighten') : t('adm.action.add')}
                pending={save.pending}
                answer={save.answer}
                onConfirm={() => {
                    save.submit({
                        ...(item === null ? {} : { persyaratan: item.handle, lock_version: item.lock_version }),
                        jenis: draft.kind,
                        tipe_dokumen: draft.kind === 'CATALOG_DOCUMENT' ? draft.documentType : null,
                        tipe_bukti: draft.kind === 'EXTERNAL_DOCUMENT' ? draft.evidenceType : null,
                        uraian: draft.description,
                        wajib: draft.required,
                        cara: draft.mode,
                        asli_klien: draft.mode === 'UPLOADED' && draft.clientOriginal,
                    }, onClose);
                }}
            />
            <ConfirmCommand
                open={action?.kind === 'link'}
                onOpenChange={close}
                title={t('adm.link.title')}
                consequence={t('adm.link.consequence')}
                items={
                    <div className="flex min-w-0 flex-col gap-4">
                        {item !== null && <p className="font-semibold text-text-primary">{name(item)}</p>}
                        {targets.length === 0 ? (
                            <p className="text-body text-text-secondary">{t('adm.link.none')}</p>
                        ) : (
                            <FormField label={takesDocument && !takesEvidence ? t('adm.field.document') : t('adm.field.evidence')} required error={link.errors.dokumen ?? link.errors.bukti}>
                                {(control) => (
                                    <NativeSelect {...control} value={target} onChange={(event) => { set({ target: event.target.value }); }}>
                                        {targets.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                    </NativeSelect>
                                )}
                            </FormField>
                        )}
                        {takesEvidence && <a href="/dokumen/bukti" className="text-accent-link underline underline-offset-2">{t('adm.action.upload')}</a>}
                    </div>
                }
                confirmLabel={t('adm.action.link')}
                pending={link.pending}
                answer={link.answer}
                onConfirm={() => {
                    if (target === '') return;
                    const [field, id] = target.split(':');
                    link.submit({ [field === 'dokumen' ? 'dokumen' : 'bukti']: id ?? '' }, onClose);
                }}
            />
            <ConfirmCommand
                open={action?.kind === 'waive'}
                onOpenChange={close}
                title={t('adm.waive.title')}
                consequence={t('adm.waive.consequence')}
                items={item === null ? undefined : <p className="font-semibold text-text-primary">{name(item)}</p>}
                reason={{ value: reason, onChange: setReason, error: waive.errors.alasan ?? null }}
                confirmLabel={t('adm.action.waive')}
                pending={waive.pending}
                answer={waive.answer}
                onConfirm={() => { waive.submit({ alasan: reason }, onClose); }}
            />
            <ConfirmCommand
                open={action?.kind === 'eligibility'}
                onOpenChange={close}
                title={t('adm.eligibility.title')}
                consequence={item?.waiver_eligible === true ? t('adm.eligibility.consequence_off') : t('adm.eligibility.consequence_on')}
                items={item === null ? undefined : <p className="font-semibold text-text-primary">{name(item)}</p>}
                confirmLabel={item?.waiver_eligible === true ? t('adm.action.eligibility_off') : t('adm.action.eligibility_on')}
                pending={eligibility.pending}
                answer={eligibility.answer}
                onConfirm={() => { if (item !== null) eligibility.submit({ lock_version: item.lock_version, layak: item.waiver_eligible !== true }, onClose); }}
            />
            <ConfirmCommand
                open={action?.kind === 'relax'}
                onOpenChange={close}
                title={t('adm.relax.title')}
                consequence={t('adm.relax.consequence')}
                items={
                    <div className="flex min-w-0 flex-col gap-4">
                        {item !== null && <p className="font-semibold text-text-primary">{name(item)}</p>}
                        <div className="flex items-center gap-2">
                            <Checkbox id="requirement-remove" checked={draft.remove} onCheckedChange={(checked) => { set({ remove: checked }); }} />
                            <label htmlFor="requirement-remove" className="text-body text-text-primary">{t('adm.field.remove')}</label>
                        </div>
                        {!draft.remove && <RequirementTerms draft={draft} set={set} errors={relax.errors} />}
                    </div>
                }
                reason={{ value: reason, onChange: setReason, error: relax.errors.alasan ?? null }}
                confirmLabel={t('adm.action.relax')}
                destructive={draft.remove}
                pending={relax.pending}
                answer={relax.answer}
                onConfirm={() => {
                    if (item === null) return;
                    relax.submit({ lock_version: item.lock_version, hapus: draft.remove, wajib: draft.required, cara: draft.mode, asli_klien: draft.mode === 'UPLOADED' && draft.clientOriginal, alasan: reason }, onClose);
                }}
            />
            <ConfirmCommand
                open={action?.kind === 'supersede'}
                onOpenChange={close}
                title={t('adm.supersede.title')}
                consequence={t('adm.supersede.consequence_waiver')}
                items={item === null ? undefined : <p className="font-semibold text-text-primary">{name(item)}</p>}
                reason={{ value: reason, onChange: setReason, error: supersede.errors.alasan ?? null }}
                confirmLabel={t('adm.action.supersede')}
                pending={supersede.pending}
                answer={supersede.answer}
                onConfirm={() => { supersede.submit({ proyek: projectId, alasan: reason }, onClose); }}
            />
        </>
    );
}

/** *Wajib*, the mode and "Harus asli dari klien" — the last only with the uploaded mode (DATABASE §4.11). */
function RequirementTerms({ draft, set, errors }: { draft: Draft; set: (patch: Partial<Draft>) => void; errors: Record<string, string> }) {
    const { t } = useTranslation();

    return (
        <>
            <div className="flex items-center gap-2">
                <Checkbox id="requirement-required" checked={draft.required} onCheckedChange={(checked) => { set({ required: checked }); }} />
                <label htmlFor="requirement-required" className="text-body text-text-primary">{t('adm.field.required')}</label>
            </div>
            <FormField label={t('adm.field.mode')} required error={errors.cara}>
                {(control) => (
                    <NativeSelect {...control} value={draft.mode} onChange={(event) => { set({ mode: event.target.value as Mode }); }}>
                        {MODES.map((mode) => <option key={mode} value={mode}>{t(modeName(mode))}</option>)}
                    </NativeSelect>
                )}
            </FormField>
            {draft.mode === 'UPLOADED' && (
                <div className="flex items-center gap-2">
                    <Checkbox id="requirement-original" checked={draft.clientOriginal} onCheckedChange={(checked) => { set({ clientOriginal: checked }); }} />
                    <label htmlFor="requirement-original" className="text-body text-text-primary">{t('adm.field.client_original')}</label>
                </div>
            )}
            {errors.asli_klien !== undefined && <p className="text-danger-fg">{errors.asli_klien}</p>}
        </>
    );
}

/** PG-011's checklist inside "Kesiapan dokumen" (D-IA-10; J-ADM-01). */
export function ChecklistSection({ projectId, checklist }: { projectId: string; checklist: Checklist }) {
    const { t } = useTranslation();
    const [action, setAction] = useState<Action | null>(null);

    return (
        <section aria-labelledby="checklist-title" className="flex min-w-0 flex-col gap-2">
            <div className="flex min-w-0 flex-wrap items-center justify-between gap-2">
                <h3 id="checklist-title" className="text-label font-semibold text-text-primary">{t('adm.checklist.title')}</h3>
                {checklist.can.manage && (
                    <Button variant="outline" size="sm" onClick={() => { setAction({ kind: 'add' }); }}>{t('adm.action.add')}</Button>
                )}
            </div>
            {checklist.items.length === 0 ? (
                <RegionEmpty text={t('adm.checklist.empty')} />
            ) : (
                <ul role="list" aria-labelledby="checklist-title" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                    {checklist.items.map((item) => <RequirementRow key={item.handle} item={item} can={checklist.can} onAction={setAction} />)}
                </ul>
            )}
            <RequirementCommands projectId={projectId} action={action} options={checklist.options} onClose={() => { setAction(null); }} />
        </section>
    );
}
