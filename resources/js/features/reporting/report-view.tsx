// The reports' shared view (PG-051, PG-052; IA §8.6; D-IA-12; D-IA-15; GR-19; GR-23; DESIGN_SYSTEM §10, §17): the report
// switch, the period control with its presets bound to the periods and the company filter — address keys, so a
// reload, the print view and "Ekspor" read the same filters (IP-18; PF-28) —, "Ekspor" with its format, and each table
// one section per company with its own totals and never a total across companies; the receivable ageing a chart with
// its table. The print view is this page prepared for paper, its filters a line of text, its header and footer
// (DP-19). Prefetch stays off: these are S3 pages (PF-35).
import { router, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Bar, BarChart, LabelList, XAxis, YAxis } from 'recharts';

import { CompanyTag } from '@/components/company';
import { DateField } from '@/components/date-field';
import { FormField } from '@/components/form';
import { type Answer, OutcomeMessage } from '@/components/outcome-message';
import { type MenuAction, PageHeader } from '@/components/page-header';
import { RegionEmpty, RegionLoading } from '@/components/region-state';
import { Button } from '@/components/ui/button';
import { ChartContainer } from '@/components/ui/chart';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Icon } from '@/components/ui/icon';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTab } from '@/components/ui/tabs';
import { date, dateTime, money, percent } from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';

export type Column = { key: string; kind: 'text' | 'label' | 'company' | 'date' | 'qty' | 'money' | 'percent'; total: boolean };
export type Section = { company: string | null; name: string | null; rows: (string | null)[][]; total: (string | null)[] | null };
export type ReportTable = { key: string; columns: Column[]; sections: Section[] };
export type Filters = { periode: string; dari: string; sampai: string; perusahaan: string | null };
export type ReportProps = {
    reports: string[];
    report: string;
    filters: Filters;
    companies: { code: string; name: string }[];
    as_of: string;
    tables?: ReportTable[];
    can: { export: boolean; consolidated: boolean };
};

const PRESETS = ['bulan-ini', 'bulan-lalu', 'kuartal-ini', 'tahun-ini', 'rentang'] as const;
/** The reports read as a position today, which the period does not bound. */
const POSITIONS = new Set(['stok', 'piutang']);
const AGE_COLORS = ['var(--color-chart-track)', 'var(--color-chart-age-1)', 'var(--color-chart-age-2)', 'var(--color-chart-age-3)', 'var(--color-chart-age-4)', 'var(--color-chart-age-5)'];

const exact = (value: string): string => {
    const [whole = '0', fraction = ''] = value.replace('-', '').split('.');
    const trimmed = fraction.replace(/0+$/, '');

    return (value.startsWith('-') ? '−' : '') + whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (trimmed === '' ? '' : `,${trimmed}`);
};

export function useLabel() {
    const { t, has } = useTranslation();

    return (key: string): string => (has(key) ? t(key) : key);
}

function CellValue({ column, value }: { column: Column; value: string | null }) {
    const { t } = useTranslation();
    const label = useLabel();

    if (column.kind === 'company') {
        return value === null ? <span className="font-medium">{t('pages.pg_006.view.gabungan')}</span> : <CompanyTag code={value} />;
    }

    if (value === null || value === '') {
        return <span className="text-text-tertiary">—</span>;
    }

    switch (column.kind) {
        case 'money':
            return <data value={value}>{money(value)}</data>;
        case 'qty':
            return <data value={value}>{exact(value)}</data>;
        case 'percent':
            return <data value={value}>{percent(value)}</data>;
        case 'date':
            return <time dateTime={value}>{date(value)}</time>;
        case 'label':
            return <>{label(value)}</>;
        default:
            return <>{value}</>;
    }
}

const numeric = (column: Column): boolean => column.kind === 'money' || column.kind === 'qty' || column.kind === 'percent';

function Ageing({ section }: { section: Section }) {
    const label = useLabel();
    const data = section.rows.map((row, index) => ({ label: label(row[0] ?? ''), amount: Number(row[1] ?? '0'), text: money(row[1] ?? '0'), fill: AGE_COLORS[index] ?? 'var(--color-chart-track)' }));

    return (
        <ChartContainer config={{}} className="aspect-auto h-56 w-full print:hidden" aria-hidden="true" data-slot="ageing-chart">
            <BarChart data={data} layout="vertical" margin={{ left: 8, right: 112 }}>
                <XAxis type="number" hide />
                <YAxis type="category" dataKey="label" width={150} tickLine={false} axisLine={false} />
                <Bar dataKey="amount" isAnimationActive={false} radius={3}>
                    <LabelList dataKey="text" position="right" className="fill-text-secondary" />
                </Bar>
            </BarChart>
        </ChartContainer>
    );
}

function TableSection({ table, section }: { table: ReportTable; section: Section }) {
    const { t } = useTranslation();
    const label = useLabel();
    const caption = `${label(`pages.pg_051.table.${table.key}`)}${section.company === null ? '' : ` · ${section.company}`}`;

    return (
        <section data-slot="report-section" data-company={section.company ?? ''} className="flex min-w-0 flex-col gap-3 break-inside-avoid">
            {section.company !== null && (
                <h3 className="flex min-w-0 flex-wrap items-center gap-2 text-body font-semibold text-text-primary">
                    <CompanyTag code={section.company} />
                    <span className="min-w-0">{section.name}</span>
                </h3>
            )}
            {table.key === 'umur' && section.rows.some((row) => row[1] !== '0.00') && <Ageing section={section} />}
            {section.rows.length === 0 ? (
                <RegionEmpty text={t('pages.pg_051.empty')} />
            ) : (
                <div className="min-w-0">
                    <Table aria-label={caption} scrollLabel={caption} className="text-label">
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                {table.columns.map((column) => (
                                    <TableHead key={column.key} numeric={numeric(column)} scope="col">
                                        {label(`pages.pg_051.col.${column.key}`)}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {section.rows.map((row, index) => (
                                // A report row has no identity beyond its place in the snapshot.
                                <TableRow key={index}>
                                    {table.columns.map((column, at) => (
                                        <TableCell key={column.key} numeric={numeric(column)}>
                                            <CellValue column={column} value={row[at] ?? null} />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                            {section.total !== null && (
                                <TableRow data-slot="section-total" className="font-semibold hover:bg-transparent">
                                    {table.columns.map((column, at) => (
                                        <TableCell key={column.key} numeric={numeric(column)}>
                                            {at === 0 && section.total?.[0] === null ? t('pages.pg_051.export.total') : section.total?.[at] === null ? null : <CellValue column={column} value={section.total?.[at] ?? null} />}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            )}
        </section>
    );
}

function ReportTables({ tables }: { tables: ReportTable[] }) {
    const { t, has } = useTranslation();
    const label = useLabel();

    return (
        <div className="flex min-w-0 flex-col gap-8">
            {tables.map((table) => {
                const note = `pages.pg_051.note.${table.key}`;

                return (
                    <section key={table.key} data-slot="report-table" data-table={table.key} aria-labelledby={`tabel-${table.key}`} className="flex min-w-0 flex-col gap-4">
                        <h2 id={`tabel-${table.key}`} className="text-heading font-semibold text-text-primary">
                            {label(`pages.pg_051.table.${table.key}`)}
                        </h2>
                        {has(note) && <p className="text-label text-text-secondary">{t(note)}</p>}
                        {table.sections.map((section) => (
                            <TableSection key={section.company ?? 'pool'} table={table} section={section} />
                        ))}
                    </section>
                );
            })}
        </div>
    );
}

/** The filters as one line of text, for the print view (DESIGN_SYSTEM §17). */
function filterLine(t: (key: TranslationKey, values?: Record<string, string>) => string, filters: Filters, report: string, companies: ReportProps['companies']): string {
    const scope = filters.perusahaan ?? companies.map((company) => company.code).join(', ');
    const period = POSITIONS.has(report) ? t('pages.pg_051.position') : `${date(filters.dari)} – ${date(filters.sampai)}`;

    return t('pages.pg_051.print_line', { periode: period, perusahaan: scope === '' ? '—' : scope });
}

export function ReportPage({ props, path, title, back, links }: { props: ReportProps; path: string; title: string; back?: { label: string; href: string }; links: MenuAction[] }) {
    const { t } = useTranslation();
    const label = useLabel();
    const { reports, report, filters, companies, as_of: asOf, tables, can } = props;
    const [answer, setAnswer] = useState<Answer | null>(null);
    const [sending, setSending] = useState(false);
    const user = (usePage().props as { auth?: { name?: string | null } }).auth?.name ?? '';

    const visit = (patch: Partial<Filters> & { laporan?: string }) => {
        setAnswer(null);
        const query: Record<string, string> = { laporan: report, periode: filters.periode, ...(filters.perusahaan === null ? {} : { perusahaan: filters.perusahaan }) };

        if ((patch.periode ?? filters.periode) === 'rentang') {
            query.dari = filters.dari;
            query.sampai = filters.sampai;
        }

        for (const [key, value] of Object.entries(patch)) {
            if (value === null || value === '') {
                Reflect.deleteProperty(query, key);
            } else {
                query[key] = value;
            }
        }

        router.get(`${path}?${new URLSearchParams(query).toString()}`, {}, { preserveScroll: true, preserveState: true });
    };

    const exportAs = (format: 'xlsx' | 'pdf') => {
        setSending(true);
        setAnswer(null);
        router.post('/laporan/ekspor', { laporan: report, bentuk: format, periode: filters.periode, dari: filters.dari, sampai: filters.sampai, ...(filters.perusahaan === null ? {} : { perusahaan: filters.perusahaan }) }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const given = (page.flash as { answer?: { text?: unknown } }).answer;

                if (given !== undefined && typeof given.text === 'string') {
                    setAnswer({ kind: 'REJECTED', text: given.text });
                }
            },
            onFinish: () => {
                setSending(false);
            },
        });
    };

    const primary = can.export ? (
        <DropdownMenu>
            <DropdownMenuTrigger render={<Button disabled={sending} />}>
                {t('pages.pg_051.export')}
                <Icon icon={ChevronDown} size="sm" />
            </DropdownMenuTrigger>
            <DropdownMenuContent>
                <DropdownMenuItem onClick={() => { exportAs('xlsx'); }}>{t('pages.pg_051.export_xlsx')}</DropdownMenuItem>
                <DropdownMenuItem onClick={() => { exportAs('pdf'); }}>{t('pages.pg_051.export_pdf')}</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    ) : undefined;

    return (
        <div className="flex min-w-0 flex-col gap-6" data-report={report}>
            <div className="hidden print:block" data-slot="print-header">
                <p className="text-body font-semibold">{title} · {label(`pages.pg_051.report.${report}`)}</p>
                <p className="text-label">{filterLine(t, filters, report, companies)}</p>
            </div>
            <div className="print:hidden">
                <PageHeader title={title} back={back} primary={primary} actions={[{ label: t('actions.print'), onSelect: () => { window.print(); } }, ...links]} />
                {/* The header shows its primary action from the tablet up; on the phone "Ekspor" stands under it (PT-03). */}
                {primary !== undefined && <div className="md:hidden">{primary}</div>}
            </div>
            {answer !== null && <OutcomeMessage answer={answer} />}
            {reports.length > 1 && (
                <div className="min-w-0 overflow-x-auto print:hidden">
                    <Tabs value={report} onValueChange={(value) => { visit({ laporan: String(value) }); }}>
                        <TabsList>
                            {reports.map((code) => <TabsTab key={code} value={code}>{label(`pages.pg_051.report.${code}`)}</TabsTab>)}
                        </TabsList>
                    </Tabs>
                </div>
            )}
            <div className="flex min-w-0 flex-col gap-4 md:flex-row md:flex-wrap md:items-end print:hidden">
                {!POSITIONS.has(report) && (
                    <FormField label={t('pages.pg_051.filter.period')} className="min-w-0 md:w-48">
                        {(control) => (
                            <NativeSelect {...control} value={filters.periode} onChange={(event) => { visit({ periode: event.target.value }); }}>
                                {PRESETS.map((preset) => <option key={preset} value={preset}>{label(`pages.pg_051.preset.${preset}`)}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                )}
                {!POSITIONS.has(report) && filters.periode === 'rentang' && (
                    <>
                        <FormField label={t('pages.pg_051.filter.from')} className="min-w-0 md:w-44">
                            {(control) => <DateField {...control} value={filters.dari} onValueChange={(day) => { if (day !== null) visit({ periode: 'rentang', dari: day }); }} />}
                        </FormField>
                        <FormField label={t('pages.pg_051.filter.to')} className="min-w-0 md:w-44">
                            {(control) => <DateField {...control} value={filters.sampai} onValueChange={(day) => { if (day !== null) visit({ periode: 'rentang', sampai: day }); }} />}
                        </FormField>
                    </>
                )}
                {companies.length > 1 && (
                    <FormField label={t('pages.pg_051.filter.company')} className="min-w-0 md:w-64">
                        {(control) => (
                            <NativeSelect {...control} value={filters.perusahaan ?? ''} onChange={(event) => { visit({ perusahaan: event.target.value === '' ? null : event.target.value }); }}>
                                <option value="">{t('pages.pg_051.filter.company_all')}</option>
                                {companies.map((company) => <option key={company.code} value={company.code}>{`${company.code} · ${company.name}`}</option>)}
                            </NativeSelect>
                        )}
                    </FormField>
                )}
                <p className="text-caption text-text-tertiary md:ml-auto">{POSITIONS.has(report) ? t('pages.pg_051.as_of', { waktu: dateTime(asOf) }) : `${date(filters.dari)} – ${date(filters.sampai)}`}</p>
            </div>
            {tables === undefined ? <RegionLoading /> : <ReportTables tables={tables} />}
            <p className="hidden text-caption print:block" data-slot="print-footer">
                {t('pages.pg_051.print_footer', { waktu: dateTime(asOf), nama: user })}
            </p>
        </div>
    );
}
