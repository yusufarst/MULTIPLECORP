// PG-035 *Dokumen* (ROUTE-035; NAV-035; SCR-34; IA §8.4; WF-DOC-01; TASK-029): the generated documents of one company
// at a time — a branch of the account's scope —, each row its type, its number, the state of its current version and
// its date, a pre-payment Kuitansi with its own *Menunggu Pembayaran* (UXS-38); the exact number lookup (PF-19) inside
// the branch, so another company's document is never shown (PJ-23). A row opens PG-036. Metadata only (PJ-15).
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';

import { DataList, ListContinuation } from '@/components/data-list';
import { FormField } from '@/components/form';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty, RegionNoMatch } from '@/components/region-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Code, DateText } from '@/components/values';
import { typeName } from '@/features/doc/labels';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';

type Branch = { id: string; code: string; name: string };
type Row = { id: string; type: string; type_name: string; state: 'DRAFT' | 'ISSUED' | 'SUPERSEDED' | 'VOIDED'; prepayment: 'open_not_linked' | 'linked' | null; number: string | null; business_date: string | null; version_no: number };
type Props = { branches: Branch[]; branch: string | null; number: string | null; rows: Row[]; more: string | null };

function visit(query: Record<string, string | null>) {
    const params = new URLSearchParams(window.location.search);

    for (const [key, value] of Object.entries(query)) {
        if (value === null || value === '') {
            params.delete(key);
        } else {
            params.set(key, value);
        }
    }

    router.get(`/dokumen${params.size > 0 ? `?${params.toString()}` : ''}`, {}, { preserveScroll: true, preserveState: true });
}

function State({ row }: { row: Row }) {
    return row.prepayment === null ? (
        <StatusBadge family="document_version" state={row.state} size="sm" />
    ) : (
        <StatusBadge family="pre_payment_kuitansi" state={row.prepayment === 'linked' ? 'LINKED' : 'OPEN_NOT_LINKED'} size="sm" />
    );
}

export default function Documents({ branches, branch, number, rows, more }: Props) {
    const { t } = useTranslation();
    const [term, setTerm] = useState(number ?? '');
    // PT-08: "Muat Lagi" adds the page after the rows shown on a partial reload; any other visit shows its first page.
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

    return (
        <AppShell title={t('pages.pg_035.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_035.title')} />
                <div className="flex min-w-0 flex-wrap items-end gap-3">
                    {branches.length > 1 && (
                        <FormField label={t('documents.field.branch')} className="min-w-0 sm:w-80">
                            {(control) => (
                                <NativeSelect {...control} value={branch ?? ''} onChange={(event) => { visit({ perusahaan: event.target.value, setelah: null, nomor: null }); }}>
                                    {branches.map((option) => (
                                        <option key={option.id} value={option.id}>
                                            {`${option.code} · ${option.name}`}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}
                    <form
                        role="search"
                        className="flex min-w-0 flex-1 items-end gap-2 sm:max-w-md"
                        onSubmit={(event) => {
                            event.preventDefault();
                            visit({ nomor: term.trim(), setelah: null });
                        }}
                    >
                        <FormField label={t('documents.field.number_lookup')} help={t('documents.field.number_help')} className="min-w-0 flex-1">
                            {(control) => <Input {...control} value={term} autoComplete="off" spellCheck={false} onChange={(event) => { setTerm(event.target.value); }} />}
                        </FormField>
                        <Button type="submit" variant="outline">
                            <Icon icon={Search} size="sm" />
                            {t('documents.action.find')}
                        </Button>
                    </form>
                </div>
                {shown.length === 0 ? (
                    number !== null ? (
                        <RegionNoMatch term={number} clearLabel={t('documents.action.clear_lookup')} onClear={() => { setTerm(''); visit({ nomor: null, setelah: null }); }} />
                    ) : (
                        <RegionEmpty text={branches.length === 0 ? t('documents.empty_scope') : t('documents.empty')} />
                    )
                ) : (
                    <>
                        <DataList<Row>
                            caption={t('pages.pg_035.title')}
                            rows={shown}
                            rowKey={(row) => row.id}
                            rowHref={(row) => `/dokumen/${row.id}`}
                            columns={[
                                { key: 'type', header: t('documents.field.type'), cell: (row) => t(typeName(row.type)) },
                                { key: 'number', header: t('documents.field.number'), cell: (row) => (row.number === null ? t('documents.number_pending') : <Code>{row.number}</Code>) },
                                { key: 'state', header: t('documents.field.state'), cell: (row) => <State row={row} /> },
                                { key: 'date', header: t('documents.field.date'), cell: (row) => (row.business_date === null ? '—' : <DateText value={row.business_date} />) },
                                { key: 'version', header: t('documents.field.version'), numeric: true, priority: 'low', cell: (row) => row.version_no },
                            ]}
                            dense={(row) => ({
                                title: t(typeName(row.type)),
                                secondary: row.number ?? t('documents.number_pending'),
                                status: <State row={row} />,
                            })}
                        />
                        <ListContinuation state={next === null ? 'end' : 'more'} loading={appending} onLoadMore={loadMore} onReload={loadMore} />
                    </>
                )}
            </div>
        </AppShell>
    );
}
