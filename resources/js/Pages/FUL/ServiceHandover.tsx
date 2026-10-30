// PG-033 *Serah Terima Jasa* (ROUTE-033, ROUTE-208, ROUTE-209; NAV-033; SCR-31; WF-FUL-04; J-FUL-04; TASK-047): per service
// line the quantity and the work performed, the recipient, the date and the evidence uploaded first (IP-16) — on the desktop
// the form on the left and the BAST's live preview on the right from 1,025 px, never a tab; on the phone Edit → Preview →
// Finalize (D-DS-23; DC-11; INT-012) —; "Catat Serah Terima" answers on PG-011 (NAV-033). The recorded handovers below it,
// each with its reversal lines — "Balikkan" with its reason (IP-09; CM-27) — and its BAST, the screen's second command
// (IP-19): "Terbitkan BAST" creates its draft from the recorded facts, or finds the one an earlier press left, and issues it
// in place after its confirmation (INT-221; INT-222; IP-08). No stock screen is ever involved (FS-05).
import { router } from '@inertiajs/react';
import { RotateCcw, Send } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { ActionBar } from '@/components/action-bar';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { BusinessDateControl, type BusinessDate } from '@/components/date-field';
import { DecimalInput, FormField, FormSection } from '@/components/form';
import { DuplicateWarning, type DuplicateMatch } from '@/components/notices';
import { OutcomeMessage, type Answer } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { DateText } from '@/components/values';
import { DocumentComposer } from '@/features/doc/composer';
import { type Paper } from '@/features/doc/paper';
import { type BastLine, liveBast } from '@/features/ful/handover';
import AppShell from '@/layouts/AppShell';
import { dateLong, dateTime, quantity as formatQuantity } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { isBackground } from '@/lib/session-core';
import { useCommand } from '@/lib/use-command';

type ServiceLine = { handle: string; no: number; description: string; unit: string; base_unit: string; ratio: number; scale: number; demand: string; handed_over: string; cancelled: string; remainder: string; remainder_entered: string };
type Reversal = { reason: string | null; by: string | null; at: string };
type HandedLine = { handle: string; no: number; description: string; quantity: string; unit: string; base: string; scope: string | null; reversal: Reversal | null; can_reverse: boolean };
type Bast = { document: string; draft: { id: string; lock_version: number; facts: string | null } | null; issued: string | null; issued_lines: number };
type Handover = { id: string; date: string; recipient: string; recorded_by: string | null; recorded_at: string; lines: HandedLine[]; evidence: { id: string; type: string }[]; bast: Bast | null; bast_affected: boolean; can_issue: boolean };
type EvidenceOption = { id: string; type: string; issuer: string; reference: string | null; date: string };
type Props = {
    project: { id: string; number: string; title: string; company: string; client: string; state: string } | null;
    lines: ServiceLine[];
    handovers: Handover[];
    older: string | null;
    after: string | null;
    paper: Paper | null;
    evidence: EvidenceOption[];
    today: string;
    can: { record: boolean; upload: boolean };
};
type Entered = Record<string, { quantity: string; scope: string }>;
type Payload = Record<string, string | boolean | string[] | { baris: string; jumlah: string; lingkup: string }[] | { service_handover_id: string }>;

const evidenceName = (type: string): TranslationKey => `evidence.type.${type}` as TranslationKey;

function Answered({ command }: { command: { answer: Answer | null; unknown: boolean; retry: () => void } }) {
    const { t } = useTranslation();

    return command.answer === null ? null : (
        <OutcomeMessage answer={command.answer} action={command.unknown ? <Button variant="outline" size="sm" onClick={command.retry}>{t('actions.try_again')}</Button> : undefined} />
    );
}

const standing = (handover: Handover): BastLine[] => handover.lines.filter((line) => line.reversal === null).map((line) => ({ description: line.description, quantity: line.quantity, unit: line.unit, note: line.scope }));

/** One recorded handover: its lines with their reversal lines, its evidence and its BAST (PT-18; IP-19). */
function Recorded({ handover, previewing, onPreview, onReverse, onIssue, preparing }: { handover: Handover; previewing: boolean; onPreview: () => void; onReverse: (line: HandedLine) => void; onIssue: () => void; preparing: boolean }) {
    const { t } = useTranslation();
    const bast = handover.bast;

    return (
        <li data-testid="handover" className="flex min-w-0 flex-col gap-3 py-4">
            <div className="flex min-w-0 flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <span className="min-w-0 font-semibold text-text-primary"><DateText value={handover.date} />{` · ${handover.recipient}`}</span>
                {handover.recorded_by !== null && <span className="text-caption text-text-secondary">{t('handover.recorded.by', { nama: handover.recorded_by })}</span>}
            </div>
            <ul role="list" className="flex min-w-0 flex-col gap-2">
                {handover.lines.map((line) => (
                    <li key={line.handle} className="flex min-w-0 flex-col gap-1">
                        <div className="flex min-w-0 flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <span className={line.reversal === null ? 'min-w-0 text-body text-text-primary' : 'min-w-0 text-body text-text-secondary line-through'}>
                                {`${String(line.no)}. ${line.description} · ${formatQuantity(line.quantity, line.unit)}`}
                            </span>
                            {line.can_reverse && (
                                <Button variant="ghost" size="sm" onClick={() => { onReverse(line); }}>
                                    <Icon icon={RotateCcw} size="sm" />
                                    {t('handover.action.reverse')}
                                </Button>
                            )}
                        </div>
                        {line.scope !== null && line.reversal === null && <p className="text-body text-text-secondary">{line.scope}</p>}
                        {line.reversal !== null && (
                            <p className="text-body text-text-secondary">
                                {t('handover.line.reversed', { waktu: dateTime(line.reversal.at), nama: line.reversal.by ?? '—', alasan: line.reversal.reason ?? '—' })}
                            </p>
                        )}
                    </li>
                ))}
            </ul>
            {handover.evidence.length > 0 && (
                <ul role="list" className="flex flex-wrap gap-3">
                    {handover.evidence.map((item) => (
                        <li key={item.id}>
                            <a href={`/dokumen/bukti?bukti=${item.id}`} className="text-body text-accent-link underline underline-offset-2">{t(evidenceName(item.type))}</a>
                        </li>
                    ))}
                </ul>
            )}
            <div className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
                <span className="text-body text-text-secondary">
                    {bast?.issued != null ? t('handover.bast.issued', { nomor: bast.issued }) : bast?.draft != null ? t('handover.bast.draft') : t('handover.bast.none')}
                </span>
                {bast !== null && <a href={`/dokumen/${bast.document}`} className="text-body text-accent-link underline underline-offset-2">{t('handover.bast.open')}</a>}
                {!previewing && standing(handover).length > 0 && bast?.issued == null && (
                    <Button variant="ghost" size="sm" onClick={onPreview}>{t('handover.action.preview_bast')}</Button>
                )}
                {handover.can_issue && (
                    <Button variant="outline" size="sm" pending={preparing} onClick={onIssue}>
                        <Icon icon={Send} size="sm" />
                        {t('handover.action.issue_bast')}
                    </Button>
                )}
            </div>
            {handover.bast_affected && <p className="text-body text-danger">{t('handover.bast.affected')}</p>}
        </li>
    );
}

export default function ServiceHandover({ project, lines, handovers, older, after, paper, evidence, today, can }: Props) {
    const { t } = useTranslation();
    const id = project?.id ?? '';
    const [entered, setEntered] = useState<Entered>({});
    const [recipient, setRecipient] = useState('');
    const [date, setDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [cited, setCited] = useState('');
    const [acknowledged, setAcknowledged] = useState<string[]>([]);
    const [reason, setReason] = useState('');
    const [focus, setFocus] = useState<string | null>(null);
    const [reversing, setReversing] = useState<{ handover: string; line: HandedLine } | null>(null);
    const [reverseReason, setReverseReason] = useState('');
    const [issuing, setIssuing] = useState<string | null>(null);
    const [issueDate, setIssueDate] = useState<BusinessDate>({ date: today, chosen: false });
    const [leaving, setLeaving] = useState<(() => void) | null>(null);
    // The departure MSG-35 confirmed passes the guard at once: the guard's `dirty` is the last render's until the next one.
    const departing = useRef(false);
    const record = useCommand<Payload>(() => `/proyek/${id}/serah-terima`, 'post');
    const reverse = useCommand<Payload>(() => `/proyek/${id}/serah-terima/${reversing?.handover ?? ''}/balikkan`, 'post');
    const prepare = useCommand<Payload>(() => '/dokumen', 'post');
    const issuingHandover = handovers.find((handover) => handover.id === issuing) ?? null;
    const issue = useCommand<Payload>(() => `/dokumen/${issuingHandover?.bast?.document ?? ''}/terbitkan`, 'post');
    const open = lines.filter((line) => Number(line.remainder) > 0);
    const filled = lines.filter((line) => (entered[line.handle]?.quantity ?? '') !== '');
    const dirty = filled.length > 0 || recipient !== '' || cited !== '';
    const matches = Array.isArray(record.state.matches) ? (record.state.matches as DuplicateMatch[]) : [];
    const warned = record.answer?.kind === 'REJECTED' && matches.length > 0;
    const page = `/proyek/${id}/serah-terima`;

    // A visit away with unsent input asks first (MSG-35; NV-02); background reads do not.
    useEffect(() => {
        const off = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (!dirty || departing.current || visit.method !== 'get' || visit.only.length > 0 || isBackground(visit)) {
                return;
            }

            const href = visit.url.href;
            setLeaving(() => () => { router.visit(href); });

            return false;
        });
        const unload = (event: BeforeUnloadEvent) => {
            if (dirty && !departing.current) event.preventDefault();
        };
        window.addEventListener('beforeunload', unload);

        return () => {
            off();
            window.removeEventListener('beforeunload', unload);
        };
    }, [dirty]);

    const set = (handle: string, patch: Partial<Entered[string]>) => {
        setEntered((current) => ({ ...current, [handle]: { quantity: current[handle]?.quantity ?? '', scope: current[handle]?.scope ?? '', ...patch } }));
        setFocus(null);
    };
    const focused = handovers.find((handover) => handover.id === focus) ?? null;
    // The form's entries while there are any; else the handover chosen, else the newest one a BAST could still be issued for.
    const shown = focused ?? (filled.length === 0 ? (handovers.find((handover) => handover.can_issue) ?? null) : null);
    const composing = (can.record && open.length > 0) || shown !== null;
    const live = useMemo<Paper | null>(() => {
        if (paper === null || !composing) return null;

        return shown !== null
            ? liveBast(paper, shown.recipient, shown.date, standing(shown))
            : liveBast(paper, recipient, date.date, filled.map((line) => ({ description: line.description, quantity: entered[line.handle]?.quantity ?? null, unit: line.unit, note: entered[line.handle]?.scope ?? null })));
    }, [paper, composing, shown, recipient, date.date, filled, entered]);
    const submit = () => {
        record.submit({
            tanggal: date.date,
            tanggal_otomatis: !date.chosen,
            penerima: recipient,
            ...(cited === '' ? {} : { bukti: cited }),
            baris: filled.map((line) => ({ baris: line.handle, jumlah: entered[line.handle]?.quantity ?? '', lingkup: entered[line.handle]?.scope ?? '' })),
            ...(warned ? { acknowledged, reason } : {}),
        });
    };
    const browse = (cursor: string | null) => {
        router.get(page, cursor === null ? {} : { setelah: cursor }, { only: ['handovers', 'older', 'after'], preserveState: true, preserveScroll: true });
    };
    const startIssue = (handover: Handover) => {
        setIssueDate({ date: today, chosen: false });
        issue.clear();
        prepare.submit({ jenis: 'DOC-08', subjek: { service_handover_id: handover.id }, kembali: page }, () => { setIssuing(handover.id); });
    };
    const lineError = (handle: string, field: string): string | undefined => record.errors[`baris.${String(filled.findIndex((line) => line.handle === handle))}.${field}`];

    if (project === null) {
        return null;
    }

    const form = (
        <div className="flex min-w-0 flex-col gap-6">
            {can.record && open.length > 0 && (
                <FormSection title={t('handover.section.form')}>
                    <FormField label={t('handover.field.recipient')} required help={t('handover.field.recipient_help')} error={record.errors.penerima}>
                        {(control) => <Input {...control} value={recipient} autoComplete="off" onChange={(event) => { setRecipient(event.target.value); setFocus(null); }} />}
                    </FormField>
                    <BusinessDateControl today={today} value={date} onChange={(next) => { setDate(next); setFocus(null); }} />
                    {record.errors.tanggal !== undefined && <p className="text-body text-danger">{record.errors.tanggal}</p>}
                    <FormField label={t('handover.field.evidence')} error={record.errors.bukti}>
                        {(control) => (
                            <NativeSelect {...control} value={cited} onChange={(event) => { setCited(event.target.value); }}>
                                <option value="">{t('handover.evidence.none')}</option>
                                {evidence.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {`${t(evidenceName(option.type))} · ${option.issuer}${option.reference === null ? '' : ` · ${option.reference}`} · ${dateLong(option.date)}`}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>
                    {can.upload && <a href="/dokumen/bukti" className="text-body text-accent-link underline underline-offset-2">{t('handover.evidence.add')}</a>}
                </FormSection>
            )}
            <FormSection title={t('handover.lines')}>
                {lines.length === 0 ? (
                    <RegionEmpty text={t('handover.no_lines')} />
                ) : (
                    <ol aria-label={t('handover.lines')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {lines.map((line) => {
                            const figure = (value: string) => formatQuantity(value, '').trim();
                            const progress = { dipesan: figure(line.demand), diserahkan: figure(line.handed_over), dibatalkan: figure(line.cancelled), sisa: figure(line.remainder), satuan: line.base_unit };

                            return (
                                <li key={line.handle} data-testid="service-line" className="grid min-w-0 gap-3 py-4 sm:grid-cols-2">
                                    <div className="flex min-w-0 flex-col gap-1 sm:col-span-2">
                                        <span className="min-w-0 font-semibold text-text-primary">{`${String(line.no)}. ${line.description}`}</span>
                                        <span className="text-body text-text-secondary">{t('handover.line.progress', progress)}</span>
                                    </div>
                                    {Number(line.remainder) <= 0 ? (
                                        <p className="text-body text-text-secondary sm:col-span-2">{t('handover.line.done')}</p>
                                    ) : can.record && (
                                        <>
                                            <FormField label={t('handover.field.quantity', { satuan: line.unit })} error={lineError(line.handle, 'jumlah')}
                                                help={line.ratio === 1 ? undefined : t('handover.field.ratio', { satuan: line.unit, rasio: String(line.ratio), dasar: line.base_unit })}>
                                                {(control) => <DecimalInput {...control} value={entered[line.handle]?.quantity ?? ''} scale={line.scale} unit={line.unit} onValueChange={(value) => { set(line.handle, { quantity: value ?? '' }); }} />}
                                            </FormField>
                                            <FormField label={t('handover.field.scope')} className="sm:col-span-2" help={t('handover.field.scope_help')} error={lineError(line.handle, 'lingkup')}>
                                                {(control) => <Textarea {...control} rows={2} value={entered[line.handle]?.scope ?? ''} onChange={(event) => { set(line.handle, { scope: event.target.value }); }} />}
                                            </FormField>
                                        </>
                                    )}
                                </li>
                            );
                        })}
                    </ol>
                )}
                {lines.length > 0 && open.length === 0 && <p className="text-body text-text-secondary">{t('handover.all_done')}</p>}
                {record.errors.baris !== undefined && <p className="text-body text-danger">{record.errors.baris}</p>}
            </FormSection>
            <FormSection title={t('handover.recorded.title')}>
                <Answered command={reverse} />
                <Answered command={prepare} />
                <Answered command={issue} />
                {live !== null && shown !== null && <p className="text-caption text-text-secondary">{t('handover.preview_of', { tanggal: dateLong(shown.date) })}</p>}
                {handovers.length === 0 && after === null ? (
                    <RegionEmpty text={t('handover.recorded.empty')} />
                ) : (
                    <ol aria-label={t('handover.recorded.title')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {handovers.map((handover) => (
                            <Recorded
                                key={handover.id}
                                handover={handover}
                                previewing={shown?.id === handover.id}
                                preparing={prepare.pending}
                                onPreview={() => { setFocus(handover.id); }}
                                onReverse={(line) => { reverse.clear(); setReverseReason(''); setReversing({ handover: handover.id, line }); }}
                                onIssue={() => { startIssue(handover); }}
                            />
                        ))}
                    </ol>
                )}
                {(older !== null || after !== null) && (
                    <div className="flex flex-wrap gap-2">
                        {after !== null && <Button variant="ghost" size="sm" onClick={() => { browse(null); }}>{t('handover.recorded.newest')}</Button>}
                        {older !== null && <Button variant="outline" size="sm" onClick={() => { browse(older); }}>{t('handover.recorded.older')}</Button>}
                    </div>
                )}
            </FormSection>
        </div>
    );
    const finalize = can.record && open.length > 0 ? (
        <div className="flex min-w-0 flex-col gap-3">
            {warned ? (
                <DuplicateWarning matches={matches} message={record.answer?.text} acknowledged={acknowledged} onAcknowledge={setAcknowledged}
                    reason={{ value: reason, onChange: setReason, error: record.errors.reason ?? null }} canContinue />
            ) : (
                <Answered command={record} />
            )}
            <ActionBar label={t('pages.pg_033.title')}>
                <Button variant="outline" onClick={() => { router.visit(`/proyek/${project.id}`); }}>{t('actions.cancel')}</Button>
                <Button pending={record.pending} disabled={record.frozen} onClick={submit}>{t('handover.action.record')}</Button>
            </ActionBar>
        </div>
    ) : null;

    return (
        <AppShell title={t('pages.pg_033.title')} back={{ href: `/proyek/${project.id}`, label: t('pages.pg_011.title') }} composer={live !== null}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader
                    title={t('handover.title', { nomor: project.number })}
                    crumbs={[{ label: t('pages.pg_010.title'), href: '/proyek' }, { label: project.number, href: `/proyek/${project.id}` }]}
                    back={{ label: t('dialogs.back'), href: `/proyek/${project.id}` }}
                    facts={
                        <div className="flex flex-wrap items-center gap-3 text-body text-text-secondary">
                            <span>{project.title}</span>
                            <span>{`${project.company} · ${project.client}`}</span>
                        </div>
                    }
                />
                {live === null ? (
                    <div className="flex min-w-0 flex-col gap-6">
                        {form}
                        {finalize}
                    </div>
                ) : (
                    <DocumentComposer name={t('documents.doc_08')} paper={live} form={form} finalize={finalize} />
                )}
            </div>
            <ConfirmCommand
                open={reversing !== null}
                onOpenChange={(next) => { if (!next) setReversing(null); }}
                title={t('handover.reverse.title', { baris: reversing?.line.no ?? 0 })}
                consequence={t('handover.reverse.consequence', { jumlah: formatQuantity(reversing?.line.quantity ?? '0', '').trim(), satuan: reversing?.line.unit ?? '' })}
                reason={{ value: reverseReason, onChange: setReverseReason, error: reverse.errors.alasan ?? null }}
                confirmLabel={t('handover.action.reverse')}
                destructive
                pending={reverse.pending}
                answer={reverse.answer}
                onConfirm={() => {
                    if (reversing === null) return;
                    reverse.submit({ baris: reversing.line.handle, alasan: reverseReason }, () => { setReversing(null); });
                }}
            />
            <ConfirmCommand
                open={issuingHandover?.bast?.draft != null}
                onOpenChange={(next) => { if (!next) setIssuing(null); }}
                title={t('handover.bast.confirm_title')}
                consequence={t('handover.bast.consequence')}
                items={
                    <div className="flex min-w-0 flex-col gap-3">
                        <BusinessDateControl today={today} value={issueDate} onChange={setIssueDate} />
                        {issue.errors.tanggal !== undefined && <p className="text-danger">{issue.errors.tanggal}</p>}
                    </div>
                }
                confirmLabel={t('handover.action.issue_bast')}
                pending={issue.pending}
                answer={issue.answer}
                onConfirm={() => {
                    const draft = issuingHandover?.bast?.draft;

                    if (draft == null) return;
                    issue.submit({ draf: draft.id, lock_version: String(draft.lock_version), tanggal: issueDate.date, tanggal_otomatis: !issueDate.chosen, isi: draft.facts ?? '', kembali: page }, () => { setIssuing(null); });
                }}
            />
            <UnsentInputDialog open={leaving !== null} kind="leave" onStay={() => { setLeaving(null); }}
                onLeave={() => { const go = leaving; departing.current = true; setLeaving(null); go?.(); }} />
        </AppShell>
    );
}
