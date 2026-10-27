// PG-038 *Bukti* (ROUTE-038, ROUTE-228–ROUTE-231; NAV-038; SCR-36; IA §8.4; J-DOC-02; IP-16; TASK-030): evidence one branch
// at a time — a company in scope, or the shared warehouse's pool evidence —, its versions and links. "Unggah" opens the
// upload sheet: the file streamed by ROUTE-228 outside any command with its progress and "Batalkan", then the
// registration (ROUTE-229) with the types of the families the account may view; an identity already in the company
// offers "Tambahkan sebagai Versi Baru", a visible duplicate is IP-05's warning, a pool upload needs MSG-32's tick. An
// evidence's sheet lists its versions — each opened only as a download, an image by its re-encoded preview — with
// "Jadikan Versi Berlaku", and its links with their reasoned unlink. Each command's answer in place (IP-01).
import { router, usePage } from '@inertiajs/react';
import { Download, Link2, Plus, Upload } from 'lucide-react';
import { type ReactNode, useRef, useState } from 'react';

import { DataList, ListContinuation } from '@/components/data-list';
import { FileThumbnail } from '@/components/file-thumbnail';
import { DecimalInput, FormField, FormSection } from '@/components/form';
import { Lookup, type LookupGroup, type LookupResult } from '@/components/lookup';
import { DuplicateWarning, type DuplicateMatch, UploadField, type UploadState } from '@/components/notices';
import { type Answer, OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { DateText, Money } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { uploadFile } from '@/lib/file-upload';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import type { AccountHints } from '@/lib/navigation';
import { search } from '@/lib/search-client';
import { useCommand } from '@/lib/use-command';

type Branch = { id: string; code: string | null; name: string | null; types: string[] };
type Row = { id: string; type: string; issuer: string; reference: string | null; date: string; amount: string | null; version: number | null; versions: number; links: number; image: boolean; reproduction: boolean };
type Version = { number: number; current: boolean; file: string; name: string | null; image: boolean; reason: string | null; uploader: string | null; recorded_at: string; reproduction: boolean };
type EvidenceLink = { handle: string; target: string; record: string | null; label: string | null; role: string; linked_at: string };
type Detail = { id: string; type: string; pool: boolean; issuer: string; reference: string | null; date: string; amount: string | null; deliverer: string | null; received: string | null; versions: Version[]; links: EvidenceLink[] };
type Props = { branches: Branch[]; branch: string | null; rows: Row[]; more: string | null; evidence: Detail | null; override: boolean; amountTypes: string[]; poolTypes: string[] };
type MatchLabel = { id: string; kind: 'evidence' | 'rendition'; type: string; reference: string | null; date: string | null; branch: string | null };

const POOL = 'bersama';
const ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx,application/pdf,image/jpeg,image/png,image/webp';
const ROLES = ['PRIMARY_PROOF', 'SUPPORTING', 'CLIENT_ORIGINAL', 'REVIEWED'] as const;

const typeName = (type: string): TranslationKey => `evidence.type.${type}` as TranslationKey;
const roleName = (role: string): TranslationKey => `evidence.role.${role}` as TranslationKey;
const targetName = (target: string): TranslationKey => `evidence.target.${target}` as TranslationKey;

function visit(query: Record<string, string | null>) {
    const params = new URLSearchParams(window.location.search);

    for (const [key, value] of Object.entries(query)) {
        if (value === null) {
            params.delete(key);
        } else {
            params.set(key, value);
        }
    }

    router.get(`/dokumen/bukti${params.size > 0 ? `?${params.toString()}` : ''}`, {}, { preserveScroll: true, preserveState: true });
}

/** ROUTE-228's upload of one file for evidence of the branch, its state for IP-16's field. */
function useUpload(fields: () => Record<string, string>) {
    const { t } = useTranslation();
    const [state, setState] = useState<UploadState>({ phase: 'idle' });
    const [file, setFile] = useState<string | null>(null);
    const abort = useRef<AbortController | null>(null);
    const last = useRef<File | null>(null);

    const choose = (chosen: File) => {
        last.current = chosen;
        abort.current = new AbortController();
        setFile(null);
        setState({ phase: 'uploading', progress: 0 });
        void uploadFile('/berkas', chosen, 'file', (progress) => {
            setState({ phase: 'uploading', progress });
        }, abort.current.signal, fields()).then((answer) => {
            if (answer.kind === 'stored') {
                setFile(answer.id);
                setState({ phase: 'done', name: answer.name });
            } else if (answer.kind === 'refused') {
                setState({ phase: 'error', message: answer.message });
            } else if (answer.kind === 'cancelled') {
                setState({ phase: 'idle' });
            } else {
                setState({ phase: 'error', message: t('messages.msg_07') });
            }
        });
    };

    return {
        state,
        file,
        choose,
        cancel: () => abort.current?.abort(),
        reset: () => {
            setFile(null);
            setState({ phase: 'idle' });
        },
        retry: () => {
            if (last.current !== null) {
                choose(last.current);
            }
        },
    };
}

/** A command's answer in place (IP-01), with "Coba Lagi" sending the same `command_id` and payload again while its outcome
    is unknown (IP-02; IP-04) — never a second command —, or the action its answer offers. */
function CommandAnswer({ command, action }: { command: { answer: Answer | null; unknown: boolean; retry: () => void }; action?: ReactNode }) {
    const { t } = useTranslation();

    if (command.answer === null) return null;

    return (
        <OutcomeMessage
            answer={command.answer}
            action={
                command.unknown ? (
                    <Button variant="outline" size="sm" onClick={command.retry}>
                        {t('actions.try_again')}
                    </Button>
                ) : (
                    action
                )
            }
        />
    );
}

/** IP-05's block for the matches the server named, each with its link where its page is the evidence's. */
function Warning({ state, acknowledged, onAcknowledge, reason, onReason, error }: { state: Record<string, unknown>; acknowledged: string[]; onAcknowledge: (ids: string[]) => void; reason: string; onReason: (value: string) => void; error?: string }) {
    const { t } = useTranslation();
    const labels = Array.isArray(state.labels) ? (state.labels as MatchLabel[]) : [];

    if (!Array.isArray(state.matches) || labels.length === 0) {
        return null;
    }

    const describe = (label: MatchLabel): string =>
        [label.kind === 'rendition' ? t(`documents.doc_${label.type.slice(4)}` as TranslationKey) : t(typeName(label.type)), label.reference, label.date].filter((part) => part !== null && part !== '').join(' · ');
    const matches: DuplicateMatch[] = labels.map((label) => ({
        id: label.id,
        label: describe(label),
        href: label.kind === 'evidence' && label.branch !== null ? `/dokumen/bukti?cabang=${label.branch}&bukti=${label.id}` : null,
    }));
    const list = labels.map(describe).join('; ');
    const override = state.override === true;

    return (
        <DuplicateWarning
            matches={matches}
            message={override ? t('messages.msg_15', { daftar_catatan_yang_cocok: list }) : t('messages.msg_15_no_authority', { daftar_catatan_yang_cocok: list, catatan: t('glossary.gl_122').toLowerCase() })}
            acknowledged={acknowledged}
            onAcknowledge={onAcknowledge}
            reason={{ value: reason, onChange: onReason, error }}
            canContinue={override}
        />
    );
}

function UploadSheet({ open, onOpenChange, branch, branches, amountTypes, poolTypes }: { open: boolean; onOpenChange: (open: boolean) => void; branch: Branch | null; branches: Branch[]; amountTypes: string[]; poolTypes: string[] }) {
    const { t } = useTranslation();
    const [chosen, setChosen] = useState<string>(branch?.id ?? '');
    const target = branches.find((candidate) => candidate.id === chosen) ?? branch;
    const pool = target?.id === POOL;
    const upload = useUpload((): Record<string, string> => (pool ? { purpose: 'bukti', pool: '1' } : { purpose: 'bukti', company: target?.id ?? '' }));
    const [type, setType] = useState('');
    const [issuer, setIssuer] = useState('');
    const [reference, setReference] = useState('');
    const [date, setDate] = useState('');
    const [amount, setAmount] = useState('');
    const [deliverer, setDeliverer] = useState('');
    const [received, setReceived] = useState('');
    const [ticked, setTicked] = useState(false);
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [reason, setReason] = useState('');
    const register = useCommand<Record<string, string | boolean | string[] | null>>(() => '/bukti', 'post');
    const existing = typeof register.state.evidence === 'string' ? register.state.evidence : null;
    const asVersion = useCommand<Record<string, string | string[] | null>>(() => `/bukti/${existing ?? ''}/versi`, 'post');
    const types = target?.types ?? [];

    const close = () => {
        upload.reset();
        register.clear();
        asVersion.clear();
        setAcknowledged([]);
        setReason('');
        onOpenChange(false);
    };

    return (
        <Sheet
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    onOpenChange(true);
                } else {
                    close();
                }
            }}
        >
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t('shell.create.upload')}</SheetTitle>
                    <SheetDescription>{t('evidence.upload_line')}</SheetDescription>
                </SheetHeader>
                <form
                    className="flex min-w-0 flex-col gap-6"
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (upload.file === null) return;

                        register.submit(
                            {
                                file: upload.file,
                                company: pool ? null : (target?.id ?? null),
                                pool,
                                pool_confirmed: pool ? ticked : false,
                                type,
                                issuer,
                                reference: reference === '' ? null : reference,
                                date,
                                amount: amountTypes.includes(type) ? amount : null,
                                deliverer: deliverer === '' ? null : deliverer,
                                received: received === '' ? null : received,
                                acknowledged,
                                reason: reason === '' ? null : reason,
                            },
                            close,
                        );
                    }}
                >
                    <FormSection title={t('evidence.section.file')}>
                        <FormField label={t('evidence.field.branch')} required>
                            {(control) => (
                                <NativeSelect
                                    {...control}
                                    value={target?.id ?? ''}
                                    disabled={upload.state.phase !== 'idle'}
                                    onChange={(event) => {
                                        setChosen(event.target.value);
                                        setType('');
                                    }}
                                >
                                    {branches.map((option) => (
                                        <option key={option.id} value={option.id}>
                                            {option.id === POOL ? t('glossary.gl_125') : `${option.code ?? ''} · ${option.name ?? ''}`}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>
                        <UploadField
                            label={t('glossary.gl_123')}
                            accept={ACCEPT}
                            limits={t('evidence.limits')}
                            state={upload.state}
                            onChoose={upload.choose}
                            onCancel={upload.cancel}
                            onRemove={upload.reset}
                            onRetry={upload.retry}
                        />
                    </FormSection>
                    <FormSection title={t('evidence.section.identity')}>
                        <FormField label={t('evidence.field.type')} required error={register.errors.type}>
                            {(control) => (
                                <NativeSelect {...control} value={type} onChange={(event) => { setType(event.target.value); }}>
                                    <option value="">{t('forms.choose')}</option>
                                    {types.filter((option) => !pool || poolTypes.includes(option)).map((option) => (
                                        <option key={option} value={option}>
                                            {t(typeName(option))}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>
                        <FormField label={t('evidence.field.issuer')} required error={register.errors.issuer}>
                            {(control) => <Input {...control} value={issuer} maxLength={200} onChange={(event) => { setIssuer(event.target.value); }} />}
                        </FormField>
                        <FormField label={t('evidence.field.reference')} help={t('evidence.field.reference_help')} error={register.errors.reference}>
                            {(control) => <Input {...control} value={reference} maxLength={120} onChange={(event) => { setReference(event.target.value); }} />}
                        </FormField>
                        <FormField label={t('evidence.field.date')} required error={register.errors.date}>
                            {(control) => <Input {...control} type="date" value={date} onChange={(event) => { setDate(event.target.value); }} />}
                        </FormField>
                        {amountTypes.includes(type) && (
                            <FormField label={t('evidence.field.amount')} required error={register.errors.amount}>
                                {(control) => <DecimalInput {...control} scale={2} value={amount} onValueChange={(decimal) => { setAmount(decimal ?? ''); }} />}
                            </FormField>
                        )}
                        <FormField label={t('evidence.field.deliverer')} error={register.errors.deliverer}>
                            {(control) => <Input {...control} value={deliverer} maxLength={200} onChange={(event) => { setDeliverer(event.target.value); }} />}
                        </FormField>
                        <FormField label={t('evidence.field.received')} error={register.errors.received}>
                            {(control) => <Input {...control} type="date" value={received} onChange={(event) => { setReceived(event.target.value); }} />}
                        </FormField>
                        {pool && (
                            <div className="flex items-start gap-3">
                                <Checkbox id="pool-confirmed" checked={ticked} onCheckedChange={(checked) => { setTicked(checked); }} aria-describedby="pool-confirmed-error" />
                                <label htmlFor="pool-confirmed" className="text-body text-text-primary">
                                    {t('messages.msg_32')}
                                </label>
                            </div>
                        )}
                        {register.errors.pool_confirmed !== undefined && (
                            <p id="pool-confirmed-error" className="text-body text-danger-fg">
                                {register.errors.pool_confirmed}
                            </p>
                        )}
                    </FormSection>
                    <Warning state={register.state} acknowledged={acknowledged} onAcknowledge={setAcknowledged} reason={reason} onReason={setReason} error={register.errors.reason} />
                    {register.answer !== null && (
                        <CommandAnswer
                            command={register}
                            action={
                                existing !== null && upload.file !== null ? (
                                    <Button variant="outline" size="sm" pending={asVersion.pending} onClick={() => { asVersion.submit({ file: upload.file, note: null, acknowledged: [], reason: null }, close); }}>
                                        {t('evidence.add_version')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    )}
                    <CommandAnswer command={asVersion} />
                    <div className="flex flex-wrap justify-end gap-3">
                        <Button type="button" variant="ghost" onClick={close}>
                            {t('actions.cancel')}
                        </Button>
                        <Button type="submit" disabled={upload.file === null || register.frozen} pending={register.pending}>
                            {t('evidence.register')}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}

/** The link form: a project the account may see, found by ROUTE-281, and the link's role. */
function LinkForm({ evidence }: { evidence: Detail }) {
    const { t } = useTranslation();
    const [term, setTerm] = useState('');
    const [groups, setGroups] = useState<LookupGroup[]>([]);
    const [project, setProject] = useState<LookupResult | null>(null);
    const [role, setRole] = useState<string>('SUPPORTING');
    const link = useCommand<Record<string, string>>(() => `/bukti/${evidence.id}/tautan`, 'post');

    const pending = useRef<{ timer: number; controller: AbortController } | null>(null);
    // ROUTE-281's projects for the term, a quarter second after the last keystroke, the earlier request abandoned.
    const find = (text: string) => {
        setTerm(text);

        if (pending.current !== null) {
            window.clearTimeout(pending.current.timer);
            pending.current.controller.abort();
        }

        if (text.trim().length < 2) {
            setGroups([]);

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            void search(text, ['project'], controller.signal).then((outcome) => {
                if (outcome.kind === 'answer') {
                    const rows = [...outcome.answer.exact, ...outcome.answer.groups.flatMap((group) => group.rows)];
                    setGroups([{ value: 'project', label: t('glossary.gl_004'), items: rows.map((row) => ({ id: row.id, code: row.code ?? '', label: row.label })) }]);
                }
            });
        }, 250);
        pending.current = { timer, controller };
    };

    return (
        <form
            className="flex min-w-0 flex-col gap-3"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();

                if (project === null) return;

                link.submit({ action: 'link', project: project.id, role }, () => {
                    setProject(null);
                    setTerm('');
                });
            }}
        >
            <Lookup label={t('evidence.link_project')} term={term} onTermChange={find} groups={groups} value={project} onChoose={setProject} />
            <FormField label={t('evidence.field.role')} required error={link.errors.role}>
                {(control) => (
                    <NativeSelect {...control} value={role} onChange={(event) => { setRole(event.target.value); }}>
                        {ROLES.map((option) => (
                            <option key={option} value={option}>
                                {t(roleName(option))}
                            </option>
                        ))}
                    </NativeSelect>
                )}
            </FormField>
            <CommandAnswer command={link} />
            <div>
                <Button type="submit" variant="outline" size="sm" disabled={project === null || link.frozen} pending={link.pending}>
                    <Icon icon={Link2} size="sm" />
                    {t('evidence.link')}
                </Button>
            </div>
        </form>
    );
}

function UnlinkControl({ evidence, link }: { evidence: Detail; link: EvidenceLink }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const unlink = useCommand<Record<string, string>>(() => `/bukti/${evidence.id}/tautan`, 'post');

    if (!open) {
        return (
            <Button variant="ghost" size="sm" onClick={() => { setOpen(true); }}>
                {t('evidence.unlink')}
            </Button>
        );
    }

    return (
        <form
            className="flex w-full min-w-0 flex-col gap-2"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                unlink.submit({ action: 'unlink', link: link.handle, reason }, () => { setOpen(false); });
            }}
        >
            <FormField label={t('evidence.unlink_reason')} required error={unlink.errors.reason}>
                {(control) => <Textarea {...control} value={reason} maxLength={500} onChange={(event) => { setReason(event.target.value); }} />}
            </FormField>
            <CommandAnswer command={unlink} />
            <div className="flex gap-2">
                <Button type="submit" variant="destructive" size="sm" pending={unlink.pending} disabled={unlink.frozen}>
                    {t('evidence.unlink')}
                </Button>
                <Button type="button" variant="ghost" size="sm" onClick={() => { setOpen(false); }}>
                    {t('actions.cancel')}
                </Button>
            </div>
        </form>
    );
}

function VersionRow({ evidence, version, canUpload }: { evidence: Detail; version: Version; canUpload: boolean }) {
    const { t } = useTranslation();
    const replace = useCommand<Record<string, string | number>>(() => `/bukti/${evidence.id}/tautan`, 'post');

    return (
        <li className="flex min-w-0 flex-col gap-2 py-3">
            <div className="flex min-w-0 items-center gap-3">
                {version.image && <FileThumbnail fileId={version.file} alt={version.name ?? ''} />}
                <div className="flex min-w-0 flex-1 flex-col">
                    <span className="flex min-w-0 items-center gap-2 text-body font-medium text-text-primary">
                        {t('evidence.version_n', { nomor: String(version.number) })}
                        {version.current && <Badge tone="success">{t('evidence.current')}</Badge>}
                    </span>
                    <span className="min-w-0 truncate text-caption text-text-tertiary">
                        {[version.name, version.uploader].filter((part) => part !== null).join(' · ')}
                    </span>
                    {version.reproduction && <span className="text-caption text-warning-fg">{t('evidence.reproduction')}</span>}
                    {version.reason !== null && <span className="text-caption text-text-secondary">{version.reason}</span>}
                </div>
                <Button variant="outline" size="sm" render={<a href={`/berkas/${version.file}/unduh`} />}>
                    <Icon icon={Download} size="sm" />
                    {t('actions.download')}
                </Button>
            </div>
            {!version.current && canUpload && (
                <div>
                    <Button variant="outline" size="sm" pending={replace.pending} disabled={replace.frozen} onClick={() => { replace.submit({ action: 'replace', version: version.number }); }}>
                        {t('actions.make_current_version')}
                    </Button>
                </div>
            )}
            <CommandAnswer command={replace} />
        </li>
    );
}

function AddVersion({ evidence, branch }: { evidence: Detail; branch: string | null }) {
    const { t } = useTranslation();
    const upload = useUpload((): Record<string, string> => (evidence.pool ? { purpose: 'bukti', pool: '1', type: evidence.type } : { purpose: 'bukti', company: branch ?? '', type: evidence.type }));
    const [note, setNote] = useState('');
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [reason, setReason] = useState('');
    const version = useCommand<Record<string, string | string[] | null>>(() => `/bukti/${evidence.id}/versi`, 'post');

    return (
        <form
            className="flex min-w-0 flex-col gap-3"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();

                if (upload.file === null) return;

                version.submit({ file: upload.file, note: note === '' ? null : note, acknowledged, reason: reason === '' ? null : reason }, () => {
                    upload.reset();
                    setNote('');
                });
            }}
        >
            <UploadField label={t('evidence.add_version')} accept={ACCEPT} limits={t('evidence.limits')} state={upload.state} onChoose={upload.choose} onCancel={upload.cancel} onRemove={upload.reset} onRetry={upload.retry} />
            <FormField label={t('evidence.field.note')} error={version.errors.note}>
                {(control) => <Input {...control} value={note} maxLength={500} onChange={(event) => { setNote(event.target.value); }} />}
            </FormField>
            <Warning state={version.state} acknowledged={acknowledged} onAcknowledge={setAcknowledged} reason={reason} onReason={setReason} error={version.errors.reason} />
            <CommandAnswer command={version} />
            <div>
                <Button type="submit" variant="outline" size="sm" disabled={upload.file === null || version.frozen} pending={version.pending}>
                    <Icon icon={Plus} size="sm" />
                    {t('evidence.add_version')}
                </Button>
            </div>
        </form>
    );
}

function EvidenceSheet({ evidence, branch, canUpload }: { evidence: Detail; branch: string | null; canUpload: boolean }) {
    const { t } = useTranslation();
    const facts: [string, ReactNode][] = [
        [t('evidence.field.issuer'), evidence.issuer],
        [t('evidence.field.reference'), evidence.reference ?? '—'],
        [t('evidence.field.date'), <DateText key="date" value={evidence.date} />],
        ...(evidence.amount !== null ? [[t('evidence.field.amount'), <Money key="amount" value={evidence.amount} />] as [string, ReactNode]] : []),
        ...(evidence.deliverer !== null ? [[t('evidence.field.deliverer'), evidence.deliverer] as [string, ReactNode]] : []),
        ...(evidence.received !== null ? [[t('evidence.field.received'), <DateText key="received" value={evidence.received} />] as [string, ReactNode]] : []),
    ];

    return (
        <Sheet
            open
            onOpenChange={(open) => {
                if (!open) visit({ bukti: null });
            }}
        >
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t(typeName(evidence.type))}</SheetTitle>
                    <SheetDescription>{evidence.pool ? t('glossary.gl_125') : evidence.issuer}</SheetDescription>
                </SheetHeader>
                <dl className="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                    {facts.map(([label, value]) => (
                        <div key={label} className="flex min-w-0 flex-col gap-1">
                            <dt className="text-caption text-text-tertiary">{label}</dt>
                            <dd className="min-w-0 text-body text-text-primary">{value}</dd>
                        </div>
                    ))}
                </dl>
                <FormSection title={t('evidence.versions')}>
                    <ul className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {evidence.versions.map((version) => (
                            <VersionRow key={version.number} evidence={evidence} version={version} canUpload={canUpload} />
                        ))}
                    </ul>
                    {canUpload && <AddVersion evidence={evidence} branch={branch} />}
                </FormSection>
                <FormSection title={t('evidence.links')}>
                    {evidence.links.length === 0 ? (
                        <p className="text-body text-text-secondary">{t('evidence.no_links')}</p>
                    ) : (
                        <ul className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {evidence.links.map((link) => (
                                <li key={link.handle} className="flex min-w-0 flex-wrap items-center gap-3 py-3">
                                    <span className="flex min-w-0 flex-1 flex-col">
                                        <span className="text-body text-text-primary">{link.label ?? t(targetName(link.target))}</span>
                                        <span className="text-caption text-text-tertiary">{t(roleName(link.role))}</span>
                                    </span>
                                    {canUpload && <UnlinkControl evidence={evidence} link={link} />}
                                </li>
                            ))}
                        </ul>
                    )}
                    {canUpload && !evidence.pool && <LinkForm evidence={evidence} />}
                </FormSection>
            </SheetContent>
        </Sheet>
    );
}

export default function Evidence({ branches, branch, rows, more, evidence, amountTypes, poolTypes }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage<{ auth: AccountHints }>().props;
    const canUpload = auth.capabilities.includes('evidence.upload');
    const [uploading, setUploading] = useState(false);
    const current = branches.find((candidate) => candidate.id === branch) ?? null;
    // PT-08: "Muat Lagi" asks for the page after the rows shown on a partial reload, the address unchanged, and adds it
    // below them; any other visit — a branch, a command's answer — shows its own first page.
    const [base, setBase] = useState(rows);
    const [shown, setShown] = useState(rows);
    const [next, setNext] = useState(more);
    const [appending, setAppending] = useState(false);

    if (base !== rows) {
        setBase(rows);
        setShown(appending ? [...shown, ...rows.filter((row) => !shown.some((known) => known.id === row.id))] : rows);
        setNext(more);
        setAppending(false);
    }

    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        router.reload({ only: ['rows', 'more'], data: { setelah: next }, preserveUrl: true });
    };
    const uploadAction = canUpload ? (
        <Button onClick={() => { setUploading(true); }}>
            <Icon icon={Upload} size="sm" />
            {t('glossary.gl_123')}
        </Button>
    ) : undefined;

    return (
        <AppShell title={t('pages.pg_038.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_038.title')} primary={uploadAction} />
                <div className="flex min-w-0 flex-wrap items-end gap-3">
                    <FormField label={t('evidence.field.branch')} className="min-w-0 sm:w-80">
                        {(control) => (
                            <NativeSelect {...control} value={branch ?? ''} onChange={(event) => { visit({ cabang: event.target.value, setelah: null, bukti: null }); }}>
                                {branches.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.id === POOL ? t('glossary.gl_125') : `${option.code ?? ''} · ${option.name ?? ''}`}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>
                    {canUpload && (
                        <Button variant="outline" className="md:hidden" onClick={() => { setUploading(true); }}>
                            <Icon icon={Upload} size="sm" />
                            {t('glossary.gl_123')}
                        </Button>
                    )}
                </div>
                {shown.length === 0 ? (
                    <RegionEmpty text={t('evidence.empty')} action={uploadAction} />
                ) : (
                    <>
                        <DataList<Row>
                            caption={t('pages.pg_038.title')}
                            rows={shown}
                            rowKey={(row) => row.id}
                            rowHref={(row) => `/dokumen/bukti?${new URLSearchParams({ ...(branch !== null ? { cabang: branch } : {}), bukti: row.id }).toString()}`}
                            columns={[
                                { key: 'type', header: t('evidence.field.type'), cell: (row) => t(typeName(row.type)) },
                                { key: 'issuer', header: t('evidence.field.issuer'), cell: (row) => row.issuer },
                                { key: 'reference', header: t('evidence.field.reference'), cell: (row) => row.reference ?? '—' },
                                { key: 'date', header: t('evidence.field.date'), cell: (row) => <DateText value={row.date} /> },
                                { key: 'amount', header: t('evidence.field.amount'), numeric: true, cell: (row) => (row.amount === null ? '—' : <Money value={row.amount} />) },
                                { key: 'versions', header: t('evidence.versions'), numeric: true, priority: 'low', cell: (row) => row.versions },
                                { key: 'links', header: t('evidence.links'), numeric: true, priority: 'low', cell: (row) => row.links },
                            ]}
                            dense={(row) => ({
                                title: t(typeName(row.type)),
                                figure: row.amount === null ? undefined : <Money value={row.amount} />,
                                secondary: [row.issuer, row.reference].filter((part) => part !== null).join(' · '),
                                status: <DateText value={row.date} />,
                            })}
                        />
                        <ListContinuation state={next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={loadMore} />
                    </>
                )}
            </div>
            <UploadSheet open={uploading} onOpenChange={setUploading} branch={current} branches={branches.filter((option) => option.types.length > 0)} amountTypes={amountTypes} poolTypes={poolTypes} />
            {evidence !== null && <EvidenceSheet key={evidence.id} evidence={evidence} branch={branch} canUpload={canUpload} />}
        </AppShell>
    );
}
