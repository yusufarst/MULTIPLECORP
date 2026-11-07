// PG-009 *Cari* (ROUTE-009; NAV-009; SCR-08; INFORMATION_ARCHITECTURE §8.1, §10; IP-15; IP-17; TASK-059): one field
// "Cari atau pindai kode" for typed and scanned codes alike (D-IA-04), its results by type from ROUTE-281 — an exact
// code match first under "Kode Cocok", then products, clients, suppliers and projects by name, at most one page — and
// only those whose page exists and the account may open (RC-06; PJ-10). Enter only looks up (GAP-015), and decides on
// the search's answer to that very term, waiting for it when it is not in yet: one exact hit opens it, several are
// listed and none is chosen for the user (PF-19); no exact hit goes to ROUTE-282's exact lookup for an account with
// `stock.view` — one match opens its stock PG-019, several are listed for the user's choice (MSG-37), an unknown code
// says so with "Daftarkan Barcode" when the account may register one, nothing created (MSG-25), the scanner limiter's
// refusal MSG-20. The term lives in the field only, never in the address, the title or the history (IA §11; DP-02).
// "Kembali" and Esc return to the page search was opened from (NV-01). Full-screen on the phone (D-IA-04).
import { Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { type KeyboardEvent, useEffect, useRef, useState, useSyncExternalStore } from 'react';

import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { ScanNotice, useScanGuard } from '@/components/scan-field';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { Spinner } from '@/components/ui/spinner';
import { lookUpOutcome, type ScanMatch, type ScanOutcome } from '@/features/inv/scan-client';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { type AccountHints, type SearchResult, targetOf } from '@/lib/navigation';
import { returnTarget } from '@/lib/previous-page';
import { SCAN_TARGET } from '@/lib/scanner';
import { enterDecision, outcomeFor, search, type SearchOutcome, type Settled } from '@/lib/search-client';

type Props = { can: { scan: boolean } };

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

/** The router's page changes, so the return target is read once the previous page is known (NV-01). */
const onNavigate = (changed: () => void) => router.on('navigate', changed);

/** A key that composes text is not yet the term (IME): Safari confirms a composition with an Enter whose keyCode is 229. */
const composing = (event: globalThis.KeyboardEvent) => event.isComposing || event.key === 'Process' || Reflect.get(event, 'keyCode') === 229;

/** What the last exact lookup answered: the candidates to choose from, or a message. */
type Scanned = { code: string; outcome: ScanOutcome };

function ResultRow({ row, href, exact }: { row: SearchResult; href: string; exact: boolean }) {
    const { t } = useTranslation();
    const detail = row.detail ?? (row.company !== undefined && row.code !== null ? row.company : undefined);
    const code = row.code ?? row.company;

    return (
        <li>
            <Link href={href} className="flex min-w-0 flex-col gap-0.5 rounded-md px-3 py-2 outline-none hover:bg-row-hover focus-visible:outline-2 focus-visible:outline-focus">
                <span className="flex min-w-0 flex-wrap items-baseline gap-x-2">
                    <span className="min-w-0 font-medium break-words text-text-primary">{row.label}</span>
                    {code !== undefined && <span className="font-mono text-caption text-text-secondary">{code}</span>}
                    {exact && <span className="text-caption font-medium text-text-secondary">{t('search.code_match')}</span>}
                </span>
                {detail !== undefined && <span className="min-w-0 text-caption break-words text-text-tertiary">{detail}</span>}
            </Link>
        </li>
    );
}

function Candidates({ scanned }: { scanned: Scanned }) {
    const { t } = useTranslation();
    const { outcome, code } = scanned;

    if (outcome.kind === 'limited') {
        return <p role="alert" className="text-body text-text-primary">{outcome.message}</p>;
    }

    if (outcome.kind === 'failed') {
        return <p role="alert" className="text-body text-text-primary">{t('messages.msg_06')}</p>;
    }

    const { scan } = outcome;

    if (scan.kind === 'unknown' || scan.matches.length === 0) {
        return (
            <div data-slot="scan-unknown" className="flex min-w-0 flex-col items-start gap-2">
                <p role="alert" className="text-body text-text-primary">{t('messages.msg_25', { kode: code })}</p>
                {scan.register && (
                    <Button variant="outline" size="sm" render={<Link href="/data-master/produk" />}>{t('actions.register_barcode')}</Button>
                )}
            </div>
        );
    }

    // Several matches are feedback for the user's choice, announced politely (IP-15; TL-12).
    return (
        <section data-slot="scan-candidates" aria-labelledby="scan-candidates" className="flex min-w-0 flex-col gap-2">
            <p id="scan-candidates" role="status" className="text-body text-text-primary">{t('messages.msg_37', { kode: code })}</p>
            <ul className="flex min-w-0 flex-col gap-1">
                {scan.matches.map((match: ScanMatch) => (
                    <li key={`${match.kind}:${match.product}:${match.serial ?? match.lot ?? ''}`}>
                        <Link href={`/gudang/stok/${match.product}`} className="flex min-w-0 flex-col gap-0.5 rounded-md px-3 py-2 outline-none hover:bg-row-hover focus-visible:outline-2 focus-visible:outline-focus">
                            <span className="min-w-0 font-medium break-words text-text-primary">{match.name}</span>
                            <span className="font-mono text-caption text-text-secondary">{match.serial ?? match.lot ?? match.sku ?? ''}</span>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Cari({ can }: Props) {
    const { t } = useTranslation();
    const account = usePage<{ auth: AccountHints }>().props.auth;
    const origin = useSyncExternalStore(onNavigate, () => returnTarget(account));
    const [term, setTerm] = useState('');
    const [settled, setSettled] = useState<Settled | null>(null);
    const [scanned, setScanned] = useState<Scanned | null>(null);
    const latest = useRef(0);
    // The last answer as soon as it arrives, before its render: an Enter between the two decides on it, never waits forever.
    const answered = useRef<Settled | null>(null);
    // The term an Enter waits on while its search answer is not in; cleared by any change to the field.
    const waiting = useRef<string | null>(null);
    // The page's live exact lookup: a later lookup, a changed term or leaving the page withdraws it, so no late answer
    // shows or opens anything (IP-15).
    const page = useRef<{ lookup: AbortController | null }>({ lookup: null });
    const guard = useScanGuard({ hasScanField: true });

    const withdraw = () => {
        page.current.lookup?.abort();
        page.current.lookup = null;
    };

    const scanLookup = (code: string) => {
        withdraw();
        const controller = new AbortController();
        page.current.lookup = controller;

        void lookUpOutcome(code, controller.signal).then((outcome) => {
            if (controller.signal.aborted) {
                return;
            }

            page.current.lookup = null;
            const only = outcome.kind === 'answer' && outcome.scan.kind === 'match' && outcome.scan.matches.length === 1 ? outcome.scan.matches[0] : undefined;

            if (only !== undefined) {
                router.visit(`/gudang/stok/${only.product}`);

                return;
            }

            setScanned({ code, outcome: outcome.kind === 'answer' && outcome.scan.kind === 'match' ? { kind: 'answer', scan: { ...outcome.scan, kind: 'ambiguous' } } : outcome });
        });
    };

    // Enter's decision on the search's answer to the code (PF-19): one exact hit opens it, several are listed and none
    // chosen, none goes to the exact scan lookup.
    const decide = (code: string, outcome: SearchOutcome) => {
        const decision = enterDecision(outcome, can.scan, (row) => targetOf(row, account));

        if (decision.kind === 'open') {
            router.visit(decision.href);
        } else if (decision.kind === 'lookup') {
            scanLookup(code);
        }
    };
    const decision = useRef(decide);

    useEffect(() => {
        decision.current = decide;
    });

    useEffect(() => {
        const text = term.trim();

        if (text === '') {
            return;
        }

        const request = ++latest.current;
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            void search(text, [], controller.signal).then((outcome) => {
                if (request !== latest.current) {
                    return;
                }

                answered.current = { term: text, outcome };
                setSettled({ term: text, outcome });

                if (waiting.current === text) {
                    waiting.current = null;
                    decision.current(text, outcome);
                }
            });
        }, DEBOUNCE_MS);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [term]);

    const back = useRef(origin);

    useEffect(() => {
        back.current = origin;
    });

    useEffect(() => {
        const state = page.current;
        const pending = waiting;
        // Leaving the page — Esc, "Kembali", a result, a link of the shell — withdraws what is still pending, so a late
        // answer never overrides the visit the user chose (IP-15). A reload of this page's own props leaves it alone.
        const leaving = router.on('before', (event) => {
            if (event.detail.visit.url.pathname !== window.location.pathname) {
                state.lookup?.abort();
                state.lookup = null;
                pending.current = null;
            }
        });
        // Esc closes the search without a result, from anywhere on the page but an open dialog (NAV-009; NV-01).
        const escape = (event: globalThis.KeyboardEvent) => {
            if (event.key !== 'Escape' || event.defaultPrevented || composing(event) || document.querySelector('[role="dialog"], [role="alertdialog"]') !== null) {
                return;
            }

            event.preventDefault();
            router.visit(back.current);
        };

        document.addEventListener('keydown', escape);

        return () => {
            leaving();
            document.removeEventListener('keydown', escape);
            state.lookup?.abort();
            state.lookup = null;
        };
    }, []);

    const current = outcomeFor(settled, term);
    const answer = current?.kind === 'answer' ? current.answer : null;
    const open = (row: SearchResult) => targetOf(row, account);
    const exact = (answer?.exact ?? []).filter((row) => open(row) !== null);
    const groups = (answer?.groups ?? []).map((group) => ({ type: group.type, rows: group.rows.filter((row) => open(row) !== null) })).filter((group) => group.rows.length > 0);
    const text = term.trim();

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        // A key that composes text is not yet the term (IME): neither Enter nor Esc acts on it.
        if (composing(event.nativeEvent) || event.key !== 'Enter') {
            return;
        }

        // Enter only looks up: no command is sent from this page (IP-15; GAP-015).
        event.preventDefault();

        if (text === '') {
            return;
        }

        const known = current ?? (answered.current?.term === text ? answered.current.outcome : null);

        if (known === null) {
            waiting.current = text;

            return;
        }

        decide(text, known);
    };

    const notice = current?.kind === 'limited'
        ? current.message
        : current?.kind === 'failed'
            ? t('messages.msg_06')
            : answer?.notice === 'cut'
                ? t('messages.msg_23')
                : answer?.notice === 'short'
                    ? t('messages.msg_24')
                    : null;
    const loading = text !== '' && current === null;
    const nothing = answer !== null && exact.length === 0 && groups.length === 0 && scanned === null;

    return (
        <AppShell title={t('pages.pg_009.title')} back={{ href: origin, label: t('shell.back') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_009.title')} />
                <Button variant="outline" size="sm" className="self-start max-md:hidden" render={<Link href={origin} />}>{t('shell.back')}</Button>
                <ScanNotice notice={guard.notice} />
                <div role="search" aria-label={t('pages.pg_009.title')} className="flex min-w-0 flex-col gap-2">
                    <InputGroup className="max-md:h-floor md:max-w-search">
                        <InputGroupAddon divider>
                            <Icon icon={Search} size="row" />
                        </InputGroupAddon>
                        <InputGroupInput
                            {...{ [SCAN_TARGET]: 'search' }}
                            aria-label={t('shell.search')}
                            placeholder={t('shell.search')}
                            // The page exists to type or scan into this field (NAV-009).
                            autoFocus
                            autoComplete="off"
                            autoCorrect="off"
                            autoCapitalize="off"
                            spellCheck={false}
                            enterKeyHint="search"
                            maxLength={100}
                            value={term}
                            onChange={(event) => {
                                setTerm(event.target.value);
                                setScanned(null);
                                waiting.current = null;
                                withdraw();
                            }}
                            onKeyDown={onKeyDown}
                        />
                    </InputGroup>
                    <p role="status" className="text-caption text-text-secondary empty:hidden">{loading ? null : notice}</p>
                </div>
                {loading && (
                    <div data-region-state="loading" className="flex items-center gap-2 text-caption text-text-tertiary">
                        <Spinner label={t('states.loading')} size="sm" />
                    </div>
                )}
                {scanned !== null && <Candidates scanned={scanned} />}
                {exact.length > 0 && (
                    <section aria-labelledby="hasil-kode" data-slot="search-exact" className="flex min-w-0 flex-col gap-2">
                        <h2 id="hasil-kode" className="text-subhead">{t('search.code_match')}</h2>
                        <ul className="flex min-w-0 flex-col gap-1">
                            {exact.map((row) => <ResultRow key={`${row.type}:${row.id}:${row.code ?? ''}`} row={row} href={open(row) ?? ''} exact />)}
                        </ul>
                    </section>
                )}
                {groups.map((group) => (
                    <section key={group.type} aria-labelledby={`hasil-${group.type}`} data-slot="search-group" className="flex min-w-0 flex-col gap-2">
                        <h2 id={`hasil-${group.type}`} className="text-subhead">{t(TYPE_LABELS[group.type] ?? 'pages.pg_009.title')}</h2>
                        <ul className="flex min-w-0 flex-col gap-1">
                            {group.rows.map((row) => <ResultRow key={`${row.type}:${row.id}:${row.code ?? ''}`} row={row} href={open(row) ?? ''} exact={false} />)}
                        </ul>
                    </section>
                ))}
                {answer !== null && nothing && answer.notice !== 'short' && <RegionEmpty text={answer.notice === 'unknown_code' ? t('messages.msg_25', { kode: text }) : t('search.not_found')} />}
            </div>
        </AppShell>
    );
}
