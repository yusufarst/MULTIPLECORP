// PG-036 *Detail Dokumen* (ROUTE-036, ROUTE-221–ROUTE-225; NAV-036; SCR-35; IA §8.4; WF-DOC-01; J-DOC-01; TASK-029): a
// document's draft in the composer — the record's facts carried and read-only, the date and, where the layout states
// one, the bank destination entered — beside its live preview (D-DS-23), "Simpan Draf" before "Terbitkan {jenis}" and
// its confirmation (IP-08; MSG-39); its versions with their numbers, states and renditions — PENDING with MSG-05, READY
// inline with "Unduh" and "Cetak", FAILED with MSG-08 and never a re-issue (UXS-09; IP-12) —; "Revisi" and "Batalkan
// Dokumen" each with their reason (IP-09; MSG-40). A voided version reads "Dibatalkan" and is never offered as current.
import { Download, Printer, Send } from 'lucide-react';
import { useMemo, useState } from 'react';

import { BusinessDateControl, type BusinessDate } from '@/components/date-field';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { FormField, FormSection } from '@/components/form';
import { OutcomeMessage, type Answer } from '@/components/outcome-message';
import { PageHeader, type MenuAction } from '@/components/page-header';
import { RenditionStatus } from '@/components/rendition-status';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateText, DateTimeText } from '@/components/values';
import { DocumentComposer } from '@/features/doc/composer';
import { typeName } from '@/features/doc/labels';
import { type Paper } from '@/features/doc/paper';
import AppShell from '@/layouts/AppShell';
import { dateLong } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { type CommandForm, useCommand } from '@/lib/use-command';

type Bank = { handle: string; bank_name: string; account_number: string; account_holder: string; branch: string | null };
type Rendition = { state: 'PENDING' | 'RENDERING' | 'READY' | 'FAILED'; attempt: number; attempts: number; file: string | null };
type Version = {
    id: string;
    version_no: number;
    state: 'DRAFT' | 'ISSUED' | 'SUPERSEDED' | 'VOIDED';
    number: string | null;
    business_date: string | null;
    issued_by: string | null;
    voided_at: string | null;
    voided_by: string | null;
    voided_reason: string | null;
    supersedes: string | null;
    rendition: Rendition | null;
};
type Draft = { id: string; version_no: number; lock_version: number; business_date: string | null; bank_account: string | null; bank_accounts: Bank[]; states_bank: boolean; revision_of: string | null; paper: Paper | null; facts: string | null };
type Props = {
    document: { id: string; type: string; type_name: string; mode: string; company: { id: string | null; code: string | null; name: string | null }; prepayment: 'open_not_linked' | 'linked' | null };
    versions: Version[];
    current: string | null;
    draft: Draft | null;
    content: boolean;
    can: { issue: boolean; correct: boolean };
    today: string;
};

/** A page-level answer the server's redirect carried (IP-01), with the Owner's numbering link of a C-10 refusal. */
function useCommandAnswer(command: { answer: Answer | null; unknown: boolean; retry: () => void }) {
    const { t } = useTranslation();

    return command.answer === null ? null : (
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

type IssueCommand = CommandForm<Record<string, string | number | boolean>>;

// The issue's command lives with the page, not the draft: its answer stays once the issued draft is gone (IP-01).
function DraftComposer({ document, draft, today, canIssue, issue }: { document: Props['document']; draft: Draft; today: string; canIssue: boolean; issue: IssueCommand }) {
    const { t } = useTranslation();
    const name = t(typeName(document.type));
    const saved = draft.business_date ?? today;
    const [date, setDate] = useState<BusinessDate>({ date: saved, chosen: saved !== today });
    const [bank, setBank] = useState(draft.bank_account ?? '');
    const [confirming, setConfirming] = useState(false);
    const save = useCommand<Record<string, string | number | boolean>>(() => '/dokumen', 'post');
    const entered = { draf: draft.id, lock_version: draft.lock_version, tanggal: date.date, tanggal_otomatis: !date.chosen, rekening: bank };
    // The issue carries the digest of the facts its paper showed: a record changed since is a CONFLICT (architecture challenge 15a).
    const confirmed = { ...entered, isi: draft.facts ?? '' };
    const chosenBank = draft.bank_accounts.find((account) => account.handle === bank) ?? null;
    // The live preview: the server's paper with the entered date and bank destination, never a number (DC-2).
    const paper = useMemo<Paper | null>(
        () =>
            draft.paper === null
                ? null
                : {
                      ...draft.paper,
                      date: dateLong(date.date),
                      bank: chosenBank === null || !draft.states_bank ? null : { bank_name: chosenBank.bank_name, account_number: chosenBank.account_number, account_holder: chosenBank.account_holder, branch: chosenBank.branch },
                  },
        [draft.paper, draft.states_bank, date.date, chosenBank],
    );
    const saveAnswer = useCommandAnswer(save);

    const form = (
        <FormSection title={t('documents.draft.title', { versi: draft.version_no })}>
            {draft.revision_of !== null && <p className="text-body text-text-secondary">{t('documents.draft.revision_of', { nomor: draft.revision_of })}</p>}
            <BusinessDateControl today={today} value={date} onChange={setDate} />
            {draft.states_bank && (
                <FormField label={t('documents.draft.bank')} error={save.errors.rekening ?? issue.errors.rekening}>
                    {(control) => (
                        <NativeSelect {...control} value={bank} onChange={(event) => { setBank(event.target.value); }}>
                            <option value="">{t('documents.draft.bank_none')}</option>
                            {draft.bank_accounts.map((account) => (
                                <option key={account.handle} value={account.handle}>
                                    {`${account.bank_name} · ${account.account_number} · ${account.account_holder}`}
                                </option>
                            ))}
                        </NativeSelect>
                    )}
                </FormField>
            )}
            {(save.errors.tanggal ?? issue.errors.tanggal) !== undefined && <p className="text-body text-danger">{save.errors.tanggal ?? issue.errors.tanggal}</p>}
            <p className="text-body text-text-secondary">{t('documents.draft.carried')}</p>
        </FormSection>
    );
    const finalize = (
        <div className="flex min-w-0 flex-col gap-3">
            {saveAnswer}
            <div className="flex flex-wrap gap-2">
                <Button variant="outline" pending={save.pending} disabled={save.frozen} onClick={() => { save.submit({ dokumen: document.id, ...entered }); }}>
                    {t('composer.save_draft')}
                </Button>
                {canIssue && (
                    <Button onClick={() => { issue.clear(); setConfirming(true); }}>
                        <Icon icon={Send} size="sm" />
                        {t('documents.action.issue', { jenis: name })}
                    </Button>
                )}
            </div>
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
                title={t('documents.confirm.issue_title', { jenis: name })}
                consequence={t('documents.confirm.issue_consequence')}
                items={<p>{t('documents.confirm.issue_date', { tanggal: dateLong(date.date) })}</p>}
                confirmLabel={t('documents.action.issue', { jenis: name })}
                pending={issue.pending}
                answer={issue.answer}
                onConfirm={() => {
                    issue.submit(confirmed, () => { setConfirming(false); });
                }}
            />
        </>
    );
}

function VersionRow({ version, document, current, content }: { version: Version; document: Props['document']; current: boolean; content: boolean }) {
    const { t } = useTranslation();
    const name = t(typeName(document.type));
    const [polled, setPolled] = useState<string | null>(null);
    const file = version.rendition?.file ?? polled;

    return (
        <li data-version={version.version_no} className="flex min-w-0 flex-col gap-3 py-4">
            <div className="flex min-w-0 flex-wrap items-center gap-3">
                <span className="font-semibold text-text-primary">{t('documents.version', { n: version.version_no })}</span>
                <StatusBadge family="document_version" state={version.state} />
                {version.number !== null && <Code>{version.number}</Code>}
                {version.business_date !== null && <DateText value={version.business_date} />}
                {current && <span className="text-caption text-text-secondary">{t('documents.current')}</span>}
            </div>
            {version.supersedes !== null && <p className="text-body text-text-secondary">{t('documents.revises', { nomor: version.supersedes })}</p>}
            {version.issued_by !== null && <p className="text-body text-text-secondary">{t('documents.issued_by', { nama: version.issued_by })}</p>}
            {version.state === 'VOIDED' && (
                <p className="text-body text-text-secondary">
                    {t('documents.voided_by', { nama: version.voided_by ?? '—' })}
                    {version.voided_at !== null && (
                        <>
                            {' · '}
                            <DateTimeText value={version.voided_at} />
                        </>
                    )}
                    {version.voided_reason !== null && ` · ${version.voided_reason}`}
                </p>
            )}
            {version.state !== 'DRAFT' && version.number !== null && current && <RenditionStatus versionId={version.id} documentName={name} number={version.number} onFile={content ? setPolled : undefined} />}
            {version.state !== 'DRAFT' && !current && version.rendition !== null && <StatusBadge family="rendition" state={version.rendition.state} size="sm" />}
            {content && file !== null && (
                <div className="flex min-w-0 flex-col gap-2">
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" render={<a href={`/berkas/${file}/unduh`} />}>
                            <Icon icon={Download} size="sm" />
                            {t('actions.download')}
                        </Button>
                        <Button variant="outline" size="sm" render={<a href={`/berkas/${file}/tampil`} target="_blank" rel="noopener" />}>
                            <Icon icon={Printer} size="sm" />
                            {t('actions.print')}
                        </Button>
                    </div>
                    {current && <iframe title={t('documents.rendition_frame', { jenis: name, nomor: version.number ?? '' })} src={`/berkas/${file}/tampil`} className="h-(--composer-frame-h) w-full min-w-0 rounded-md border border-border-subtle bg-surface" />}
                </div>
            )}
        </li>
    );
}

export default function DocumentPage({ document, versions, current, draft, content, can, today }: Props) {
    const { t } = useTranslation();
    const name = t(typeName(document.type));
    const issued = versions.find((version) => version.id === current) ?? null;
    const [correcting, setCorrecting] = useState<'revise' | 'void' | null>(null);
    const [reason, setReason] = useState('');
    const revise = useCommand<Record<string, string>>(() => `/dokumen/${document.id}/revisi`, 'post');
    const voiding = useCommand<Record<string, string>>(() => `/dokumen/${document.id}/batalkan`, 'post');
    const issue: IssueCommand = useCommand<Record<string, string | number | boolean>>(() => `/dokumen/${document.id}/terbitkan`, 'post');
    const issueAnswer = useCommandAnswer(issue);
    const correction = correcting === 'void' ? voiding : revise;
    const reviseAnswer = useCommandAnswer(revise);
    const voidAnswer = useCommandAnswer(voiding);
    const actions: MenuAction[] =
        can.correct && issued !== null && draft === null
            ? [
                  { label: t('documents.action.revise'), onSelect: () => { setReason(''); revise.clear(); setCorrecting('revise'); } },
                  { label: t('documents.action.void'), tone: 'danger', onSelect: () => { setReason(''); voiding.clear(); setCorrecting('void'); } },
              ]
            : can.correct && issued !== null
              ? [{ label: t('documents.action.void'), tone: 'danger', onSelect: () => { setReason(''); voiding.clear(); setCorrecting('void'); } }]
              : [];

    return (
        <AppShell title={t('pages.pg_036.title')} back={{ href: '/dokumen', label: t('pages.pg_035.title') }} composer={draft !== null}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader
                    title={issued?.number !== undefined && issued.number !== null ? `${name} ${issued.number}` : name}
                    crumbs={[{ label: t('pages.pg_035.title'), href: '/dokumen' }]}
                    back={{ label: t('pages.pg_035.title'), href: '/dokumen' }}
                    facts={
                        <div className="flex flex-wrap items-center gap-3 text-body text-text-secondary">
                            {document.company.code !== null && <span>{`${document.company.code} · ${document.company.name ?? ''}`}</span>}
                            {document.prepayment !== null && <StatusBadge family="pre_payment_kuitansi" state={document.prepayment === 'linked' ? 'LINKED' : 'OPEN_NOT_LINKED'} />}
                            {document.prepayment === 'open_not_linked' && <span>{t('documents.prepayment_note')}</span>}
                        </div>
                    }
                    actions={actions}
                />
                {reviseAnswer}
                {voidAnswer}
                {issueAnswer}
                {draft !== null && <DraftComposer key={`${draft.id}-${String(draft.lock_version)}`} document={document} draft={draft} today={today} canIssue={can.issue} issue={issue} />}
                {!content && <p className="text-body text-text-secondary">{t('documents.no_content')}</p>}
                <FormSection title={t('documents.versions')}>
                    <ol className="flex min-w-0 flex-col divide-y divide-border-subtle">
                        {versions
                            .filter((version) => version.state !== 'DRAFT')
                            .map((version) => (
                                <VersionRow key={version.id} version={version} document={document} current={version.id === current} content={content} />
                            ))}
                    </ol>
                    {versions.every((version) => version.state === 'DRAFT') && <p className="text-body text-text-secondary">{t('documents.none_issued')}</p>}
                </FormSection>
            </div>
            <ConfirmCommand
                open={correcting !== null}
                onOpenChange={(open) => { if (!open) setCorrecting(null); }}
                title={correcting === 'void' ? t('documents.confirm.void_title', { jenis: name, nomor: issued?.number ?? '' }) : t('documents.confirm.revise_title', { jenis: name, nomor: issued?.number ?? '' })}
                consequence={correcting === 'void' ? t('documents.confirm.void_consequence') : t('documents.confirm.revise_consequence')}
                reason={{ value: reason, onChange: setReason, error: correction.errors.alasan ?? null }}
                confirmLabel={correcting === 'void' ? t('documents.action.void') : t('documents.action.revise')}
                destructive={correcting === 'void'}
                pending={correction.pending}
                answer={correction.answer}
                onConfirm={() => {
                    correction.submit({ alasan: reason, versi: current ?? '' }, () => { setCorrecting(null); });
                }}
            />
        </AppShell>
    );
}

