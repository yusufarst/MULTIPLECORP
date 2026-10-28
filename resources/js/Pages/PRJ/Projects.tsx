// PG-010 *Proyek* (ROUTE-010; NAV-010; SCR-09; IA §8.2; `DIR-077` CA-06; TASK-032): the projects of the companies in the
// account's scope, newest first, each row with its company code, number, title, client, state and deadline at once, and
// its journey stage, blocker marker and next step when the journey summary group arrives (QB-01) — its own loading
// state and error, the rows staying (PT-19). Filtered by company and by state; no stage filter, since no bounded plan
// exists for a derived stage (PFM-04). "Buat Proyek" with `projects.manage`; a row opens PG-011.
import { router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

import { CompanyFilter, CompanyTag } from '@/components/company';
import { DataList, ListContinuation } from '@/components/data-list';
import { FormField } from '@/components/form';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Code, DateText } from '@/components/values';
import { type Failed, isFailed, ItemText, type JourneySummary, StageSummary } from '@/features/prj/journey';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';

type State = 'DRAFT' | 'ACTIVE' | 'CANCELLED' | 'COMPLETED_NORMAL' | 'COMPLETED_FORCED';
type Row = { id: string; company: string; number: string; title: string; client: string; state: State; deadline: string | null; channel: 'REGULAR' | 'SIPLAH' };
type Props = {
    companies: { code: string; name: string }[];
    filter: { perusahaan: string[]; status: State | null };
    rows: Row[];
    more: string | null;
    can: { create: boolean };
    journeys?: Record<string, JourneySummary> | Failed;
};

const STATES: State[] = ['DRAFT', 'ACTIVE', 'COMPLETED_NORMAL', 'COMPLETED_FORCED', 'CANCELLED'];

function visit(query: Record<string, string | null>) {
    const params = new URLSearchParams(window.location.search);

    for (const [key, value] of Object.entries(query)) {
        if (value === null || value === '') {
            params.delete(key);
        } else {
            params.set(key, value);
        }
    }

    router.get(`/proyek${params.size > 0 ? `?${params.toString()}` : ''}`, {}, { preserveScroll: true, preserveState: true });
}

/** A command's success on the page its answer opened, once (IP-01) — "Buang Draf" lands here. */
function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

export default function Projects({ companies, filter, rows, more, can, journeys }: Props) {
    const { t } = useTranslation();
    // PT-08: "Muat Lagi" adds the page after the rows shown on a partial reload, its journeys with it; any other visit
    // shows its first page.
    const [base, setBase] = useState(rows);
    const [shown, setShown] = useState(rows);
    const [next, setNext] = useState(more);
    const [appending, setAppending] = useState(false);
    const [known, setKnown] = useState<Record<string, JourneySummary>>({});
    const [seen, setSeen] = useState(journeys);
    // The page the last journey request was for, so a retry asks for that page's rows (`setelah`), not the first page's.
    const [requested, setRequested] = useState<string | null>(null);
    const [failedPage, setFailedPage] = useState<string | null>(null);

    if (base !== rows) {
        setBase(rows);
        setShown(appending ? [...shown, ...rows.filter((row) => !shown.some((other) => other.id === row.id))] : rows);
        setNext(more);
        setAppending(false);
    }

    if (seen !== journeys) {
        setSeen(journeys);

        if (journeys !== undefined && !isFailed(journeys)) {
            setKnown((earlier) => ({ ...earlier, ...journeys }));
        } else if (isFailed(journeys)) {
            setFailedPage(requested);
        }
    }

    const loadMore = () => {
        if (next === null) return;
        setAppending(true);
        setRequested(next);
        router.reload({ only: ['rows', 'more', 'journeys'], data: { setelah: next }, preserveUrl: true });
    };
    const failed = isFailed(journeys);
    const reloadJourneys = () => {
        setRequested(failedPage);
        router.reload({ only: ['journeys'], data: failedPage === null ? {} : { setelah: failedPage }, preserveUrl: true });
    };
    const stage = (row: Row) => {
        const summary = known[row.id];

        if (summary !== undefined) {
            return summary.outcome !== null ? <span className="text-text-secondary">—</span> : <StageSummary summary={summary} />;
        }

        return failed ? (
            <button type="button" className="text-caption text-accent-link underline underline-offset-2" onClick={reloadJourneys}>
                {t('projects.list.journey_failed')}
            </button>
        ) : (
            <Spinner label={t('projects.list.journey_loading')} delay={0} />
        );
    };
    const nextStep = (row: Row) => {
        const summary = known[row.id];

        if (summary === undefined) {
            return null;
        }

        return summary.next === null ? <span className="text-text-secondary">—</span> : <ItemText item={{ ...summary.next, href: null }} />;
    };
    const create = can.create ? (
        <Button size="lg" render={<a href="/proyek/baru" />}>
            <Icon icon={Plus} size="sm" />
            {t('actions.create_project')}
        </Button>
    ) : undefined;

    return (
        <AppShell title={t('pages.pg_010.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_010.title')} primary={create} />
                <PageAnswer />
                <div className="flex min-w-0 flex-wrap items-end gap-3">
                    {companies.length > 1 && (
                        <CompanyFilter companies={companies} selected={filter.perusahaan} onChange={(codes) => { visit({ perusahaan: codes.join(','), setelah: null }); }} />
                    )}
                    <FormField label={t('projects.field.state')} className="min-w-0 sm:w-60">
                        {(control) => (
                            <NativeSelect {...control} value={filter.status ?? ''} onChange={(event) => { visit({ status: event.target.value, setelah: null }); }}>
                                <option value="">{t('projects.list.all_states')}</option>
                                {STATES.map((state) => (
                                    <option key={state} value={state}>
                                        {t(`status.project.${state.toLowerCase()}` as 'status.project.draft')}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>
                </div>
                {shown.length === 0 ? (
                    <RegionEmpty text={filter.status !== null ? t('projects.list.empty_filtered') : t('projects.list.empty')} action={filter.status === null ? create : undefined} />
                ) : (
                    <>
                        <DataList<Row>
                            caption={t('pages.pg_010.title')}
                            rows={shown}
                            rowKey={(row) => row.id}
                            rowHref={(row) => `/proyek/${row.id}`}
                            columns={[
                                { key: 'company', header: t('projects.field.company'), cell: (row) => <CompanyTag code={row.company} /> },
                                { key: 'number', header: t('projects.field.number'), cell: (row) => <Code>{row.number}</Code> },
                                { key: 'title', header: t('projects.field.title'), cell: (row) => row.title },
                                { key: 'client', header: t('projects.field.client'), cell: (row) => row.client },
                                { key: 'state', header: t('projects.field.state'), cell: (row) => <StatusBadge family="project" state={row.state} size="sm" /> },
                                { key: 'deadline', header: t('projects.field.deadline'), priority: 'low', cell: (row) => (row.deadline === null ? '—' : <DateText value={row.deadline} />) },
                                { key: 'stage', header: t('projects.field.stage'), cell: stage },
                                { key: 'next', header: t('journey.next'), priority: 'low', cell: nextStep },
                            ]}
                            dense={(row) => ({
                                title: `${row.number} · ${row.title}`,
                                secondary: (
                                    <span className="flex min-w-0 flex-col gap-1">
                                        <span>{`${row.company} · ${row.client}`}</span>
                                        {stage(row)}
                                    </span>
                                ),
                                status: <StatusBadge family="project" state={row.state} size="sm" />,
                            })}
                        />
                        <ListContinuation state={next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={loadMore} />
                    </>
                )}
            </div>
        </AppShell>
    );
}
