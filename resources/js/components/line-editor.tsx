import { ChevronRight, Plus, Trash2 } from 'lucide-react';
import { type KeyboardEvent, type ReactNode, useRef, useState } from 'react';

import { Disclosure } from '@/components/record-blocks';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Sheet, SheetContent, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/lib/i18n';
import { useShortcut } from '@/lib/shortcuts';
import { cn } from '@/lib/utils';

export type LineUpdate<L> = (patch: Partial<L>) => void;

export type LineColumn<L> = {
    key: string;
    header: ReactNode;
    numeric?: boolean;
    /** The cell's control or value; `update` patches this line. */
    cell: (line: L, update: LineUpdate<L>, index: number) => ReactNode;
    className?: string;
};

// PT-10's multi-line entry (DESIGN_SYSTEM §9.3, §10 "Multi-line editor"; ADMIN_FLOW IP-13): a form laid out as
// rows — number · Produk/Jasa · Jumlah · Satuan · Harga · Pajak · Subtotal *Perkiraan* · row actions —, one empty
// row always at the end as the one add path; the details that do not fit a row open under it. The keys: Enter
// moves to the line's next field (KB-05), Alt+N adds a line (KB-06), Alt+Delete removes the focused line with
// "Urungkan" offered (KB-07), Alt+↑/↓ on the line's number moves it (KB-08). Where its column is narrower than its
// lines the table scrolls inside its own frame with the number fixed on the left and the actions on the right
// (D-DS-23). On the phone each line is a dense row opening a full-height editing sheet, "Tambah Baris" at the end.
// No free cell grid, formulas or block paste.
export function LineEditor<L extends { key: string }>({
    label,
    lines,
    onChange,
    blank,
    isBlank,
    columns,
    summary,
    details,
}: {
    label: string;
    lines: L[];
    onChange: (lines: L[]) => void;
    /** A new empty line; its `key` is the editor's. */
    blank: (key: string) => L;
    isBlank: (line: L) => boolean;
    columns: LineColumn<L>[];
    /** The phone's dense row for a line. */
    summary: (line: L) => { title: ReactNode; figure?: ReactNode; secondary?: ReactNode };
    details?: (line: L, update: LineUpdate<L>) => ReactNode;
}) {
    const { t } = useTranslation();
    const frame = useRef<HTMLDivElement>(null);
    // KB-06 and KB-07 act anywhere in the editor — its lines and its "Urungkan" status line alike.
    const editor = useRef<HTMLElement>(null);
    const mint = () => `new-${crypto.randomUUID()}`;
    const [trailing, setTrailing] = useState(() => blank(mint()));
    const [removed, setRemoved] = useState<{ line: L; at: number } | null>(null);
    const [editing, setEditing] = useState<string | null>(null);
    const rows = [...lines, trailing];
    const editingIndex = editing === null ? -1 : rows.findIndex((line) => line.key === editing);
    const editingLine = rows[editingIndex];

    const focusLine = (key: string, part: 'first' | 'number' = 'first') => {
        requestAnimationFrame(() => {
            const row = frame.current?.querySelector<HTMLElement>(`[data-line="${CSS.escape(key)}"]`);
            const target = part === 'number' ? row?.querySelector<HTMLElement>('[data-line-number]') : row?.querySelector<HTMLElement>('input, button[role=combobox], select, textarea');
            target?.focus();
        });
    };

    const update = (key: string, patch: Partial<L>) => {
        if (key === trailing.key) {
            const filled = { ...trailing, ...patch };

            if (!isBlank(filled)) {
                onChange([...lines, filled]);
                setTrailing(blank(mint()));
            } else {
                setTrailing(filled);
            }

            return;
        }

        onChange(lines.map((line) => (line.key === key ? { ...line, ...patch } : line)));
    };

    const remove = (key: string) => {
        const at = lines.findIndex((line) => line.key === key);
        const line = lines[at];

        if (line === undefined) {
            return;
        }

        onChange(lines.filter((other) => other.key !== key));
        setRemoved({ line, at });
        focusLine((lines[at + 1] ?? trailing).key);
    };

    const move = (key: string, by: -1 | 1) => {
        const at = lines.findIndex((line) => line.key === key);
        const to = at + by;
        const line = lines[at];

        if (line === undefined || to < 0 || to >= lines.length) {
            return;
        }

        const reordered = lines.filter((other) => other.key !== key);
        reordered.splice(to, 0, line);
        onChange(reordered);
        focusLine(key, 'number');
    };

    const patchEditing = (value: Partial<L>) => {
        if (editingLine !== undefined) {
            update(editingLine.key, value);
        }
    };

    const focused = (): string | null => (document.activeElement?.closest('[data-line]') as HTMLElement | null)?.dataset.line ?? null;

    useShortcut(
        { key: 'n', alt: true },
        () => {
            focusLine(trailing.key);
        },
        editor,
    );
    useShortcut(
        { key: 'Delete', alt: true },
        () => {
            const key = focused();

            if (key !== null && key !== trailing.key) {
                remove(key);
            }
        },
        editor,
    );

    const onRowKey = (event: KeyboardEvent<HTMLTableRowElement>, key: string) => {
        const target = event.target as HTMLElement;

        if (event.altKey && (event.key === 'ArrowUp' || event.key === 'ArrowDown') && target.hasAttribute('data-line-number')) {
            event.preventDefault();
            move(key, event.key === 'ArrowUp' ? -1 : 1);

            return;
        }

        // KB-05: Enter moves on; a picker or select keeps Enter for choosing, and no field ever sends a command.
        if (event.key === 'Enter' && !event.altKey && !event.ctrlKey && target instanceof HTMLInputElement && target.getAttribute('aria-expanded') !== 'true') {
            event.preventDefault();
            const fields = [...(frame.current?.querySelectorAll<HTMLElement>('[data-line] input:not([disabled])') ?? [])];
            fields[fields.indexOf(target) + 1]?.focus();
        }
    };

    return (
        <section ref={editor} aria-label={label} data-slot="line-editor" className="flex min-w-0 flex-col gap-2">
            <div ref={frame} className="max-md:hidden">
                <Table frameClassName="[scroll-padding-inline:var(--control-md)]">
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead numeric className="sticky left-0 z-(--z-raised) w-control-md bg-surface-sunken">
                                {t('list.position')}
                            </TableHead>
                            {columns.map((column) => (
                                <TableHead key={column.key} numeric={column.numeric} className={column.className}>
                                    {column.header}
                                </TableHead>
                            ))}
                            <TableHead className="sticky right-0 z-(--z-raised) w-control-md bg-surface-sunken">
                                <span className="sr-only">{t('actions.row_menu')}</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((line, index) => {
                            const last = line.key === trailing.key;
                            const patch = (value: Partial<L>) => {
                                update(line.key, value);
                            };

                            return [
                                <TableRow
                                    key={line.key}
                                    data-line={line.key}
                                    className="hover:bg-transparent focus-within:bg-row-hover"
                                    onKeyDown={(event) => {
                                        onRowKey(event, line.key);
                                    }}
                                >
                                    <TableCell numeric className="sticky left-0 bg-surface">
                                        <span
                                            data-line-number
                                            tabIndex={last ? -1 : 0}
                                            aria-label={t('forms.line_number', { n: index + 1 })}
                                            className="inline-flex min-w-control-xs justify-end rounded-xs text-text-tertiary outline-none focus-visible:outline-2 focus-visible:outline-focus"
                                        >
                                            {index + 1}
                                        </span>
                                    </TableCell>
                                    {columns.map((column) => (
                                        <TableCell key={column.key} numeric={column.numeric} className={cn('py-1', column.className)}>
                                            {column.cell(line, patch, index)}
                                        </TableCell>
                                    ))}
                                    <TableCell className="sticky right-0 bg-surface text-right">
                                        {!last && (
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label={t('forms.remove_line')}
                                                onClick={() => {
                                                    remove(line.key);
                                                }}
                                            >
                                                <Icon icon={Trash2} size="sm" />
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>,
                                details !== undefined && !last && (
                                    <TableRow key={`${line.key}-details`} className="hover:bg-transparent">
                                        <TableCell />
                                        <TableCell colSpan={columns.length + 1} className="pt-0">
                                            <Disclosure title={t('forms.more_details')}>{details(line, patch)}</Disclosure>
                                        </TableCell>
                                    </TableRow>
                                ),
                            ];
                        })}
                    </TableBody>
                </Table>
            </div>
            <div className="md:hidden">
                <ol className="flex flex-col divide-y divide-border-subtle">
                    {lines.map((line, index) => {
                        const row = summary(line);

                        return (
                            <li key={line.key}>
                                <button
                                    type="button"
                                    className="flex min-h-row w-full min-w-0 items-center gap-3 py-3 text-left outline-none hover:bg-row-hover focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-focus"
                                    onClick={() => {
                                        setEditing(line.key);
                                    }}
                                >
                                    <span className="w-control-xs shrink-0 text-right text-m-meta text-text-tertiary tabular-nums">{index + 1}</span>
                                    <span className="flex min-w-0 flex-1 flex-col gap-1">
                                        <span className="flex min-w-0 items-baseline justify-between gap-3 text-m-row font-medium">
                                            <span className="min-w-0 truncate">{row.title}</span>
                                            {row.figure !== undefined && <span className="shrink-0 tabular-nums">{row.figure}</span>}
                                        </span>
                                        {row.secondary !== undefined && <span className="text-m-meta text-text-tertiary">{row.secondary}</span>}
                                    </span>
                                    <Icon icon={ChevronRight} size="sm" className="text-text-tertiary" />
                                </button>
                            </li>
                        );
                    })}
                </ol>
                <Button
                    variant="outline"
                    size="lg"
                    block
                    className="mt-2"
                    onClick={() => {
                        setEditing(trailing.key);
                    }}
                >
                    <Icon icon={Plus} size="sm" />
                    {t('actions.add_line')}
                </Button>
                <Sheet
                    open={editing !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditing(null);
                        }
                    }}
                >
                    <SheetContent closeLabel={t('actions.close')}>
                        {editingLine !== undefined && (
                            <>
                                <SheetHeader>
                                    <SheetTitle>{t('forms.line_number', { n: editingIndex + 1 })}</SheetTitle>
                                </SheetHeader>
                                <div className="flex flex-col gap-4">
                                    {columns.map((column) => (
                                        <div key={column.key} className="flex flex-col gap-2">
                                            <span className="text-label text-text-secondary">{column.header}</span>
                                            {column.cell(editingLine, patchEditing, editingIndex)}
                                        </div>
                                    ))}
                                    {details?.(editingLine, patchEditing)}
                                </div>
                                <SheetFooter>
                                    {editingLine.key !== trailing.key && (
                                        <Button
                                            variant="danger"
                                            size="lg"
                                            onClick={() => {
                                                remove(editingLine.key);
                                                setEditing(null);
                                            }}
                                        >
                                            {t('forms.remove_line')}
                                        </Button>
                                    )}
                                    <Button
                                        variant="primary"
                                        size="lg"
                                        onClick={() => {
                                            setEditing(null);
                                        }}
                                    >
                                        {t('actions.close')}
                                    </Button>
                                </SheetFooter>
                            </>
                        )}
                    </SheetContent>
                </Sheet>
            </div>
            <div role="status" className="flex min-h-control-sm items-center gap-2 text-body text-text-secondary">
                {removed !== null && (
                    <>
                        {t('forms.line_removed', { n: removed.at + 1 })}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                const restored = [...lines];
                                restored.splice(removed.at, 0, removed.line);
                                onChange(restored);
                                setRemoved(null);
                                focusLine(removed.line.key);
                            }}
                        >
                            {t('actions.undo')}
                        </Button>
                    </>
                )}
            </div>
        </section>
    );
}
