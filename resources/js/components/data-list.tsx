import { Link } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Ellipsis, TriangleAlert } from 'lucide-react';
import type { KeyboardEvent, ReactNode } from 'react';

import type { MenuAction } from '@/components/page-header';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Icon } from '@/components/ui/icon';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

// PT-05, PT-06 and PT-08 (DESIGN_SYSTEM §9.2, §10 "Data table and row list"): one data set — a table from 768 px,
// dense rows on the phone —, a position number on every record list continuing across "Muat Lagi" (GR-08); the
// first column the record's number as a link; numbers right-aligned in tabular figures; secondary row actions in
// "⋯"; a row click or Enter opens the record, the arrow keys move between rows (KB-05); a row the viewer may not open —
// another company's record shown by its relation marker (PJ-04) — has no link. No total row, page number,
// "x dari y", page-size choice, selection checkboxes, column resizing or inline editing (PF-15; PF-17).
export const PAGE_SIZE = 25;

/** A first column's second line, absent when the record has nothing to put there (a product without an SKU). */
function Secondary({ content }: { content: ReactNode }) {
    return content === null || content === undefined || content === '' ? null : <span className="mt-1 block text-caption text-text-tertiary">{content}</span>;
}

export type Column<Row> = {
    key: string;
    header: ReactNode;
    cell: (row: Row) => ReactNode;
    numeric?: boolean;
    /** Hidden first when the table tightens at tablet widths (§6). */
    priority?: 'low';
    /** Only on keys the screen inventory registers (PT-05). */
    sort?: { direction: 'ascending' | 'descending' | 'none'; onSort: () => void };
    /** APPR-042's pilot: the first column's second line, outside the row's link — the record's other identifiers, so a
        list keeps them in view without a column each. */
    secondary?: (row: Row) => ReactNode;
    /** APPR-042's pilot: the first column's link is a name that may wrap, not a record number kept on one line. */
    wrap?: boolean;
};

export function DataTable<Row>({
    caption,
    columns,
    rows,
    rowKey,
    rowHref,
    rowActions,
    positionStart = 1,
}: {
    caption: string;
    columns: Column<Row>[];
    rows: Row[];
    rowKey: (row: Row) => string;
    rowHref: (row: Row) => string | null;
    rowActions?: (row: Row) => MenuAction[];
    positionStart?: number;
}) {
    const { t } = useTranslation();

    const move = (event: KeyboardEvent<HTMLTableRowElement>) => {
        const row = event.currentTarget;

        if (event.target !== row) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const next = event.key === 'ArrowDown' ? row.nextElementSibling : row.previousElementSibling;
            (next as HTMLElement | null)?.focus();
        } else if (event.key === 'Enter') {
            row.querySelector<HTMLAnchorElement>('a[data-row-link]')?.click();
        }
    };

    return (
        <Table aria-label={caption}>
            <TableHeader>
                <TableRow className="hover:bg-transparent">
                    <TableHead numeric className="w-control-md">
                        {t('list.position')}
                    </TableHead>
                    {columns.map((column) => (
                        <TableHead
                            key={column.key}
                            numeric={column.numeric}
                            aria-sort={column.sort?.direction}
                            className={cn(column.priority === 'low' && 'max-lg:hidden')}
                        >
                            {column.sort === undefined ? (
                                column.header
                            ) : (
                                <button type="button" onClick={column.sort.onSort} className="inline-flex items-center gap-1 rounded-xs outline-none hover:text-text-primary focus-visible:outline-2 focus-visible:outline-solid focus-visible:outline-offset-2 focus-visible:outline-focus">
                                    {column.header}
                                    {column.sort.direction !== 'none' && <Icon icon={column.sort.direction === 'ascending' ? ArrowUp : ArrowDown} size="xs" />}
                                </button>
                            )}
                        </TableHead>
                    ))}
                    {rowActions !== undefined && (
                        <TableHead className="w-control-md">
                            <span className="sr-only">{t('actions.row_menu')}</span>
                        </TableHead>
                    )}
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row, index) => {
                    const href = rowHref(row);
                    const actions = rowActions?.(row) ?? [];

                    return (
                        <TableRow
                            key={rowKey(row)}
                            tabIndex={index === 0 ? 0 : -1}
                            className={cn(href !== null && 'cursor-pointer')}
                            onKeyDown={move}
                            onClick={(event) => {
                                if ((event.target as HTMLElement).closest('a, button, [role=menu]') === null) {
                                    event.currentTarget.querySelector<HTMLAnchorElement>('a[data-row-link]')?.click();
                                }
                            }}
                        >
                            <TableCell numeric className="text-text-tertiary">
                                {positionStart + index}
                            </TableCell>
                            {columns.map((column, at) => (
                                <TableCell key={column.key} numeric={column.numeric} className={cn(column.priority === 'low' && 'max-lg:hidden')}>
                                    {at === 0 && href !== null ? (
                                        <Link href={href} data-row-link data-code={column.wrap === true ? undefined : true} tabIndex={-1} className={cn('font-medium text-text-primary underline-offset-2 hover:underline', column.wrap === true ? 'break-words' : 'whitespace-nowrap')}>
                                            {column.cell(row)}
                                        </Link>
                                    ) : (
                                        column.cell(row)
                                    )}
                                    {at === 0 && column.secondary !== undefined && <Secondary content={column.secondary(row)} />}
                                </TableCell>
                            ))}
                            {rowActions !== undefined && (
                                <TableCell className="text-right">
                                    {actions.length > 0 && <RowMenu actions={actions} />}
                                </TableCell>
                            )}
                        </TableRow>
                    );
                })}
            </TableBody>
        </Table>
    );
}

export function RowMenu({ actions }: { actions: MenuAction[] }) {
    const { t } = useTranslation();
    const ordered = [...actions.filter((action) => action.tone !== 'danger'), ...actions.filter((action) => action.tone === 'danger')];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" size="icon-sm" aria-label={t('actions.row_menu')} />}>
                <Icon icon={Ellipsis} size="sm" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {ordered.map((action) => (
                    <DropdownMenuItem key={action.label} tone={action.tone} onClick={action.onSelect}>
                        {action.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export type DenseRow = {
    key: string;
    href: string | null;
    title: ReactNode;
    figure?: ReactNode;
    secondary?: ReactNode;
    status?: ReactNode;
    /** APPR-042's pilot: the second line wraps instead of being cut — for rows whose facts are figures and notes that
        must be read whole, never shortened with an ellipsis. */
    wrap?: boolean;
};

/** PT-06: rows of at least 60 px, two lines — the identifier with the key figure, the muted facts with the status. */
export function RowList({ label, rows, positionStart = 1 }: { label: string; rows: DenseRow[]; positionStart?: number }) {
    return (
        <ol aria-label={label} start={positionStart} className="flex min-w-0 flex-col divide-y divide-border-subtle">
            {rows.map((row, index) => (
                <li key={row.key}>
                    <DenseLink href={row.href}>
                        <span aria-hidden className="w-control-xs shrink-0 pt-px text-right text-m-meta text-text-tertiary tabular-nums">
                            {positionStart + index}
                        </span>
                        <span className="flex min-w-0 flex-1 flex-col gap-1">
                            <span className="flex min-w-0 items-baseline justify-between gap-3">
                                <span data-code className="min-w-0 text-m-row font-medium text-text-primary">
                                    {row.title}
                                </span>
                                {row.figure !== undefined && <span className="shrink-0 text-m-row font-medium tabular-nums">{row.figure}</span>}
                            </span>
                            {(row.secondary !== undefined || row.status !== undefined) && (
                                <span className="flex min-w-0 items-center justify-between gap-3">
                                    <span className={cn('min-w-0 text-m-meta text-text-tertiary', row.wrap === true ? 'break-words' : 'truncate')}>{row.secondary}</span>
                                    {row.status !== undefined && <span className="shrink-0">{row.status}</span>}
                                </span>
                            )}
                        </span>
                    </DenseLink>
                </li>
            ))}
        </ol>
    );
}

function DenseLink({ href, children }: { href: string | null; children: ReactNode }) {
    const layout = 'flex min-h-row min-w-0 items-start gap-3 py-3';

    return href === null ? (
        <div className={layout}>{children}</div>
    ) : (
        <Link href={href} className={cn(layout, 'outline-none hover:bg-row-hover focus-visible:outline-2 focus-visible:outline-solid focus-visible:-outline-offset-2 focus-visible:outline-focus')}>
            {children}
        </Link>
    );
}

/** One data set as PT-05's table from 768 px and PT-06's rows below it. APPR-042's pilot: `denseBelow="lg"` keeps the
    labelled rows through the tablet band, for a table whose columns do not fit there (§6: a table that does not fit
    becomes rows) — every figure stays, labelled, none hidden. */
export function DataList<Row>(props: Parameters<typeof DataTable<Row>>[0] & { dense: (row: Row) => Omit<DenseRow, 'key' | 'href'>; denseBelow?: 'md' | 'lg' }) {
    const { dense, denseBelow = 'md', ...table } = props;

    return (
        <>
            <div className={denseBelow === 'lg' ? 'max-lg:hidden' : 'max-md:hidden'}>
                <DataTable {...table} />
            </div>
            <div className={denseBelow === 'lg' ? 'lg:hidden' : 'md:hidden'}>
                <RowList
                    label={table.caption}
                    positionStart={table.positionStart}
                    rows={table.rows.map((row) => ({ key: table.rowKey(row), href: table.rowHref(row), ...dense(row) }))}
                />
            </div>
        </>
    );
}

export type Continuation = 'more' | 'end' | 'partial' | 'cut' | 'cursor_failed';

/** PT-08: "Muat Lagi" below the last row, "Semua sudah ditampilkan.", the cut notice (MSG-23), a bounded filter's
    short page and a failed cursor (MSG-26) — never a total, page number or page-size choice. */
export function ListContinuation({ state, loading = false, onLoadMore, onReload }: { state: Continuation; loading?: boolean; onLoadMore: () => void; onReload: () => void }) {
    const { t } = useTranslation();

    return (
        <div data-slot="list-continuation" className="flex flex-col items-stretch gap-3 pt-3 md:items-center">
            {state === 'partial' && <p className="text-body text-text-secondary md:text-center">{t('states.partial_page')}</p>}
            {state === 'cut' && <p className="text-body text-text-secondary md:text-center">{t('messages.msg_23')}</p>}
            {(state === 'more' || state === 'partial') && (
                <Button variant="outline" size="lg" className="max-md:w-full" pending={loading} pendingLabel={t('states.loading')} onClick={onLoadMore}>
                    {t('actions.load_more')}
                </Button>
            )}
            {state === 'end' && (
                <p role="status" className="text-body text-text-tertiary md:text-center">
                    {t('states.all_shown')}
                </p>
            )}
            {state === 'cursor_failed' && (
                <div role="alert" className="w-full">
                    <Alert
                        tone="warning"
                        icon={TriangleAlert}
                        title={t('messages.msg_26')}
                        action={
                            <Button variant="outline" size="sm" onClick={onReload}>
                                {t('states.refresh')}
                            </Button>
                        }
                    />
                </div>
            )}
        </div>
    );
}
