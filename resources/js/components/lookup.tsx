import { Plus, Search } from 'lucide-react';
import { type ReactNode, type RefObject, useMemo } from 'react';

import { Mark } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxCollection,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxGroupLabel,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import { Icon } from '@/components/ui/icon';
import { InputGroupAddon } from '@/components/ui/input-group';
import { useTranslation } from '@/lib/i18n';
import { SCAN_TARGET } from '@/lib/scanner';

// PT-27's search and type-ahead (DESIGN_SYSTEM §9.2; ADMIN_FLOW IP-17): the server's results grouped by type, an
// exact code match first with the mark "Kode Cocok", at most 20 rows, MSG-23's cut notice; a name search waits
// for three letters or digits (MSG-24) while a code is looked up exactly; the term at most 100 characters. In a
// form picker an empty answer reads "Tidak ditemukan." with "Buat {Objek}" where the account may create. The
// field accepts scans as an exact lookup (§13.2); no recent searches are kept in the browser.
export const LOOKUP_ROWS = 20;
export const LOOKUP_MIN_NAME = 3;
export const LOOKUP_MAX_TERM = 100;

export type LookupResult = { id: string; code: string; label: string; detail?: ReactNode; exact?: boolean };
export type LookupGroup = { value: string; label: string; items: LookupResult[] };

const NO_GROUPS: LookupGroup[] = [];

export function Lookup({
    label,
    term,
    onTermChange,
    groups,
    cut = false,
    loading = false,
    value,
    onChoose,
    create,
    placeholder,
    notice,
    short: answered,
    inputRef,
}: {
    label: string;
    term: string;
    onTermChange: (term: string) => void;
    /** The server's answer for `term`, already ordered — exact code matches first. */
    groups: LookupGroup[];
    /** The server cut the list (more than 200 matches; MSG-23). */
    cut?: boolean;
    loading?: boolean;
    value: LookupResult | null;
    onChoose: (result: LookupResult | null) => void;
    /** "Buat {Objek}", offered only where the account may create (AZ-01 as a hint). */
    create?: { object: string; onCreate: () => void };
    placeholder?: string;
    /** A notice the server's answer carries beside the rows — MSG-25's unknown code, MSG-20's limit. */
    notice?: string | null;
    /** The server's word that the term has no trigram (MSG-24; PF-23); the field's own rule until it answers. */
    short?: boolean;
    inputRef?: RefObject<HTMLInputElement | null>;
}) {
    const { t } = useTranslation();
    // A stable list while the server's answer is unchanged: the primitive resynchronizes on a new `items`.
    const shown = useMemo(() => (groups.length === 0 ? NO_GROUPS : limit(groups)), [groups]);
    const short = answered ?? (term.trim() !== '' && !/\d/.test(term) && term.trim().replace(/[^\p{L}\p{N}]/gu, '').length < LOOKUP_MIN_NAME);

    return (
        <Combobox<LookupResult>
            items={shown}
            filter={null}
            value={value}
            onValueChange={(next) => {
                onChoose(next);
            }}
            inputValue={term}
            onInputValueChange={(next) => {
                onTermChange(next.slice(0, LOOKUP_MAX_TERM));
            }}
            itemToStringLabel={(item) => `${item.code} ${item.label}`}
            isItemEqualToValue={(item, other) => item.id === other.id}
            autoHighlight
        >
            <ComboboxInput
                ref={inputRef}
                aria-label={label}
                placeholder={placeholder}
                maxLength={LOOKUP_MAX_TERM}
                {...{ [SCAN_TARGET]: 'lookup' }}
                onKeyDown={(event) => {
                    // Enter with no row to choose — a changed term before its answer — chooses and clears nothing:
                    // the field keeps the term, a scanned code's Enter included (IP-15, IP-17).
                    if (event.key === 'Enter' && shown.every((group) => group.items.length === 0)) {
                        event.preventDefault();
                        event.preventBaseUIHandler();
                    }
                }}
            >
                <InputGroupAddon>
                    <Icon icon={Search} size="sm" />
                </InputGroupAddon>
            </ComboboxInput>
            <ComboboxContent aria-busy={loading || undefined}>
                {short && <p className="px-3 pt-3 text-caption text-text-secondary">{t('messages.msg_24')}</p>}
                {notice !== undefined && notice !== null && (
                    <p role="status" className="px-3 pt-3 text-caption text-text-primary">
                        {notice}
                    </p>
                )}
                <ComboboxEmpty>
                    {!short && !loading && term.trim() !== '' && (
                        <div className="flex flex-col items-start gap-2 py-1">
                            <span>{t('search.not_found')}</span>
                            {create !== undefined && (
                                <Button variant="ghost" size="sm" onClick={create.onCreate}>
                                    <Icon icon={Plus} size="sm" />
                                    {t('actions.create_object', { objek: create.object })}
                                </Button>
                            )}
                        </div>
                    )}
                </ComboboxEmpty>
                <ComboboxList>
                    {(group: LookupGroup) => (
                        <ComboboxGroup key={group.value} items={group.items}>
                            <ComboboxGroupLabel>{group.label}</ComboboxGroupLabel>
                            <ComboboxCollection>
                                {(item: LookupResult) => (
                                    <ComboboxItem key={item.id} value={item}>
                                        <span className="flex min-w-0 flex-1 flex-col">
                                            <span className="flex min-w-0 items-center gap-2">
                                                <span data-code className="font-medium whitespace-nowrap">
                                                    {item.code}
                                                </span>
                                                <span className="min-w-0 truncate">{item.label}</span>
                                                {item.exact === true && <Mark kind="code_match" />}
                                            </span>
                                            {item.detail !== undefined && <span className="text-caption text-text-secondary">{item.detail}</span>}
                                        </span>
                                    </ComboboxItem>
                                )}
                            </ComboboxCollection>
                        </ComboboxGroup>
                    )}
                </ComboboxList>
                {(cut || total(groups) > LOOKUP_ROWS) && <p className="border-t border-border-subtle px-3 py-2 text-caption text-text-secondary">{t('messages.msg_23')}</p>}
                {/* Search first, then create: with the matches shown, "Buat {Objek}" stays offered below them (J-MD-02). */}
                {create !== undefined && !short && !loading && term.trim() !== '' && total(shown) > 0 && (
                    <div className="border-t border-border-subtle px-1 py-1">
                        <Button variant="ghost" size="sm" onClick={create.onCreate}>
                            <Icon icon={Plus} size="sm" />
                            {t('actions.create_object', { objek: create.object })}
                        </Button>
                    </div>
                )}
            </ComboboxContent>
        </Combobox>
    );
}

function total(groups: LookupGroup[]): number {
    return groups.reduce((sum, group) => sum + group.items.length, 0);
}

/** At most twenty rows across the groups, in the server's order (IP-17). */
export function limit(groups: LookupGroup[], rows = LOOKUP_ROWS): LookupGroup[] {
    let left = rows;

    return groups
        .map((group) => {
            const items = group.items.slice(0, Math.max(left, 0));
            left -= items.length;

            return { ...group, items };
        })
        .filter((group) => group.items.length > 0);
}
