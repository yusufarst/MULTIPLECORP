import { Search, SlidersHorizontal, X } from 'lucide-react';
import { type ReactNode, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { Sheet, SheetContent, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useTranslation } from '@/lib/i18n';
import { SCAN_TARGET } from '@/lib/scanner';

export type AppliedFilter = { id: string; label: string; onRemove: () => void };

// PT-07's filter toolbar (DESIGN_SYSTEM §9.2, §10 "Filter toolbar and company view"; GR-20): the list's search, the
// company view "Tampilkan", one or two promoted filters and the rest behind "Filter (n)", "Atur Ulang", the
// applied filters as removable tags under the bar; on the phone "Filter (n)" opens a full-height sheet with every
// control and "Terapkan", a summary of the applied filters under it. Presentation only: the keys are the screen
// inventory's and their values live in the address (INFORMATION_ARCHITECTURE §11), never in storage.
export function FilterToolbar({
    search,
    company,
    promoted,
    more,
    applied = [],
    onReset,
    onApply,
}: {
    search?: { label: string; value: string; onChange: (value: string) => void };
    company?: ReactNode;
    promoted?: ReactNode;
    more?: ReactNode;
    applied?: AppliedFilter[];
    onReset: () => void;
    /** The phone sheet's "Terapkan": the controls inside it apply together. */
    onApply?: () => void;
}) {
    const { t, tc } = useTranslation();
    const [open, setOpen] = useState(false);
    const count = applied.length;
    const filterLabel = count > 0 ? tc('actions.filter_count', count) : t('actions.filter');

    return (
        <div data-slot="filter-toolbar" className="flex min-w-0 flex-col gap-2">
            <div role="search" className="flex min-w-0 items-center gap-2">
                {search !== undefined && (
                    <InputGroup className="min-w-0 flex-1 md:max-w-search">
                        <InputGroupAddon>
                            <Icon icon={Search} size="sm" />
                        </InputGroupAddon>
                        <InputGroupInput
                            type="search"
                            aria-label={search.label}
                            placeholder={search.label}
                            maxLength={100}
                            {...{ [SCAN_TARGET]: 'search' }}
                            value={search.value}
                            onChange={(event) => {
                                search.onChange(event.target.value);
                            }}
                        />
                    </InputGroup>
                )}
                <div className="flex shrink-0 items-center gap-2 max-md:hidden">
                    {company}
                    {promoted}
                </div>
                {(more !== undefined || promoted !== undefined || company !== undefined) && (
                    <Button
                        variant="outline"
                        size="md"
                        className={more === undefined ? 'md:hidden' : undefined}
                        onClick={() => {
                            setOpen(true);
                        }}
                    >
                        <Icon icon={SlidersHorizontal} size="sm" />
                        {filterLabel}
                    </Button>
                )}
                {count > 0 && (
                    <Button variant="ghost" size="md" className="max-md:hidden" onClick={onReset}>
                        {t('actions.reset')}
                    </Button>
                )}
            </div>
            {count > 0 && (
                <ul role="list" aria-label={filterLabel} className="flex min-w-0 flex-wrap gap-2">
                    {applied.map((filter) => (
                        <li key={filter.id}>
                            <span className="inline-flex h-control-xs items-center gap-1 rounded-full border border-border bg-surface pr-0.5 pl-3 text-label text-text-primary">
                                {filter.label}
                                <button
                                    type="button"
                                    aria-label={t('actions.remove_filter', { nama: filter.label })}
                                    className="inline-flex size-control-xs items-center justify-center rounded-full text-text-tertiary outline-none hover:bg-surface-sunken focus-visible:outline-2 focus-visible:outline-focus"
                                    onClick={filter.onRemove}
                                >
                                    <Icon icon={X} size="xs" />
                                </button>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent closeLabel={t('actions.close')}>
                    <SheetHeader>
                        <SheetTitle>{t('actions.filter')}</SheetTitle>
                    </SheetHeader>
                    <div className="flex flex-col gap-4">
                        <div className="contents md:hidden">
                            {company}
                            {promoted}
                        </div>
                        {more}
                    </div>
                    <SheetFooter>
                        <Button variant="ghost" size="lg" onClick={onReset}>
                            {t('actions.reset')}
                        </Button>
                        <Button
                            variant="primary"
                            size="lg"
                            onClick={() => {
                                onApply?.();
                                setOpen(false);
                            }}
                        >
                            {t('actions.apply')}
                        </Button>
                    </SheetFooter>
                </SheetContent>
            </Sheet>
        </div>
    );
}
