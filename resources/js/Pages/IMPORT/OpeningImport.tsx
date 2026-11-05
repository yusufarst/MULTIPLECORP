// PG-058 *Impor Data Awal* (ROUTE-058; NAV-058; SCR-58; WF-MIG-01; J-MIG-01; ADMIN_FLOW §13; TASK-057): the batches with their states — *Disiapkan*, *Uji Coba Berjalan*,
// *Uji Coba Lolos*, *Disetujui Bersama*, *Sedang Diimpor*, *Selesai Diimpor* —, a batch's report, sign-offs and progress polled as IP-12 states; "Siapkan Batch" (INT-272),
// then the batch's one next step — "Jalankan Uji Coba" (INT-273), "Setujui Hasil Uji Coba" (INT-274), the Owner's "Jalankan Impor" or "Lanjutkan" behind MSG-39 and the
// step-up (INT-275) —, the Owner's "Batalkan Batch" with its reason (INT-276), and "Koreksi Data Awal" on a committed opening fact (INT-213; INT-214; CM-33).
import { router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { FormField, FormSection } from '@/components/form';
import { UploadField } from '@/components/notices';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Code, DateText, DateTimeText, Money } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';
import { useUpload } from '@/lib/use-upload';

type State = 'PREPARED' | 'DRY_RUN_RUNNING' | 'DRY_RUN_FAILED' | 'DRY_RUN_PASSED' | 'SIGNED_OFF' | 'COMMITTING' | 'STOPPED' | 'COMMITTED' | 'ABORTED';
type RowError = { field?: string; reason?: string; code?: string };
type Totals = { company: string; stock_qty: string | null; stock_cost: string | null; receivables: string | null; credits: string | null };
type Signoff = { by: string; at: string | null } | null;
type Summary = {
    id: string; kind: string; cutoff: string; state: State; companies: string[]; counts: Record<string, number>; prepared_by: string; recorded_at: string;
    admin_signoff: Signoff; owner_signoff: Signoff; committed_by: string | null;
};
type Detail = Summary & {
    report: { state: string | null; finished_at: string | null };
    problems: { row: number; kind: string; identity: string; state: string; errors: RowError[] }[];
    totals: Totals[];
    committed_totals: Totals[];
    facts: { row: number; kind: string; identity: string; target: string }[];
};
type Props = {
    can: { prepare: boolean; commit: boolean };
    kinds: string[];
    companies: { id: string; code: string; name: string }[];
    batches: Summary[];
    more: string | null;
    batch: Detail | null;
};

const POLLED: State[] = ['DRY_RUN_RUNNING', 'COMMITTING'];
const COUNTS = ['VALID', 'INVALID', 'DUPLICATE', 'SKIPPED', 'COMMITTED'];

function reasonText(t: ReturnType<typeof useTranslation>['t'], error: RowError): string {
    // UXS-19: a twin outside scope is MSG-18 alone — no batch, company, date or count.
    if (error.reason === 'undisclosed') return t('messages.msg_18');
    const reason = t(`import.reason.${error.reason ?? 'refused'}` as TranslationKey);

    return error.code !== undefined && error.reason === 'refused' ? `${reason} (${error.code})` : error.field !== undefined && error.field !== '' ? `${error.field}: ${reason}` : reason;
}

/** The batch's state polled through ROUTE-285 while a job runs (IP-12), the page reloaded once it settles. */
function usePolledState(batch: Detail | null): { committed: number; remaining: number } | null {
    const [progress, setProgress] = useState<{ committed: number; remaining: number } | null>(null);
    const id = batch?.id ?? '';
    const running = batch !== null && POLLED.includes(batch.state);

    useEffect(() => {
        if (!running) return undefined;
        const timer = window.setInterval(() => {
            void fetch(`/status/impor/${encodeURIComponent(id)}`, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(async (response) => (response.ok ? ((await response.json()) as { state: State; committed: number; remaining: number }) : null))
                .then((answer) => {
                    if (answer === null) return;
                    setProgress({ committed: answer.committed, remaining: answer.remaining });
                    if (!POLLED.includes(answer.state)) router.reload({ only: ['batch', 'batches'] });
                });
        }, 5000);

        return () => { window.clearInterval(timer); };
    }, [id, running]);

    return progress;
}

function PrepareSheet({ kinds, companies, onClose }: { kinds: string[]; companies: Props['companies']; onClose: () => void }) {
    const { t } = useTranslation();
    const [kind, setKind] = useState('');
    const [cutoff, setCutoff] = useState('');
    const [chosen, setChosen] = useState<string[]>(companies.length === 1 ? [companies[0]?.id ?? ''] : []);
    const upload = useUpload((): Record<string, string> => {
        const fields: Record<string, string> = { purpose: 'sumber-impor' };
        chosen.forEach((id, index) => { fields[`companies[${String(index)}]`] = id; });

        return fields;
    });
    const prepare = useCommand<{ jenis: string; berkas: string; tanggal: string; perusahaan: string[] }>(() => '/data-master/impor', 'post');
    const ready = kind !== '' && cutoff !== '' && chosen.length > 0 && upload.file !== null;

    return (
        <Sheet open onOpenChange={(open) => { if (!open) onClose(); }}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t('import.action.prepare')}</SheetTitle>
                    <SheetDescription>{t('import.form.intro')}</SheetDescription>
                </SheetHeader>
                <form className="flex min-w-0 flex-col gap-6" noValidate onSubmit={(event) => { event.preventDefault(); if (upload.file !== null) prepare.submit({ jenis: kind, berkas: upload.file, tanggal: cutoff, perusahaan: chosen }, onClose); }}>
                    <FormSection title={t('import.form.batch')}>
                        <FormField label={t('import.field.kind')} required error={prepare.errors.jenis}>
                            {(control) => (
                                <NativeSelect {...control} value={kind} onChange={(event) => { setKind(event.target.value); }}>
                                    <option value="">{t('import.form.choose')}</option>
                                    {kinds.map((code) => <option key={code} value={code}>{t(`import.kind.${code}` as TranslationKey)}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        <FormField label={t('import.field.cutoff')} required error={prepare.errors.tanggal}>
                            {(control) => <Input {...control} type="date" value={cutoff} onChange={(event) => { setCutoff(event.target.value); }} />}
                        </FormField>
                        <fieldset className="flex min-w-0 flex-col gap-2">
                            <legend className="text-label font-semibold text-text-primary">{t('import.field.companies')}</legend>
                            {companies.map((company) => (
                                <label key={company.id} className="flex items-center gap-2 text-body text-text-primary">
                                    <input type="checkbox" checked={chosen.includes(company.id)} disabled={upload.file !== null}
                                        onChange={(event) => { setChosen(event.target.checked ? [...chosen, company.id] : chosen.filter((id) => id !== company.id)); }} />
                                    <span>{`${company.code} · ${company.name}`}</span>
                                </label>
                            ))}
                            {prepare.errors.perusahaan !== undefined && <p className="text-caption text-danger">{prepare.errors.perusahaan}</p>}
                        </fieldset>
                    </FormSection>
                    <FormSection title={t('import.form.source')}>
                        <UploadField label={t('import.field.source')} accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" limits={t('import.field.source_limits')}
                            state={upload.state} onChoose={(file) => { if (chosen.length > 0) upload.choose(file); }} onCancel={upload.cancel} onRemove={upload.reset} onRetry={upload.retry} />
                        {chosen.length === 0 && <p className="text-caption text-text-secondary">{t('import.form.companies_first')}</p>}
                    </FormSection>
                    {prepare.answer !== null && <OutcomeMessage answer={prepare.answer} />}
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>{t('actions.cancel')}</Button>
                        <Button type="submit" pending={prepare.pending} disabled={!ready || prepare.frozen}>{t('import.action.prepare')}</Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}

function SignOffSheet({ batch, onClose }: { batch: Detail; onClose: () => void }) {
    const { t } = useTranslation();
    const upload = useUpload((): Record<string, string> => ({ purpose: 'bukti', pool: '1' }));
    const signOff = useCommand<{ bukti: string }>(() => `/data-master/impor/${batch.id}/setujui`, 'post');

    return (
        <Sheet open onOpenChange={(open) => { if (!open) onClose(); }}>
            <SheetContent width="sm" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t('import.action.sign_off')}</SheetTitle>
                    <SheetDescription>{t('import.signoff.intro')}</SheetDescription>
                </SheetHeader>
                <div className="flex min-w-0 flex-col gap-6">
                    <UploadField label={t('import.field.signoff')} accept=".pdf,.jpg,.jpeg,.png,.webp" limits={t('import.field.signoff_limits')}
                        state={upload.state} onChoose={upload.choose} onCancel={upload.cancel} onRemove={upload.reset} onRetry={upload.retry} />
                    {signOff.answer !== null && <OutcomeMessage answer={signOff.answer} />}
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>{t('actions.cancel')}</Button>
                        <Button pending={signOff.pending} disabled={upload.file === null || signOff.frozen} onClick={() => { if (upload.file !== null) signOff.submit({ bukti: upload.file }, onClose); }}>{t('import.action.sign_off')}</Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function CorrectDialog({ batch, fact, onClose }: { batch: Detail; fact: Detail['facts'][number]; onClose: () => void }) {
    const { t } = useTranslation();
    const [reason, setReason] = useState('');
    const [value, setValue] = useState('');
    const stock = fact.kind === 'OPENING_STOCK';
    const credit = fact.kind === 'OPENING_CREDIT';
    const correct = useCommand<Record<string, string | number>>(() => (stock ? `/gudang/riwayat-stok/${fact.target}/koreksi-data-awal` : `/keuangan/data-awal/${fact.target}/koreksi`), 'post');

    return (
        <ConfirmCommand
            open
            onOpenChange={(open) => { if (!open) onClose(); }}
            title={t('import.action.correct')}
            destructive
            consequence={t('import.confirm.correct')}
            items={stock ? undefined : (
                <FormField label={t(credit ? 'import.field.amount' : 'import.field.value')} error={correct.errors[credit ? 'jumlah' : 'nilai']} help={t(credit ? 'import.field.amount_help' : 'import.field.value_help')}>
                    {(control) => <Input {...control} inputMode="decimal" value={value} onChange={(event) => { setValue(event.target.value); }} />}
                </FormField>
            )}
            reason={{ value: reason, onChange: setReason, error: correct.errors.alasan }}
            confirmLabel={t('import.action.correct')}
            pending={correct.pending}
            answer={correct.answer}
            onConfirm={() => {
                const payload: Record<string, string | number> = stock ? { alasan: reason, batch: batch.id } : { alasan: reason, baris: fact.row, ...(value === '' ? {} : { [credit ? 'jumlah' : 'nilai']: value }) };
                correct.submit(payload, onClose);
            }}
        />
    );
}

/** The control totals per company, each figure shown only to its family (PJ-22). */
function TotalsTable({ rows, title, note }: { rows: Totals[]; title: string; note: string }) {
    const { t } = useTranslation();

    return rows.length === 0 ? null : (
        <section className="flex min-w-0 flex-col gap-2" aria-label={title}>
            <h3 className="text-label font-semibold text-text-primary">{title}</h3>
            <p className="text-caption text-text-secondary">{note}</p>
            <div className="overflow-x-auto">
                <table className="w-full text-body">
                    <thead>
                        <tr className="text-left text-caption text-text-secondary">
                            <th className="py-1 pr-4 font-medium">{t('import.field.company')}</th>
                            <th className="py-1 pr-4 text-right font-medium">{t('import.totals.stock_qty')}</th>
                            <th className="py-1 pr-4 text-right font-medium">{t('import.totals.stock_cost')}</th>
                            <th className="py-1 pr-4 text-right font-medium">{t('import.totals.receivables')}</th>
                            <th className="py-1 text-right font-medium">{t('import.totals.credits')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.company} className="border-t border-border-subtle">
                                <td className="py-1 pr-4"><Code>{row.company}</Code></td>
                                <td className="py-1 pr-4 text-right tabular-nums">{row.stock_qty ?? '—'}</td>
                                <td className="py-1 pr-4 text-right">{row.stock_cost === null ? '—' : <Money value={row.stock_cost} />}</td>
                                <td className="py-1 pr-4 text-right">{row.receivables === null ? '—' : <Money value={row.receivables} />}</td>
                                <td className="py-1 text-right">{row.credits === null ? '—' : <Money value={row.credits} />}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function Report({ batch }: { batch: Detail }) {
    const { t } = useTranslation();

    return (
        <div className="flex min-w-0 flex-col gap-4">
            <dl className="flex flex-wrap gap-x-6 gap-y-2" data-slot="import-counts">
                {COUNTS.map((state) => (
                    <div key={state} className="flex items-baseline gap-2">
                        <dt className="text-caption text-text-secondary">{t(`import.count.${state}` as TranslationKey)}</dt>
                        <dd className="text-body font-semibold text-text-primary tabular-nums">{batch.counts[state] ?? 0}</dd>
                    </div>
                ))}
            </dl>
            <TotalsTable rows={batch.totals} title={t('import.totals.title')} note={t('import.totals.note')} />
            <TotalsTable rows={batch.committed_totals} title={t('import.totals.committed')} note={t('import.totals.committed_note')} />
            {batch.problems.length > 0 && (
                <section className="flex min-w-0 flex-col gap-2" aria-label={t('import.problems.title')}>
                    <h3 className="text-label font-semibold text-text-primary">{t('import.problems.title')}</h3>
                    <ul role="list" className="flex flex-col divide-y divide-border-subtle rounded-md border border-border-subtle" data-slot="import-problems">
                        {batch.problems.map((problem) => (
                            <li key={`${String(problem.row)}-${problem.kind}`} className="flex min-w-0 flex-col gap-1 p-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-body font-semibold text-text-primary">{t('import.problems.row', { nomor: String(problem.row) })}</span>
                                    {problem.identity !== '' && <Code>{problem.identity}</Code>}
                                    <span className="text-caption text-text-secondary">{t(`import.count.${problem.state}` as TranslationKey)}</span>
                                </div>
                                <ul role="list" className="flex flex-col gap-0.5 text-body text-text-secondary">
                                    {problem.errors.map((error, index) => <li key={String(index)}>{reasonText(t, error)}</li>)}
                                </ul>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}

export default function OpeningImport() {
    const { t } = useTranslation();
    const { can, kinds, companies, batches, more, batch } = usePage<Props>().props;
    // The command's answer is the page's flash data, never a prop (Inertia::flash).
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };
    const [preparing, setPreparing] = useState(false);
    const [signing, setSigning] = useState(false);
    const [committing, setCommitting] = useState(false);
    const [aborting, setAborting] = useState(false);
    const [reason, setReason] = useState('');
    const [correcting, setCorrecting] = useState<Detail['facts'][number] | null>(null);
    const progress = usePolledState(batch);
    const dryRun = useCommand<Record<string, never>>(() => `/data-master/impor/${batch?.id ?? ''}/uji`, 'post');
    const commit = useCommand<Record<string, never>>(() => `/data-master/impor/${batch?.id ?? ''}/terapkan`, 'post');
    const abort = useCommand<{ alasan: string }>(() => `/data-master/impor/${batch?.id ?? ''}/batalkan`, 'post');
    const state = batch?.state;
    const next = batch === null ? null
        : state === 'PREPARED' || state === 'DRY_RUN_FAILED' ? <Button pending={dryRun.pending} disabled={dryRun.frozen} onClick={() => { dryRun.submit({}); }}>{t(state === 'DRY_RUN_FAILED' || batch.report.state === 'INVALID' ? 'import.action.dry_run_again' : 'import.action.dry_run')}</Button>
            : state === 'DRY_RUN_PASSED' ? <Button onClick={() => { setSigning(true); }}>{t('import.action.sign_off')}</Button>
                : (state === 'SIGNED_OFF' || state === 'STOPPED') && can.commit ? <Button onClick={() => { commit.clear(); setCommitting(true); }}>{t(state === 'STOPPED' ? 'import.action.resume' : 'import.action.commit')}</Button>
                    : null;
    const abortable = batch !== null && can.commit && ['PREPARED', 'DRY_RUN_FAILED', 'DRY_RUN_PASSED', 'SIGNED_OFF'].includes(batch.state) && (batch.counts.COMMITTED ?? 0) === 0;

    return (
        <AppShell title={t('pages.pg_058.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_058.title')} primary={can.prepare ? <Button size="lg" onClick={() => { setPreparing(true); }}><Plus aria-hidden />{t('import.action.prepare')}</Button> : undefined} />
                {flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' && <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} />}
                <p className="text-body text-text-secondary">{t('import.intro')}</p>
                {batches.length === 0 ? <RegionEmpty text={t('import.empty')} action={can.prepare ? <Button onClick={() => { setPreparing(true); }}>{t('import.action.prepare')}</Button> : undefined} /> : (
                    <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle rounded-md border border-border-subtle" data-slot="import-batches">
                        {batches.map((row) => (
                            <li key={row.id}>
                                <a href={`/data-master/impor?batch=${row.id}`} aria-current={batch?.id === row.id ? 'true' : undefined}
                                    className="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-1 p-3 hover:bg-surface-hover aria-[current=true]:bg-tint-lime">
                                    <span className="text-body font-semibold text-text-primary">{t(`import.kind.${row.kind}` as TranslationKey)}</span>
                                    <span className="text-caption text-text-secondary">{t('import.field.cutoff')}: <DateText value={row.cutoff} /></span>
                                    <span className="text-caption text-text-secondary">{row.companies.join(', ')}</span>
                                    <StatusBadge family="import_batch" state={row.state} size="sm" />
                                </a>
                            </li>
                        ))}
                    </ul>
                )}
                {more !== null && <a href={`/data-master/impor?setelah=${more}`} className="self-start text-body text-accent-link underline underline-offset-2">{t('import.more')}</a>}
                {batch !== null && (
                    <Card className="flex min-w-0 flex-col gap-4 p-4" data-slot="import-batch">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex min-w-0 flex-col gap-1">
                                <h2 className="text-heading-sm font-semibold text-text-primary">{t(`import.kind.${batch.kind}` as TranslationKey)}</h2>
                                <p className="text-caption text-text-secondary">{t('import.field.cutoff')}: <DateText value={batch.cutoff} /> · {batch.companies.join(', ')} · {t('import.prepared_by', { nama: batch.prepared_by })} · <DateTimeText value={batch.recorded_at} /></p>
                            </div>
                            <StatusBadge family="import_batch" state={batch.state} />
                        </div>
                        {batch.state === 'DRY_RUN_RUNNING' && <p className="text-body text-text-secondary" role="status">{t('import.report.running')}</p>}
                        {batch.state === 'DRY_RUN_FAILED' && <p className="text-body text-warning" role="status">{t('import.report.failed')}</p>}
                        {batch.state === 'COMMITTING' && <p className="text-body text-text-secondary" role="status">{t('import.progress', { jumlah: String(progress?.committed ?? batch.counts.COMMITTED ?? 0), sisa: String(progress?.remaining ?? batch.counts.VALID ?? 0) })}</p>}
                        {batch.state === 'STOPPED' && <p className="text-body text-warning" role="status">{t('import.stopped')}</p>}
                        <dl className="flex flex-wrap gap-x-6 gap-y-1 text-body" data-slot="import-signoffs">
                            <div className="flex gap-2"><dt className="text-text-secondary">{t('import.signoff.admin')}</dt><dd className="text-text-primary">{batch.admin_signoff === null ? t('import.signoff.none') : batch.admin_signoff.by}</dd></div>
                            <div className="flex gap-2"><dt className="text-text-secondary">{t('import.signoff.owner')}</dt><dd className="text-text-primary">{batch.owner_signoff === null ? t('import.signoff.none') : batch.owner_signoff.by}</dd></div>
                        </dl>
                        <Report batch={batch} />
                        {[dryRun, commit, abort].map((command, index) => command.answer !== null && <OutcomeMessage key={String(index)} answer={command.answer} />)}
                        {(next !== null || abortable) && (
                            <div className="flex flex-wrap gap-2">
                                {next}
                                {abortable && <Button variant="outline" onClick={() => { abort.clear(); setAborting(true); }}>{t('import.action.abort')}</Button>}
                            </div>
                        )}
                        {batch.facts.length > 0 && (
                            <section className="flex min-w-0 flex-col gap-2" aria-label={t('import.facts.title')}>
                                <h3 className="text-label font-semibold text-text-primary">{t('import.facts.title')}</h3>
                                <p className="text-caption text-text-secondary">{t('import.facts.never_again')}</p>
                                <ul role="list" className="flex flex-col divide-y divide-border-subtle rounded-md border border-border-subtle" data-slot="import-facts">
                                    {batch.facts.map((fact) => (
                                        <li key={fact.row} className="flex flex-wrap items-center justify-between gap-2 p-3">
                                            <span className="text-body text-text-primary">{t('import.problems.row', { nomor: String(fact.row) })} · <Code>{fact.identity}</Code> · {t(`import.kind.${fact.kind}` as TranslationKey)}</span>
                                            <Button size="sm" variant="outline" onClick={() => { setCorrecting(fact); }}>{t('import.action.correct')}</Button>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                    </Card>
                )}
            </div>
            {preparing && <PrepareSheet kinds={kinds} companies={companies} onClose={() => { setPreparing(false); }} />}
            {signing && batch !== null && <SignOffSheet batch={batch} onClose={() => { setSigning(false); }} />}
            {correcting !== null && batch !== null && <CorrectDialog batch={batch} fact={correcting} onClose={() => { setCorrecting(null); }} />}
            {batch !== null && (
                <ConfirmCommand
                    open={committing}
                    onOpenChange={setCommitting}
                    title={t(batch.state === 'STOPPED' ? 'import.action.resume' : 'import.action.commit')}
                    consequence={t('import.confirm.commit', { jenis: t(`import.kind.${batch.kind}` as TranslationKey), perusahaan: batch.companies.join(', '), tanggal: batch.cutoff })}
                    confirmLabel={t(batch.state === 'STOPPED' ? 'import.action.resume' : 'import.action.commit')}
                    pending={commit.pending}
                    answer={commit.answer}
                    onConfirm={() => { commit.submit({}, () => { setCommitting(false); }); }}
                />
            )}
            {batch !== null && (
                <ConfirmCommand
                    open={aborting}
                    onOpenChange={setAborting}
                    title={t('import.action.abort')}
                    destructive
                    consequence={t('import.confirm.abort')}
                    reason={{ value: reason, onChange: setReason, error: abort.errors.alasan }}
                    confirmLabel={t('import.action.abort')}
                    pending={abort.pending}
                    answer={abort.answer}
                    onConfirm={() => { abort.submit({ alasan: reason }, () => { setAborting(false); }); }}
                />
            )}
        </AppShell>
    );
}
