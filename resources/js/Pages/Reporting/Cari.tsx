// PG-009 *Cari* (ROUTE-009; NAV-009; SCR-08; INFORMATION_ARCHITECTURE §8.1, §10; IP-15; IP-17; TASK-059): one field
// "Cari atau pindai kode" for typed and scanned codes (D-IA-04), its results by type from ROUTE-281 — an exact code
// match first under "Kode Cocok", then products, clients, suppliers and projects by name, at most one page — and only
// those whose page exists and the account may open (RC-06; PJ-10). Enter only looks up (GAP-015): a scanned code —
// or a typed one the search cannot place — goes to ROUTE-282's exact lookup for an account with `stock.view`; one
// match opens its stock PG-019, several are listed for the user's choice and none is chosen for them (MSG-37), an
// unknown code says so with "Daftarkan Barcode" when the account may register one, nothing created (MSG-25). The term
// lives in the field only, never in the address, the title or the history (IA §11; DP-02). "Kembali" and Esc return to
// the page search was opened from (NV-01). Full-screen on the phone (D-IA-04), the field first.
import { Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { type KeyboardEvent, useEffect, useRef, useState } from 'react';

import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { ScanNotice, useScanGuard } from '@/components/scan-field';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { Spinner } from '@/components/ui/spinner';
import { lookUp, type Scan, type ScanMatch } from '@/features/inv/scan-client';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { type AccountHints, type SearchResult, targetOf } from '@/lib/navigation';
import { returnTarget } from '@/lib/previous-page';
import { BurstDetector, SCAN_TARGET } from '@/lib/scanner';
import { outcomeFor, search, type Settled } from '@/lib/search-client';

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

/** What the last Enter's exact lookup answered: the candidates to choose from, or a message. */
type Scanned = { code: string; scan: Scan | null };

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
    const { scan, code } = scanned;

    if (scan === null) {
        return <p role="alert" className="text-body text-text-primary">{t('messages.msg_06')}</p>;
    }

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

    return (
        <section data-slot="scan-candidates" aria-labelledby="scan-candidates" className="flex min-w-0 flex-col gap-2">
            <p id="scan-candidates" role="alert" className="text-body text-text-primary">{t('messages.msg_37', { kode: code })}</p>
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
    const [term, setTerm] = useState('');
    const [settled, setSettled] = useState<Settled | null>(null);
    const [scanned, setScanned] = useState<Scanned | null>(null);
    const latest = useRef(0);
    // The answer of the last exact lookup only: a changed term or a later lookup leaves an earlier answer unshown.
    const lookups = useRef(0);
    const detector = useRef(new BurstDetector());
    const field = useRef<HTMLInputElement>(null);
    const guard = useScanGuard({ hasScanField: true });

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

    const current = outcomeFor(settled, term);
    const answer = current?.kind === 'answer' ? current.answer : null;
    const open = (row: SearchResult) => targetOf(row, account);
    const exact = (answer?.exact ?? []).filter((row) => open(row) !== null);
    const groups = (answer?.groups ?? []).map((group) => ({ type: group.type, rows: group.rows.filter((row) => open(row) !== null) })).filter((group) => group.rows.length > 0);
    const text = term.trim();

    const scanLookup = (code: string) => {
        const asked = ++lookups.current;

        void lookUp(code).then((scan) => {
            if (asked !== lookups.current) {
                return;
            }

            if (scan?.kind === 'match' && scan.matches.length === 1 && scan.matches[0] !== undefined) {
                router.visit(`/gudang/stok/${scan.matches[0].product}`);

                return;
            }

            setScanned({ code, scan: scan?.kind === 'match' ? { ...scan, kind: 'ambiguous' } : scan });
        });
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            router.visit(returnTarget(account));

            return;
        }

        const burst = detector.current.feed(event.key, event.timeStamp);

        if (event.key !== 'Enter') {
            return;
        }

        // Enter only looks up: no command is sent from this page (IP-15; GAP-015).
        event.preventDefault();
        const code = (burst?.code ?? text).trim();

        if (code === '') {
            return;
        }

        if (burst !== null) {
            setTerm(code);

            if (can.scan) {
                scanLookup(code);
            }

            return;
        }

        // A typed code: one exact hit opens it; several are listed for the user, none chosen for them (PF-19).
        const only = exact.length === 1 && exact[0] !== undefined ? open(exact[0]) : null;

        if (only !== null) {
            router.visit(only);
        } else if (can.scan && exact.length === 0 && groups.length === 0) {
            // Nothing the search placed — or its answer not in yet: the exact lookup only, never a guess (IP-15).
            scanLookup(code);
        }
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
        <AppShell title={t('pages.pg_009.title')} back={{ href: returnTarget(account), label: t('shell.back') }}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_009.title')} />
                <Button variant="outline" size="sm" className="self-start max-md:hidden" render={<Link href={returnTarget(account)} />}>{t('shell.back')}</Button>
                <ScanNotice notice={guard.notice} />
                <div role="search" aria-label={t('pages.pg_009.title')} className="flex min-w-0 flex-col gap-2">
                    <InputGroup className="max-md:h-floor md:max-w-search">
                        <InputGroupAddon divider>
                            <Icon icon={Search} size="row" />
                        </InputGroupAddon>
                        <InputGroupInput
                            ref={field}
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
                                lookups.current++;
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
