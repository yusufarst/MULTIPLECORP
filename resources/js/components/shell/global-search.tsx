import { router } from '@inertiajs/react';
import { type RefObject, useEffect, useRef, useState } from 'react';

import { Lookup, type LookupGroup, type LookupResult } from '@/components/lookup';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { type AccountHints, BUILT, type SearchResult, targetOf } from '@/lib/navigation';
import { outcomeFor, search, type Settled } from '@/lib/search-client';

// The shell's *Cari* (INFORMATION_ARCHITECTURE §10; PT-27; IP-17; INT-005): the field "Cari atau pindai kode" in
// the top bar, Ctrl K (KB-01) and the phone's full-screen search; a scanner's code typed into it is an exact
// lookup (IP-15). An exact code match comes first with "Kode Cocok"; one hit opens its record; several sharing
// the key are listed for the user. Only results whose page exists are offered (RC-06), and only the answer to the
// term in the field: a changed term shows nothing to choose until its own answer arrives. The term travels in the
// request body and never enters the address or history (DP-02).

const TYPE_LABELS: Record<string, TranslationKey> = {
    product: 'glossary.gl_052',
    serial: 'glossary.gl_055',
    project: 'glossary.gl_004',
    document: 'glossary.gl_014',
    invoice: 'pages.pg_041.title',
    client: 'glossary.gl_048',
    unit: 'glossary.gl_049',
    pic: 'glossary.gl_050',
    supplier: 'glossary.gl_051',
};

const DEBOUNCE_MS = 250;

export function GlobalSearch({ account, built = BUILT, inputRef }: { account: AccountHints; built?: ReadonlySet<string>; inputRef?: RefObject<HTMLInputElement | null> }) {
    const { t } = useTranslation();
    const [term, setTerm] = useState('');
    const [settled, setSettled] = useState<Settled | null>(null);
    const latest = useRef(0);

    useEffect(() => {
        const text = term.trim();

        if (text === '') {
            return;
        }

        const request = ++latest.current;
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            void search(text, [], controller.signal).then((outcome) => {
                if (request === latest.current) {
                    setSettled({ term: text, outcome });
                }
            });
        }, DEBOUNCE_MS);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [term]);

    const openable = (row: SearchResult) => targetOf(row, account, built) !== null;
    const toResult = (row: SearchResult, exact: boolean): LookupResult => ({
        id: `${row.type}:${row.id}:${row.code ?? ''}:${row.label}`,
        code: row.code ?? row.company ?? '',
        label: row.label,
        detail: row.detail ?? (row.company !== undefined && row.code !== null ? row.company : undefined),
        exact,
    });

    const current = outcomeFor(settled, term);
    const answer = current?.kind === 'answer' ? current.answer : null;
    const limited = current?.kind === 'limited' ? current.message : null;
    const loading = term.trim() !== '' && current === null;
    const rows = new Map<string, SearchResult>();
    const groups: LookupGroup[] = [];

    if (answer !== null) {
        const exact = answer.exact.filter(openable);

        if (exact.length > 0) {
            groups.push({ value: 'exact', label: t('search.code_match'), items: exact.map((row) => toResult(row, true)) });
            exact.forEach((row) => rows.set(toResult(row, true).id, row));
        }

        for (const group of answer.groups) {
            const items = group.rows.filter(openable);

            if (items.length > 0) {
                groups.push({ value: group.type, label: t(TYPE_LABELS[group.type] ?? 'shell.nav.cari'), items: items.map((row) => toResult(row, false)) });
                items.forEach((row) => rows.set(toResult(row, false).id, row));
            }
        }
    }

    const notice = limited ?? (answer?.notice === 'unknown_code' ? t('messages.msg_25', { kode: term.trim() }) : null);

    return (
        <Lookup
            label={t('shell.search')}
            placeholder={t('shell.search')}
            term={term}
            onTermChange={setTerm}
            groups={groups}
            cut={answer?.notice === 'cut'}
            short={answer === null ? undefined : answer.notice === 'short'}
            loading={loading}
            notice={notice}
            value={null}
            inputRef={inputRef}
            onChoose={(chosen) => {
                const row = chosen === null ? undefined : rows.get(chosen.id);
                const target = row === undefined ? null : targetOf(row, account, built);

                if (target !== null) {
                    setTerm('');
                    setSettled(null);
                    router.visit(target);
                }
            }}
        />
    );
}
