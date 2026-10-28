// PG-014 *Penawaran* (ROUTE-014, ROUTE-154–ROUTE-157; NAV-014; SCR-13; IA §8.2; WF-QUO-01; J-QUO-01; TASK-033): the
// project's quotation in the document composer — the draft's validity and lines on the left, its live paper on the
// right from 1,025 px (D-DS-23; INT-012) —; "Simpan Draf" at the version read, a CONFLICT showing the newer version
// beside the input with "Terapkan Ulang pada Versi Terbaru" (IP-03; UXS-11); "Terbitkan Penawaran" after its confirmation
// (IP-08); on a sent revision "Catat Jawaban Klien" — on the phone its quick action —, on any non-draft one "Buat
// Revisi"; the revision chain with each state, *Kedaluwarsa* derived (L-25), and its DOC-01 number. No control voids a
// quotation (BR-CR-06), and nothing here reserves stock or records money (BR-QUO-03).
import { router } from '@inertiajs/react';
import { CopyPlus, MessageSquareReply, Send, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { ActionBar } from '@/components/action-bar';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { BusinessDateControl, type BusinessDate, DateField } from '@/components/date-field';
import { DecimalInput, FormField, FormSection } from '@/components/form';
import { StaleComparison, type StaleField } from '@/components/notices';
import { OutcomeMessage, type Answer } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { Code, DateText, DateTimeText, Money } from '@/components/values';
import { DocumentComposer } from '@/features/doc/composer';
import { type Paper } from '@/features/doc/paper';
import { lineValue, livePaper, revisionState, type RevisionState, type TaxRule } from '@/features/prj/quotation';
import AppShell from '@/layouts/AppShell';
import { add } from '@/lib/decimal';
import { dateLong, quantity as formatQuantity } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { type CommandForm, useCommand } from '@/lib/use-command';

type Line = { handle: string; product: string; description: string; quantity: string; unit: string; ratio: number; scale: number; price?: string; value?: string };
type Current = { id: string; no: number; state: Exclude<RevisionState, 'EXPIRED'>; valid_until: string | null; lock_version: number; lines: Line[]; paper: Paper | null; facts: string | null };
type Revision = { id: string; no: number; state: RevisionState; valid_until: string | null; decided_at: string | null; decided_by: string | null; reason: string | null; number: string | null; document: string | null };
type Props = {
    project: { id: string; number: string; title: string; company: string; client: string; state: string } | null;
    revisions: Revision[];
    current: Current | null;
    taxes: TaxRule[];
    today: string;
    can: { manage: boolean; approve: boolean; reject: boolean };
};
type Entered = { handle: string; product: string; unit: string; description: string; quantity: string | null; price: string | null };
type Payload = Record<string, string | number | boolean | null | Record<string, string | null>[]>;

function enteredOf(current: Current): Entered[] {
    return current.lines.map((line) => ({ handle: line.handle, product: line.product, unit: line.unit, description: line.description, quantity: line.quantity, price: line.price ?? null }));
}

function signature(lines: Entered[]): string {
    return lines.map((line) => [line.handle, line.quantity ?? '', line.price ?? '', line.description].join('|')).join('\n');
}

function Answered({ command }: { command: { answer: Answer | null; unknown: boolean; retry: () => void } }) {
    const { t } = useTranslation();

    return command.answer === null ? null : (
        <OutcomeMessage
            answer={command.answer}
            action={command.unknown ? <Button variant="outline" size="sm" onClick={command.retry}>{t('actions.try_again')}</Button> : undefined}
        />
    );
}

// The draft's form keeps the version it was read at: a newer one (another Admin's save) never replaces the input silently (IP-03).
function DraftComposer({ project, draft, taxes, today, money, issue }: { project: NonNullable<Props['project']>; draft: Current; taxes: TaxRule[]; today: string; money: boolean; issue: CommandForm<Payload> }) {
    const { t } = useTranslation();
    const name = t('quotations.paper');
    const save = useCommand<Payload>(() => `/proyek/${project.id}/penawaran`, 'post');
    const [base, setBase] = useState<Current>(draft);
    const [own, setOwn] = useState(false);
    const [validUntil, setValidUntil] = useState<string | null>(draft.valid_until);
    const [lines, setLines] = useState<Entered[]>(() => enteredOf(draft));
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [confirming, setConfirming] = useState(false);
    const [dirty, setDirty] = useState(false);

    // This form's own save brings the version it wrote: the form follows it.
    if (own && draft.lock_version !== base.lock_version) {
        setBase(draft);
        setOwn(false);
    }

    // A visit away with unsent input asks first (MSG-35; NV-02).
    useEffect(() => {
        const unload = (event: BeforeUnloadEvent) => {
            if (dirty) event.preventDefault();
        };
        window.addEventListener('beforeunload', unload);

        return () => {
            window.removeEventListener('beforeunload', unload);
        };
    }, [dirty]);

    const update = (index: number, patch: Partial<Entered>) => {
        setLines(lines.map((line, at) => (at === index ? { ...line, ...patch } : line)));
        setDirty(true);
    };
    const paper = useMemo<Paper | null>(() => (draft.paper === null ? null : livePaper(draft.paper, lines.map((line) => ({ description: line.description, quantity: line.quantity, unit: line.unit, price: money ? line.price : null })), taxes, validUntil, date.date)), [draft.paper, lines, taxes, validUntil, date.date, money]);
    const total = add('0', ...lines.map((line) => lineValue({ ...line, price: money ? line.price : null }) ?? '0'));
    const payload: Payload = {
        revisi: base.id,
        lock_version: base.lock_version,
        berlaku: validUntil,
        baris: lines.map((line) => ({ baris: line.handle, jumlah: line.quantity ?? '', harga: money ? line.price : null, keterangan: line.description })),
    };
    const submit = () => {
        save.submit(payload, () => {
            setOwn(true);
            setDirty(false);
        });
    };
    // A newer version than the one this form shows: its values beside this form's, marked where they differ (IP-03; MSG-03).
    const newer = draft.lock_version !== base.lock_version && !own ? draft : null;
    const mineSignature = signature(lines);
    const stale: StaleField[] | null = newer === null ? null : [
        { key: 'valid', label: t('quotations.field.valid_until'), current: newer.valid_until === null ? '—' : dateLong(newer.valid_until), mine: validUntil === null ? '—' : dateLong(validUntil),
            differs: newer.valid_until !== validUntil, bothChanged: validUntil !== base.valid_until && newer.valid_until !== base.valid_until && validUntil !== newer.valid_until },
        { key: 'lines', label: t('quotations.form.lines'), current: t('quotations.lines', { n: newer.lines.length }), mine: t('quotations.lines', { n: lines.length }),
            differs: signature(enteredOf(newer)) !== mineSignature, bothChanged: mineSignature !== signature(enteredOf(base)) && signature(enteredOf(newer)) !== signature(enteredOf(base)) && signature(enteredOf(newer)) !== mineSignature },
    ];
    // Each part this form did not change takes the newer version's; its own changes stay (IP-03).
    const reapply = () => {
        if (newer === null) return;
        if (validUntil === base.valid_until) setValidUntil(newer.valid_until);
        if (mineSignature === signature(enteredOf(base))) setLines(enteredOf(newer));
        setBase(newer);
        save.clear();
    };
    const discardMine = () => {
        setDirty(false);
        router.visit(window.location.pathname, { preserveState: false });
    };
    const lineError = (index: number, field: string): string | undefined => save.errors[`baris.${String(index)}.${field}`];

    const form = (
        <div className="flex min-w-0 flex-col gap-6">
            {stale !== null && <StaleComparison fields={stale} onReapply={reapply} onDiscard={discardMine} />}
            <FormSection title={t('quotations.form.title', { n: base.no })}>
                <FormField label={t('quotations.field.valid_until')} required error={save.errors.berlaku ?? issue.errors.berlaku} help={t('quotations.form.valid_help')}>
                    {(control) => <DateField {...control} value={validUntil} onValueChange={(day) => { setValidUntil(day); setDirty(true); }} />}
                </FormField>
            </FormSection>
            <FormSection title={t('quotations.form.lines')}>
                {lines.length === 0 ? (
                    <p className="text-body text-text-secondary">{t('quotations.form.no_lines')}</p>
                ) : (
                    <ol aria-label={t('quotations.form.lines')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {lines.map((line, index) => (
                            <li key={line.handle} id={`line-${String(index)}`} className="grid min-w-0 gap-3 py-4 sm:grid-cols-2">
                                <div className="flex min-w-0 items-start justify-between gap-2 sm:col-span-2">
                                    <span className="min-w-0 font-semibold text-text-primary">{`${String(index + 1)}. ${line.product}`}</span>
                                    <Button variant="ghost" size="sm" disabled={lines.length === 1} aria-label={t('quotations.form.remove', { n: index + 1 })} onClick={() => { setLines(lines.filter((_, at) => at !== index)); setDirty(true); }}>
                                        <Icon icon={Trash2} size="sm" />
                                    </Button>
                                </div>
                                <FormField label={t('quotations.field.description')} className="sm:col-span-2" error={lineError(index, 'keterangan')}>
                                    {(control) => <Input {...control} value={line.description} onChange={(event) => { update(index, { description: event.target.value }); }} />}
                                </FormField>
                                <FormField label={t('quotations.field.quantity', { satuan: line.unit })} required error={lineError(index, 'jumlah')}>
                                    {(control) => <DecimalInput {...control} value={line.quantity ?? ''} scale={3} unit={line.unit} onValueChange={(value) => { update(index, { quantity: value }); }} />}
                                </FormField>
                                {money && (
                                    <FormField label={t('quotations.field.price')} required error={lineError(index, 'harga')}>
                                        {(control) => <DecimalInput {...control} value={line.price ?? ''} scale={2} kind="money" onValueChange={(value) => { update(index, { price: value }); }} />}
                                    </FormField>
                                )}
                            </li>
                        ))}
                    </ol>
                )}
                {money && (
                    <p className="flex justify-between gap-3 text-body text-text-primary">
                        <span>{t('quotations.form.subtotal')}</span>
                        <Money value={total} />
                    </p>
                )}
                {(save.errors.baris ?? '') !== '' && <p className="text-body text-danger">{save.errors.baris}</p>}
                <p className="text-body text-text-secondary">{t('quotations.form.subset')}</p>
            </FormSection>
        </div>
    );
    const finalize = (
        <div className="flex min-w-0 flex-col gap-3">
            <Answered command={save} />
            <div className="flex flex-wrap gap-2">
                <Button variant="outline" pending={save.pending} disabled={save.frozen || newer !== null} onClick={submit}>
                    {t('composer.save_draft')}
                </Button>
                <Button disabled={dirty || newer !== null || lines.length === 0} onClick={() => { issue.clear(); setConfirming(true); }}>
                    <Icon icon={Send} size="sm" />
                    {t('quotations.action.issue')}
                </Button>
            </div>
            {dirty && <p className="text-caption text-text-secondary">{t('quotations.form.save_first')}</p>}
        </div>
    );

    return (
        <>
            {paper === null ? (
                <div className="flex min-w-0 flex-col gap-6">
                    {form}
                    {finalize}
                </div>
            ) : (
                <DocumentComposer name={name} paper={paper} form={form} finalize={finalize} />
            )}
            <ConfirmCommand
                open={confirming}
                onOpenChange={setConfirming}
                title={t('quotations.confirm.issue_title')}
                consequence={t('quotations.confirm.issue_consequence')}
                items={
                    <div className="flex min-w-0 flex-col gap-3">
                        <BusinessDateControl today={today} value={date} onChange={setDate} />
                        {validUntil !== null && <p>{t('quotations.confirm.valid_until', { tanggal: dateLong(validUntil) })}</p>}
                        {issue.errors.tanggal !== undefined && <p className="text-danger">{issue.errors.tanggal}</p>}
                    </div>
                }
                confirmLabel={t('quotations.action.issue')}
                pending={issue.pending}
                answer={issue.answer}
                onConfirm={() => {
                    issue.submit({ revisi: base.id, lock_version: base.lock_version, tanggal: date.date, tanggal_otomatis: !date.chosen, isi: draft.facts ?? '' }, () => { setConfirming(false); });
                }}
            />
        </>
    );
}

function IssuedLines({ current, money }: { current: Current; money: boolean }) {
    const { t } = useTranslation();

    return (
        <FormSection title={t('quotations.form.lines')}>
            <ol aria-label={t('quotations.form.lines')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                {current.lines.map((line, index) => (
                    <li key={line.handle} className="flex min-w-0 flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3">
                        <span className="min-w-0 text-body text-text-primary">{`${String(index + 1)}. ${line.description}`}</span>
                        <span className="flex flex-wrap items-baseline gap-4 text-body text-text-secondary">
                            <span>{formatQuantity(line.quantity, line.unit)}</span>
                            {money && line.value !== undefined && <Money value={line.value} />}
                        </span>
                    </li>
                ))}
            </ol>
        </FormSection>
    );
}

export default function Quotation({ project, revisions, current, taxes, today, can }: Props) {
    const { t } = useTranslation();
    const id = project?.id ?? '';
    const latest = revisions[0] ?? null;
    const shown = latest?.state ?? null;
    const money = current?.lines.some((line) => line.price !== undefined) ?? false;
    const start = useCommand<Payload>(() => `/proyek/${id}/penawaran`, 'post');
    const issue = useCommand<Payload>(() => `/proyek/${id}/penawaran/terbitkan`, 'post');
    const revise = useCommand<Payload>(() => `/proyek/${id}/penawaran/revisi`, 'post');
    const answer = useCommand<Payload>(() => `/proyek/${id}/penawaran/jawaban`, 'post');
    const [answering, setAnswering] = useState(false);
    const [choice, setChoice] = useState<'SETUJU' | 'TOLAK'>(can.approve ? 'SETUJU' : 'TOLAK');
    const [reason, setReason] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    // A sent revision within its validity takes the client's answer; past it, only "Buat Revisi" (L-25; criterion 2).
    const answerable = shown === 'SENT' && (can.approve || can.reject);
    const revisable = can.manage && latest !== null && latest.state !== 'DRAFT';
    const openAnswer = () => {
        answer.clear();
        setReason('');
        setChoice(can.approve ? 'SETUJU' : 'TOLAK');
        setAnswering(true);
    };
    const primary = answerable ? (
        <Button size="lg" onClick={openAnswer}>
            <Icon icon={MessageSquareReply} size="sm" />
            {t('quotations.action.answer')}
        </Button>
    ) : revisable ? (
        <Button size="lg" variant={shown === 'EXPIRED' || shown === 'REJECTED' ? 'primary' : 'outline'} pending={revise.pending} onClick={() => { revise.submit({ revisi: latest.id }); }}>
            <Icon icon={CopyPlus} size="sm" />
            {t('quotations.action.revise')}
        </Button>
    ) : undefined;

    if (project === null) {
        return null;
    }

    return (
        <AppShell title={t('pages.pg_014.title')} back={{ href: `/proyek/${project.id}`, label: t('pages.pg_011.title') }} composer={current?.state === 'DRAFT' && can.manage}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader
                    title={t('quotations.title', { nomor: project.number })}
                    crumbs={[{ label: t('pages.pg_010.title'), href: '/proyek' }, { label: project.number, href: `/proyek/${project.id}` }]}
                    back={{ label: t('dialogs.back'), href: `/proyek/${project.id}` }}
                    facts={
                        <div className="flex flex-wrap items-center gap-3 text-body text-text-secondary">
                            <span>{project.title}</span>
                            <span>{`${project.company} · ${project.client}`}</span>
                            {latest !== null && <StatusBadge family="quotation_revision" state={revisionState(latest.state)} />}
                        </div>
                    }
                    primary={primary}
                    actions={answerable && revisable ? [{ label: t('quotations.action.revise'), onSelect: () => { revise.submit({ revisi: latest.id }); } }] : []}
                />
                <Answered command={start} />
                <Answered command={issue} />
                <Answered command={revise} />
                <Answered command={answer} />
                {shown === 'EXPIRED' && <p className="text-body text-text-secondary">{t('quotations.expired_note')}</p>}
                {current === null ? (
                    <RegionEmpty
                        text={t('quotations.empty')}
                        action={can.manage ? (
                            <Button pending={start.pending} onClick={() => { start.submit({}); }}>
                                {t('quotations.action.start')}
                            </Button>
                        ) : undefined}
                    />
                ) : current.state === 'DRAFT' && can.manage ? (
                    <DraftComposer key={current.id} project={project} draft={current} taxes={taxes} today={today} money={money} issue={issue} />
                ) : (
                    <IssuedLines current={current} money={money} />
                )}
                <FormSection title={t('quotations.revisions')}>
                    {revisions.length === 0 ? (
                        <p className="text-body text-text-secondary">{t('quotations.no_revisions')}</p>
                    ) : (
                        <ol aria-label={t('quotations.revisions')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {revisions.map((revision) => (
                                <li key={revision.id} data-revision={revision.no} className="flex min-w-0 flex-col gap-2 py-3">
                                    <div className="flex min-w-0 flex-wrap items-center gap-3">
                                        <span className="font-semibold text-text-primary">{t('quotations.revision', { n: revision.no })}</span>
                                        <StatusBadge family="quotation_revision" state={revisionState(revision.state)} size="sm" />
                                        {revision.number !== null && (revision.document !== null ? <a href={`/dokumen/${revision.document}`} className="text-accent-link underline underline-offset-2"><Code>{revision.number}</Code></a> : <Code>{revision.number}</Code>)}
                                    </div>
                                    {revision.valid_until !== null && (
                                        <p className="text-body text-text-secondary">
                                            {t('quotations.field.valid_until')}
                                            {': '}
                                            <DateText value={revision.valid_until} />
                                        </p>
                                    )}
                                    {revision.decided_at !== null && (
                                        <p className="text-body text-text-secondary">
                                            {t('quotations.decided', { nama: revision.decided_by ?? '—' })}
                                            {' · '}
                                            <DateTimeText value={revision.decided_at} />
                                            {revision.reason !== null && ` · ${revision.reason}`}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}
                </FormSection>
            </div>
            {/* On the phone the page's one primary action — "Catat Jawaban Klien" or "Buat Revisi" — sits in the bottom bar (J-QUO-01). */}
            {primary !== undefined && (
                <ActionBar label={t('pages.pg_014.title')} className="md:hidden">
                    {primary}
                </ActionBar>
            )}
            <ConfirmCommand
                open={answering}
                onOpenChange={setAnswering}
                title={t('quotations.confirm.answer_title', { n: latest?.no ?? 0 })}
                consequence={choice === 'SETUJU' ? t('quotations.confirm.approve_consequence') : t('quotations.confirm.reject_consequence')}
                items={
                    <div className="flex min-w-0 flex-col gap-4">
                        <RadioGroup value={choice} onValueChange={(value) => { setChoice(value === 'TOLAK' ? 'TOLAK' : 'SETUJU'); }} aria-label={t('quotations.field.answer')}>
                            {can.approve && (
                                <label className="flex items-center gap-3 text-body">
                                    <RadioGroupItem value="SETUJU" />
                                    {t('quotations.answer_choice.approve')}
                                </label>
                            )}
                            {can.reject && (
                                <label className="flex items-center gap-3 text-body">
                                    <RadioGroupItem value="TOLAK" />
                                    {t('quotations.answer_choice.reject')}
                                </label>
                            )}
                        </RadioGroup>
                        <FormField label={t('quotations.field.reason')} required={choice === 'TOLAK'} error={answer.errors.alasan}>
                            {(control) => <Textarea {...control} value={reason} rows={3} onChange={(event) => { setReason(event.target.value); }} />}
                        </FormField>
                        {choice === 'SETUJU' && <BusinessDateControl today={today} value={date} onChange={setDate} />}
                        {answer.errors.tanggal !== undefined && <p className="text-danger">{answer.errors.tanggal}</p>}
                    </div>
                }
                confirmLabel={choice === 'SETUJU' ? t('quotations.action.approve') : t('quotations.action.reject')}
                pending={answer.pending}
                answer={answer.answer}
                onConfirm={() => {
                    answer.submit({ revisi: latest?.id ?? '', jawaban: choice, alasan: reason.trim() === '' ? null : reason.trim(), tanggal: date.date, tanggal_otomatis: !date.chosen }, () => { setAnswering(false); });
                }}
            />
        </AppShell>
    );
}
