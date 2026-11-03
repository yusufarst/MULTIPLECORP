// PG-012 *Penyelesaian Proyek* (ROUTE-012; NAV-012; SCR-14; WF-PRJ-03; J-PRJ-03; UXS-37; TASK-055): the ten conditions as a derived readiness list (PT-29) — each with its state and, unmet,
// what is missing and where it is resolved, derived again each time the page is shown (L-01) —; "Selesaikan Proyek" with `projects.complete` after its confirmation (INT-161; MSG-39);
// for the Owner only, "Selesaikan Paksa" with the unmet conditions and the residual obligations as a snapshot, the mandatory reason and the statement that nothing is fabricated,
// the confirmation bound to the snapshot shown (INT-162; ST-04). A completed project shows its record: a Force Complete's unmet conditions and residual obligations stay listed.
import { usePage } from '@inertiajs/react';
import { CircleCheck, CircleDashed } from 'lucide-react';
import { useState } from 'react';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Code, DateTimeText, Money } from '@/components/values';
import { ItemText, type JourneyItem, predicateName } from '@/features/prj/journey';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type State = 'DRAFT' | 'ACTIVE' | 'CANCELLED' | 'COMPLETED_NORMAL' | 'COMPLETED_FORCED';
type Condition = { key: string; met: boolean; applies: boolean; items: JourneyItem[]; href: string | null };
type Obligation = { kind: string; ref?: string; label: string; amount: string | null; quantity: string | null };
type Props = {
    project: { id: string; number: string; title: string; state: State };
    conditions: Condition[];
    unmet: string[];
    can: { complete: boolean; force: boolean };
    force: { unmet: string[]; obligations: Obligation[]; hash: string } | null;
    record: { kind: string; reason: string | null; by: string | null; at: string; unmet: string[]; obligations: Obligation[] } | null;
};

function Obligations({ rows }: { rows: Obligation[] }) {
    const { t } = useTranslation();

    return rows.length === 0 ? <p className="text-body text-text-secondary">{t('completion.obligations.none')}</p> : (
        <ul role="list" className="flex flex-col gap-1" data-slot="completion-obligations">
            {rows.map((row, index) => (
                <li key={`${row.kind}-${String(index)}`} className="flex flex-wrap items-baseline gap-2 text-body text-text-primary">
                    <span className="font-semibold">{t(`completion.obligation.${row.kind}` as TranslationKey)}</span>
                    <span>{row.kind === 'case' ? t(`cases.kind.${row.label.toLowerCase()}` as TranslationKey) : row.label}</span>
                    {row.amount !== null && <Money value={row.amount} />}
                    {row.quantity !== null && <span className="text-text-secondary">{row.quantity}</span>}
                </li>
            ))}
        </ul>
    );
}

export default function Completion({ project, conditions, unmet, can, force, record }: Props) {
    const { t } = useTranslation();
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };
    const [completing, setCompleting] = useState(false);
    const [forcing, setForcing] = useState(false);
    const [reason, setReason] = useState('');
    const complete = useCommand<Record<string, never>>(() => `/proyek/${project.id}/selesaikan`, 'post');
    const forced = useCommand<{ alasan: string; snapshot: string }>(() => `/proyek/${project.id}/selesaikan-paksa`, 'post');
    const back = `/proyek/${project.id}`;

    return (
        <AppShell title={t('pages.pg_012.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader
                    title={t('pages.pg_012.title')}
                    back={{ label: t('pages.pg_011.title'), href: back }}
                    crumbs={[{ label: t('pages.pg_010.title'), href: '/proyek' }, { label: project.title, href: back }]}
                    facts={<><Code>{project.number}</Code><span>{project.title}</span><StatusBadge family="project" state={project.state} size="sm" /></>}
                    primary={can.complete ? <Button size="lg" onClick={() => { complete.clear(); setCompleting(true); }}>{t('actions.complete_project')}</Button> : undefined}
                />
                {flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' && <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} />}
                <p className="text-body text-text-secondary">{t('completion.intro')}</p>
                <section aria-label={t('completion.conditions')} className="flex min-w-0 flex-col gap-2">
                    <h2 className="text-heading-sm font-semibold text-text-primary">{t('completion.conditions')}</h2>
                    <ol role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle rounded-md border border-border-subtle">
                        {conditions.map((condition) => (
                            <li key={condition.key} data-slot="completion-condition" data-met={condition.met ? 'true' : 'false'} className="flex min-w-0 flex-col gap-1 p-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    {condition.met ? <CircleCheck className="size-4 text-success" aria-hidden /> : <CircleDashed className="size-4 text-warning" aria-hidden />}
                                    <span className="text-body font-semibold text-text-primary">{t(predicateName(condition.key))}</span>
                                    <span className="text-caption text-text-secondary">{t(condition.met ? (condition.applies ? 'completion.state.met' : 'completion.state.not_applicable') : 'completion.state.unmet')}</span>
                                </div>
                                {!condition.met && (
                                    <ul role="list" className="flex flex-col gap-0.5 pl-6 text-body text-text-secondary">
                                        {condition.items.map((item, index) => <li key={`${item.key}-${String(index)}`}><ItemText item={item} /></li>)}
                                    </ul>
                                )}
                                {condition.href !== null && <a href={condition.href} className="pl-6 text-body text-accent-link underline underline-offset-2">{t('completion.resolve')}</a>}
                            </li>
                        ))}
                    </ol>
                    {unmet.length === 0 && project.state === 'ACTIVE' && <p className="text-body text-text-secondary">{t('completion.all_met')}</p>}
                </section>
                {force !== null && (
                    <Card className="flex flex-col gap-3 p-4" data-slot="completion-force">
                        <h2 className="text-heading-sm font-semibold text-text-primary">{t('completion.force.title')}</h2>
                        <p className="text-body text-text-secondary">{t('completion.force.statement')}</p>
                        <p className="text-body text-text-primary">{t('completion.force.unmet', { syarat: force.unmet.map((key) => t(predicateName(key))).join(', ') })}</p>
                        <h3 className="text-label font-semibold text-text-primary">{t('completion.obligations.title')}</h3>
                        <Obligations rows={force.obligations} />
                        <Button variant="outline" className="self-start" onClick={() => { forced.clear(); setForcing(true); }}>{t('completion.force.action')}</Button>
                    </Card>
                )}
                {record !== null && (
                    <Card className="flex flex-col gap-2 p-4" data-slot="completion-record">
                        <h2 className="text-heading-sm font-semibold text-text-primary">{t(record.kind === 'FORCE_COMPLETE' ? 'completion.record.forced' : 'completion.record.normal')}</h2>
                        <p className="text-body text-text-secondary">{record.by ?? '—'} · <DateTimeText value={record.at} /></p>
                        {record.reason !== null && <p className="text-body text-text-primary">{t('completion.record.reason', { alasan: record.reason })}</p>}
                        {record.kind === 'FORCE_COMPLETE' && (
                            <>
                                <p className="text-body text-text-primary">{t('completion.force.unmet', { syarat: record.unmet.map((key) => t(predicateName(key))).join(', ') })}</p>
                                <h3 className="text-label font-semibold text-text-primary">{t('completion.obligations.title')}</h3>
                                <Obligations rows={record.obligations} />
                            </>
                        )}
                    </Card>
                )}
            </div>
            <ConfirmCommand
                open={completing}
                onOpenChange={setCompleting}
                title={t('actions.complete_project')}
                consequence={t('completion.confirm.complete')}
                confirmLabel={t('actions.complete_project')}
                pending={complete.pending}
                answer={complete.answer}
                onConfirm={() => { complete.submit({}, () => { setCompleting(false); }); }}
            />
            {force !== null && (
                <ConfirmCommand
                    open={forcing}
                    onOpenChange={setForcing}
                    title={t('completion.force.action')}
                    destructive
                    consequence={t('completion.confirm.force', { syarat: force.unmet.map((key) => t(predicateName(key))).join(', ') })}
                    reason={{ value: reason, onChange: setReason, error: forced.errors.alasan }}
                    confirmLabel={t('completion.force.action')}
                    pending={forced.pending}
                    answer={forced.answer}
                    // The snapshot this page shows now: after a CONFLICT the page carries the current one to confirm again (ST-04).
                    onConfirm={() => { forced.submit({ alasan: reason, snapshot: force.hash }, () => { setForcing(false); }); }}
                />
            )}
        </AppShell>
    );
}
