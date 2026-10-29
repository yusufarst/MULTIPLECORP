// PG-011 *Perjalanan Proyek* (ROUTE-011, ROUTE-153; NAV-011; SCR-10, SCR-14; IA §5.5, §8.2; UXN-02; D-IA-09; TASK-032):
// the project as a guided journey and the hub every later step starts from. At once: the header and its one primary
// action, *Tahap proyek* with the current stage marked (GR-03), *Sekarang · Berikutnya · Hambatan*, the derived part strip
// on the desktop (PT-31), "Posisi keuangan" with `finance.view` and "Rincian proyek". Then three deferred groups, each
// with its own loading state and error, the others staying (QB-02; PT-19; UXS-44): the completion predicates —
// the blockers and "Yang tersisa sebelum selesai" —, "Kesiapan dokumen" and *Riwayat*. *Tindakan Lain* holds "Ubah
// proyek" and "Buang Draf", whose confirmation says that the number is retired (IP-08; MSG-39; L-37). A part of a family
// the account lacks is absent (§13.7); the parts of later Tasks arrive with them.
import { Deferred, router, usePage } from '@inertiajs/react';
import { type ReactNode, useState } from 'react';

import { CompanyTag } from '@/components/company';
import { ConfirmCommand } from '@/components/confirm-dialog';
import { CorrectionTrail } from '@/components/correction-trail';
import { OutcomeMessage } from '@/components/outcome-message';
import { type MenuAction, PageHeader } from '@/components/page-header';
import { Disclosure, FactsList, NowStrip, PartStrip, PositionBlock, StageTrack } from '@/components/record-blocks';
import { RegionEmpty, RegionLoading } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Code, DateText, DateTimeText, Money } from '@/components/values';
import { type Checklist, ChecklistSection } from '@/features/adm/checklist';
import { typeName } from '@/features/doc/labels';
import { type Fulfilment, FulfilmentSection } from '@/features/prj/fulfilment';
import { type Failed, isFailed, ItemText, type Journey as JourneyShape, predicateName, stageName } from '@/features/prj/journey';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Line = { no: number; product: string; description: string; quantity: string; unit: string; mode: 'WAREHOUSE' | 'DROPSHIP' | 'SERVICE'; price?: string | null };
type Link = { id: string | null; number: string | null; title: string | null; visible: boolean; reason: string | null };
type Project = {
    id: string;
    number: string;
    title: string;
    state: 'DRAFT' | 'ACTIVE' | 'CANCELLED' | 'COMPLETED_NORMAL' | 'COMPLETED_FORCED';
    channel: 'REGULAR' | 'SIPLAH';
    deadline: string | null;
    company: { code: string; name: string };
    client: string;
    unit: string | null;
    address: { label: string | null; lines: string | null; city: string | null } | null;
    contact: { name: string; phone: string | null } | null;
    link: Link | null;
    siplah: (Record<string, string | null> & { locked: boolean }) | null;
    lines: Line[];
    recorded_at: string;
    lock_version: number;
};
type Documents = { rows: { id: string; type: string; type_name: string; state: string; number: string | null; rendition: string | null }[]; more: boolean; checklist: Checklist | null };
type History = {
    events: { kind: string; from: string | null; to: string | null; reason: string | null; by: string | null; at: string }[];
    corrects: { relation: string; type: string; id: string; reason: string | null; by: string | null; at: string }[];
    corrected_by: { relation: string; type: string; id: string; reason: string | null; by: string | null; at: string }[];
};
type Props = {
    project: Project;
    journey: JourneyShape | null;
    financial: { confirmed: string; invoiced: string } | null;
    can: { edit: boolean; discard: boolean };
    fulfilment: Fulfilment & { today: string };
    predicates?: JourneyShape | null | Failed;
    documents?: Documents | Failed;
    history?: History | Failed;
};

const SIPLAH_TEXTS = ['external_reference', 'program_code', 'account_code', 'notes'] as const;
const SIPLAH_AMOUNTS = ['siplah_value', 'real_purchase_value', 'fee_amount', 'va_fee_amount', 'cashback_amount', 'refund_amount'] as const;

const modeName = (mode: string): TranslationKey => `projects.mode.${mode.toLowerCase()}` as TranslationKey;
const siplahName = (field: string): TranslationKey => `projects.siplah.${field}` as TranslationKey;

/** A command's success on the page its answer opened, once (IP-01). */
function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

/** A deferred group's failure: its own message and "Muat Ulang", the page and the other groups staying (PT-19). */
function GroupFailed({ group }: { group: string }) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-wrap items-center gap-3 text-body text-text-secondary" role="status">
            {t('projects.journey.group_failed')}
            <Button variant="outline" size="sm" onClick={() => { router.reload({ only: [group] }); }}>
                {t('projects.journey.reload')}
            </Button>
        </div>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Card className="flex min-w-0 flex-col gap-3 p-4 md:p-6">
            <h2 className="text-heading-sm font-semibold text-text-primary">{title}</h2>
            {children}
        </Card>
    );
}

export default function Journey({ project, journey, financial, can, fulfilment, predicates, documents, history }: Props) {
    const { t } = useTranslation();
    const [discarding, setDiscarding] = useState(false);
    const discard = useCommand<Record<string, never>>(() => `/proyek/${project.id}/hapus-draf`, 'post');
    // The predicates group carries the whole journey with its blockers once it arrives; until then the root facts' one.
    const full = predicates !== undefined && predicates !== null && !isFailed(predicates) ? predicates : null;
    const shown = full ?? journey;
    const actions: MenuAction[] = [
        ...(can.edit ? [{ label: t('actions.edit_project'), onSelect: () => { router.visit(`/proyek/${project.id}/ubah`); } }] : []),
        ...(can.discard ? [{ label: t('actions.discard_draft'), tone: 'danger' as const, onSelect: () => { discard.clear(); setDiscarding(true); } }] : []),
    ];
    const primary = shown?.next?.href ? (
        // The next step's sentence may be long: the button wraps it rather than widening the page (TL-11).
        <Button size="lg" className="h-auto min-h-control-lg max-w-full shrink whitespace-normal py-2 text-left leading-snug" render={<a href={shown.next.href} />}>
            {t(shown.next.key as TranslationKey, shown.next.params)}
        </Button>
    ) : undefined;
    const stages = (shown?.stages ?? []).map((stage) => ({ key: stage.key, label: t(stageName(stage.key)), state: stage.state }));
    const blockers = full === null ? (isFailed(predicates) ? <GroupFailed group="predicates" /> : <span className="text-text-secondary">{t('states.loading')}</span>)
        : full.blockers.length === 0 ? undefined
            : (
                <ul role="list" className="flex flex-col gap-1">
                    {full.blockers.map((item, index) => (
                        <li key={`${item.key}-${String(index)}`}><ItemText item={item} /></li>
                    ))}
                </ul>
            );

    return (
        <AppShell title={t('pages.pg_011.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader
                    title={project.title}
                    back={{ label: t('pages.pg_010.title'), href: '/proyek' }}
                    crumbs={[{ label: t('pages.pg_010.title'), href: '/proyek' }]}
                    facts={
                        <>
                            <CompanyTag code={project.company.code} />
                            <Code>{project.number}</Code>
                            <StatusBadge family="project" state={project.state} size="sm" />
                            <span>{project.client}</span>
                            {project.deadline !== null && (
                                <span>
                                    {t('projects.field.deadline')}: <DateText value={project.deadline} />
                                </span>
                            )}
                        </>
                    }
                    primary={primary}
                    actions={actions}
                />
                <PageAnswer />
                {shown?.outcome != null ? (
                    <Card className="p-4">
                        <p className="text-body text-text-primary">{t('projects.journey.closed')}</p>
                    </Card>
                ) : shown !== null && (
                    <section aria-label={t('journey.track')} className="flex min-w-0 flex-col gap-4">
                        <StageTrack label={t('journey.track')} stages={stages} />
                        <NowStrip
                            now={shown.current === null ? '—' : t(stageName(shown.current))}
                            next={shown.next === null ? '—' : <ItemText item={{ ...shown.next, href: null }} />}
                            blockers={blockers}
                        />
                        <PartStrip parts={(shown.stages).map((stage) => ({
                            name: t(stageName(stage.key)),
                            state: stage.state === 'complete' ? 'DONE' : stage.state === 'upcoming' ? 'NOT_STARTED' : 'IN_PROGRESS',
                            fact: stage.key === 'agreement' ? t('projects.journey.lines', { n: project.lines.length }) : '',
                        }))} />
                        {shown.not_needed.length > 0 && (
                            <Disclosure title={t('journey.not_needed')}>
                                <p className="text-body text-text-secondary">{shown.not_needed.map((key) => t(stageName(key))).join(', ')}</p>
                            </Disclosure>
                        )}
                    </section>
                )}
                <div className="grid min-w-0 gap-6 lg:grid-cols-2">
                    <Section title={t('journey.remaining')}>
                        <Deferred data="predicates" fallback={<RegionLoading />}>
                            {isFailed(predicates) ? <GroupFailed group="predicates" />
                                : full === null || full.remaining.length === 0 ? <p className="text-body text-text-secondary">{t('projects.journey.nothing_remaining')}</p>
                                    : (
                                        <ul role="list" className="flex flex-col gap-3">
                                            {full.remaining.map((predicate) => (
                                                <li key={predicate.predicate} className="flex flex-col gap-1">
                                                    <span className="text-label font-semibold text-text-primary">{t(predicateName(predicate.predicate))}</span>
                                                    <ul role="list" className="flex flex-col gap-0.5 text-body text-text-secondary">
                                                        {predicate.items.map((item, index) => (
                                                            <li key={`${item.key}-${String(index)}`}><ItemText item={item} /></li>
                                                        ))}
                                                    </ul>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                        </Deferred>
                    </Section>
                    <Section title={t('journey.docs_readiness')}>
                        <Deferred data="documents" fallback={<RegionLoading />}>
                            {documents === undefined ? null : isFailed(documents) ? <GroupFailed group="documents" /> : (
                                <div className="flex min-w-0 flex-col gap-4">
                                    {/* The administrative checklist, missing items first (J-ADM-01; TASK-035). */}
                                    {documents.checklist !== null && <ChecklistSection projectId={project.id} checklist={documents.checklist} />}
                                    {documents.rows.length === 0 ? <RegionEmpty text={t('projects.journey.no_documents')} />
                                    : (
                                        <ul role="list" className="flex flex-col divide-y divide-border-subtle">
                                            {documents.rows.map((row) => (
                                                <li key={row.id} className="flex min-w-0 flex-wrap items-center justify-between gap-2 py-2">
                                                    <a href={`/dokumen/${row.id}`} className="min-w-0 text-body text-accent-link underline underline-offset-2">
                                                        {t(typeName(row.type))}
                                                        {row.number !== null && <> · <Code>{row.number}</Code></>}
                                                    </a>
                                                    <StatusBadge family="document_version" state={row.state as 'DRAFT'} size="sm" />
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            )}
                        </Deferred>
                    </Section>
                </div>
                {/* The confirmation and *Item dan pemenuhan* (J-PRJ-02; J-FUL-01; TASK-034). */}
                <FulfilmentSection projectId={project.id} lockVersion={project.lock_version} fulfilment={fulfilment} today={fulfilment.today} title={(children) => <Section title={t('fulfilment.title')}>{children}</Section>} />
                {financial !== null && (
                    <Section title={t('journey.financial')}>
                        <PositionBlock title={t('journey.financial')} lines={[
                            { label: t('journey.pos.confirmed'), value: <Money value={financial.confirmed} /> },
                            { label: t('journey.pos.invoiced'), value: <Money value={financial.invoiced} />, emphasis: true },
                        ]} />
                    </Section>
                )}
                <Section title={t('journey.details')}>
                    <FactsList facts={[
                        { label: t('projects.field.company'), value: `${project.company.code} · ${project.company.name}` },
                        { label: t('projects.field.client'), value: project.client },
                        { label: t('projects.field.unit'), value: project.unit ?? '—' },
                        { label: t('projects.field.address'), value: project.address === null ? '—' : [project.address.label, project.address.lines, project.address.city].filter((part) => part !== null).join(', ') },
                        { label: t('projects.field.contact'), value: project.contact === null ? '—' : [project.contact.name, project.contact.phone].filter((part) => part !== null).join(' · ') },
                        { label: t('projects.field.channel'), value: t(project.channel === 'SIPLAH' ? 'projects.channel.siplah' : 'projects.channel.regular') },
                        ...(project.link === null ? [] : [{
                            label: t('projects.field.link'),
                            value: project.link.visible && project.link.id !== null
                                ? <a href={`/proyek/${project.link.id}`} className="text-accent-link underline underline-offset-2">{`${project.link.number ?? ''} · ${project.link.title ?? ''}`}</a>
                                : t('projects.link.other_company'),
                        }]),
                    ]} />
                    {project.link?.reason != null && <p className="text-body text-text-secondary">{t('projects.link.reason', { alasan: project.link.reason })}</p>}
                    {project.siplah !== null && (
                        <Disclosure title={t('projects.siplah.title')} defaultOpen>
                            <FactsList facts={[
                                ...SIPLAH_TEXTS.map((field) => ({ label: t(siplahName(field)), value: project.siplah?.[field] ?? '—' })),
                                ...SIPLAH_AMOUNTS.filter((field) => project.siplah !== null && field in project.siplah).map((field) => ({
                                    label: t(siplahName(field)),
                                    value: project.siplah?.[field] == null ? '—' : <Money value={project.siplah[field] ?? '0'} />,
                                    numeric: true,
                                })),
                            ]} />
                            <p className="text-caption text-text-tertiary">{t('projects.siplah.manual')}</p>
                        </Disclosure>
                    )}
                    {project.lines.length === 0 ? (
                        <p className="text-body text-text-secondary">{t('projects.form.no_lines')}</p>
                    ) : (
                        <ul role="list" aria-label={t('projects.form.lines')} className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {project.lines.map((line) => (
                                <li key={line.no} className="flex min-w-0 flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2">
                                    <span className="min-w-0">
                                        <span className="block font-medium text-text-primary">{line.product}</span>
                                        {line.description !== line.product && <span className="block text-caption text-text-secondary">{line.description}</span>}
                                    </span>
                                    <span className="flex shrink-0 flex-wrap items-baseline gap-x-3 text-body text-text-secondary">
                                        <span className="tabular-nums text-text-primary">{`${line.quantity.replace(/\.?0+$/, '')} ${line.unit}`}</span>
                                        <span>{t(modeName(line.mode))}</span>
                                        {'price' in line && <span>{line.price == null ? '—' : <Money value={line.price} />}</span>}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
                <Section title={t('projects.journey.history')}>
                    <Deferred data="history" fallback={<RegionLoading />}>
                        {history === undefined ? null : isFailed(history) ? <GroupFailed group="history" /> : (
                            <div className="flex min-w-0 flex-col gap-4">
                                <ol className="flex flex-col divide-y divide-border-subtle">
                                    {history.events.map((event, index) => (
                                        <li key={`${event.kind}-${String(index)}`} className="flex min-w-0 flex-col gap-0.5 py-2">
                                            <span className="text-body text-text-primary">{t(`projects.history.${event.kind.toLowerCase()}` as TranslationKey)}</span>
                                            <span className="text-caption text-text-secondary">
                                                <DateTimeText value={event.at} />
                                                {event.by !== null && ` · ${event.by}`}
                                            </span>
                                            {event.reason !== null && <span className="text-caption text-text-secondary">{event.reason}</span>}
                                        </li>
                                    ))}
                                </ol>
                                <CorrectionTrail corrects={history.corrects} correctedBy={history.corrected_by} hrefOf={() => null} />
                            </div>
                        )}
                    </Deferred>
                </Section>
            </div>
            <ConfirmCommand
                open={discarding}
                onOpenChange={setDiscarding}
                title={t('projects.discard.title')}
                consequence={t('projects.discard.consequence', { nomor: project.number })}
                confirmLabel={t('actions.discard_draft')}
                destructive
                pending={discard.pending}
                answer={discard.answer?.kind === 'COMMITTED' ? null : discard.answer}
                onConfirm={() => { discard.submit({}); }}
            />
        </AppShell>
    );
}
