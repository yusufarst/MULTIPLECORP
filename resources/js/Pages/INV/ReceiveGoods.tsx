// PG-022 *Terima Barang* (ROUTE-022, ROUTE-181, ROUTE-282; NAV-022; SCR-21; J-INV-01; D-UX-11; IP-15; IP-19; UXS-34; UXS-35; TASK-039): on the phone the lines as a checklist with the scan field fixed at the bottom, on the desktop the lines on the left and the current line on the right. Each scan opens its line; each line is recorded by its own command. The receiving projection: never a price, tax, charge or cost (PJ-18). A scan never submits a command.
import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { FactsList } from '@/components/record-blocks';
import { RegionEmpty } from '@/components/region-state';
import { ScanField, ScanNotice, useScanGuard } from '@/components/scan-field';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { Code, DateText } from '@/components/values';
import { LineForm, plain, type Rack, type Receipt, type ReceiveLine, type ReturnCase, type ScannedSerial } from '@/features/inv/receive-line';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';
import { isBackground } from '@/lib/session-core';
import { useCommand } from '@/lib/use-command';
import { cn } from '@/lib/utils';

type Match = { kind: 'product' | 'serial' | 'lot'; product: string; name: string; sku?: string | null; serial?: string; in_stock?: boolean; lot?: string };
type Scan = { kind: 'match' | 'unknown' | 'ambiguous'; code: string; matches: Match[]; register: boolean };
type Props = {
    purchase: { id: string; number: string; state: string; supplier: string; company: string; company_id: string; project: string | null; project_number: string | null };
    lines: ReceiveLine[];
    cases: ReturnCase[];
    receipts: Receipt[];
    locations: Rack[];
    today: string;
    can: { reserve: boolean; note: boolean; evidence: boolean; barcode: boolean };
};

async function lookUp(code: string): Promise<Scan | null> {
    try {
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        const response = await fetch('/gudang/pindai', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(token === undefined ? {} : { 'X-CSRF-TOKEN': token }) },
            body: JSON.stringify({ kode: code }),
        });

        return response.ok ? (await response.json()) as Scan : null;
    } catch {
        return null;
    }
}

export default function ReceiveGoods({ purchase, lines, cases, receipts, locations, today, can }: Props) {
    const { t } = useTranslation();
    const open = lines.filter((line) => Number(line.remaining) > 0 || Number(line.replacement) > 0);
    const [current, setCurrent] = useState(open[0]?.handle ?? lines[0]?.handle ?? '');
    const [code, setCode] = useState('');
    const [result, setResult] = useState<string | null>(null);
    const [scanError, setScanError] = useState<string | null>(null);
    const [candidates, setCandidates] = useState<Match[]>([]);
    const [register, setRegister] = useState<string | null>(null);
    const [filter, setFilter] = useState<string | null>(null);
    const [serials, setSerials] = useState<Record<string, ScannedSerial[]>>({});
    const [unsent, setUnsent] = useState<Record<string, boolean>>({});
    const [leaving, setLeaving] = useState<(() => void) | null>(null);
    const departing = useRef(false);
    const dirty = Object.values(unsent).some(Boolean);
    const scanRef = useRef<HTMLInputElement>(null);
    // Lookups answer out of order: each serial reads and writes the list as it is by then, never as it was when its scan began (BD-18).
    const scannedSerials = useRef<Record<string, ScannedSerial[]>>({});
    const replaceSerials = (handle: string, next: ScannedSerial[]) => {
        scannedSerials.current = { ...scannedSerials.current, [handle]: next };
        setSerials(scannedSerials.current);
    };
    const prepare = useCommand<Record<string, FormDataConvertible>>(() => '/dokumen', 'post');
    const guard = useScanGuard({ hasScanField: true });

    // IP-15: one scan target, focused on entering the step.
    useEffect(() => {
        scanRef.current?.focus();
    }, []);

    // NAV-022; NV-05: leaving with an unsent line asks MSG-35.
    useEffect(() => {
        const off = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (!dirty || departing.current || visit.method !== 'get' || visit.only.length > 0 || isBackground(visit)) {
                return;
            }

            const href = visit.url.href;
            setLeaving(() => () => { router.visit(href); });

            return false;
        });
        const unload = (event: BeforeUnloadEvent) => {
            if (dirty && !departing.current) event.preventDefault();
        };
        window.addEventListener('beforeunload', unload);

        return () => {
            off();
            window.removeEventListener('beforeunload', unload);
        };
    }, [dirty]);

    const recorded = lines.length - open.length;
    const shown = filter === null || filter === '' ? lines : lines.filter((line) => `${line.name} ${line.sku ?? ''} ${line.description}`.toLowerCase().includes(filter.toLowerCase()));
    const currentLine = lines.find((line) => line.handle === current);
    // The same product on several lines opens the first with something left to receive.
    const lineOf = (product: string) => lines.find((line) => line.product === product && open.includes(line)) ?? lines.find((line) => line.product === product);
    const clear = () => { setResult(null); setScanError(null); setCandidates([]); setRegister(null); };
    const open_ = (line: ReceiveLine) => { setCurrent(line.handle); setResult(t('receiving.scan.opened', { barang: line.name })); };
    const addSerial = (line: ReceiveLine, serial: string) => {
        const own = scannedSerials.current[line.handle] ?? [];

        if (own.some((other) => other.serial.toLowerCase() === serial.toLowerCase())) {
            setScanError(t('messages.msg_36', { seri: serial }));

            return;
        }

        replaceSerials(line.handle, [...own, { serial, damaged: false }]);
        setCurrent(line.handle);
        setResult(t('receiving.scan.serial_added', { seri: serial }));
    };
    // The note opens PG-036: a POST the visit guard does not see, so an unsent line asks MSG-35 here (NV-05).
    const issueNote = (movement: string) => {
        const go = () => { prepare.submit({ jenis: 'DOC-12', subjek: { receiving_movement_id: movement } }); };

        if (dirty) {
            setLeaving(() => go);
        } else {
            go();
        }
    };
    const scanned = (typed: string) => {
        clear();
        void lookUp(typed).then((scan) => {
            if (scan === null) {
                setScanError(t('messages.msg_06'));

                return;
            }

            if (scan.kind === 'ambiguous') {
                setScanError(t('messages.msg_37', { kode: scan.code }));
                setCandidates(scan.matches);

                return;
            }

            const match = scan.matches[0];

            if (scan.kind === 'unknown' || match === undefined) {
                // A serialized line's new unit has no record yet: an unknown code is its serial, nothing else is created (FU-04).
                if (currentLine?.serialized === true) {
                    addSerial(currentLine, scan.code);

                    return;
                }

                setScanError(t('messages.msg_25', { kode: scan.code }));
                setRegister(scan.register ? scan.code : null);

                return;
            }

            const line = lineOf(match.product);

            if (line === undefined) {
                // A serial is unique per product: another product's same text is still a new unit of a serialized line in progress.
                if (match.kind === 'serial' && currentLine?.serialized === true) {
                    addSerial(currentLine, match.serial ?? scan.code);

                    return;
                }

                setScanError(t('receiving.scan.not_on_purchase', { barang: match.name }));

                return;
            }

            if (match.kind === 'serial') {
                if (match.in_stock === true) {
                    setScanError(t('receiving.scan.serial_in_stock', { seri: match.serial ?? scan.code }));

                    return;
                }

                addSerial(line, match.serial ?? scan.code);

                return;
            }

            open_(line);
        });
    };

    return (
        <AppShell title={t('pages.pg_022.title')} back={{ href: '/gudang/barang-masuk', label: t('pages.pg_021.title') }}>
            <div className="flex min-w-0 flex-col gap-6 pb-18 md:pb-8">
                <PageHeader title={t('pages.pg_022.title')} crumbs={[{ label: t('pages.pg_021.title'), href: '/gudang/barang-masuk' }]} />
                <ScanNotice notice={guard.notice} />
                <FactsList columns={2} facts={[
                    { label: t('receiving.field.purchase'), value: <Code>{purchase.number}</Code> },
                    { label: t('receiving.field.supplier'), value: purchase.supplier },
                    { label: t('receiving.field.company'), value: purchase.company },
                    { label: t('receiving.field.project'), value: purchase.project_number === null ? '—' : <Code>{purchase.project_number}</Code> },
                ]} />
                <p data-slot="receive-progress" className="text-body text-text-secondary">{t('receiving.progress', { tercatat: String(recorded), tersisa: String(open.length) })}</p>
                {lines.length === 0 ? <RegionEmpty text={t('receiving.list.no_lines')} /> : (
                    <div className="grid min-w-0 gap-6 md:grid-cols-3">
                        <div className="flex min-w-0 flex-col gap-3">
                            {filter !== null && <Input aria-label={t('pt20.search')} value={filter} onChange={(event) => { setFilter(event.target.value); }} />}
                            <ul role="list" data-slot="receive-lines" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                                {shown.map((line) => (
                                    <li key={line.handle}>
                                        <button type="button" aria-current={line.handle === current ? 'true' : undefined} className={cn('flex min-h-floor w-full min-w-0 items-center justify-between gap-3 px-2 py-2 text-left text-body', line.handle === current && 'bg-surface-sunken font-medium')} onClick={() => { setCurrent(line.handle); }}>
                                            <span className="min-w-0">{`${String(line.no)}. ${line.name}`}</span>
                                            <span className="flex shrink-0 items-center gap-2 tabular-nums text-text-secondary">
                                                {Number(line.remaining) === 0 && <Icon icon={Check} size="sm" />}
                                                {plain(line.remaining)}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <div className="min-w-0 md:col-span-2">
                            {lines.map((line) => (
                                <LineForm key={line.handle} line={line} url={`/gudang/barang-masuk/${purchase.id}/terima`} company={purchase.company_id} racks={locations} receipts={receipts} cases={cases} closed={purchase.state !== 'OPEN'} today={today} canEvidence={can.evidence}
                                    serials={serials[line.handle] ?? []} onSerials={(next) => { replaceSerials(line.handle, next); }} hidden={line.handle !== current} onUnsent={(next) => { setUnsent((all) => (all[line.handle] === next ? all : { ...all, [line.handle]: next })); }} />
                            ))}
                        </div>
                    </div>
                )}
                {candidates.length > 0 && (
                    <ul role="list" data-slot="scan-candidates" className="flex min-w-0 flex-col gap-1">
                        {candidates.map((candidate) => (
                            <li key={`${candidate.kind}-${candidate.product}-${candidate.serial ?? ''}`}>
                                <Button variant="outline" size="sm" onClick={() => {
                                    const line = lineOf(candidate.product);
                                    clear();

                                    if (line === undefined) {
                                        setScanError(t('receiving.scan.not_on_purchase', { barang: candidate.name }));
                                    } else if (candidate.kind === 'serial' && candidate.in_stock === true) {
                                        setScanError(t('receiving.scan.serial_in_stock', { seri: candidate.serial ?? '' }));
                                    } else if (candidate.kind === 'serial') {
                                        addSerial(line, candidate.serial ?? '');
                                    } else {
                                        open_(line);
                                    }
                                }}>{`${candidate.name}${candidate.serial === undefined ? '' : ` · ${candidate.serial}`}`}</Button>
                            </li>
                        ))}
                    </ul>
                )}
                {register !== null && can.barcode && <Button variant="outline" className="self-start" render={<a href={`/data-master/produk?barcode=${encodeURIComponent(register)}`} />}>{t('receiving.scan.register')}</Button>}
                {receipts.length > 0 && (
                    <section data-slot="receive-summary" className="flex min-w-0 flex-col gap-3">
                        <h2 className="text-subhead">{t('pages.pg_022.summary')}</h2>
                        {prepare.answer !== null && prepare.answer.kind !== 'COMMITTED' && <OutcomeMessage answer={prepare.answer} />}
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {receipts.map((receipt) => (
                                <li key={receipt.handle} className="flex min-w-0 flex-wrap items-center justify-between gap-3 py-2 text-body">
                                    <span className="flex min-w-0 flex-wrap items-center gap-3">
                                        <span>{t('receiving.summary.line', { baris: String(receipt.line), layak: plain(receipt.usable), rusak: plain(receipt.damaged), tolak: plain(receipt.rejected) })}</span>
                                        <DateText value={receipt.date} />
                                        {receipt.reference !== null && <span className="text-text-secondary">{receipt.reference}</span>}
                                    </span>
                                    {can.note && !receipt.reversed && <Button variant="outline" size="sm" disabled={prepare.pending} onClick={() => { issueNote(receipt.movement); }}>{t('actions.issue_ntb')}</Button>}
                                </li>
                            ))}
                        </ul>
                        {open.length === 0 && <Button className="self-start" render={<a href="/pekerjaan" />}>{t('receiving.summary.done')}</Button>}
                    </section>
                )}
            </div>
            <UnsentInputDialog open={leaving !== null} kind="leave" onStay={() => { setLeaving(null); }}
                onLeave={() => { const go = leaving; departing.current = true; setLeaving(null); go?.(); }} />
            <ScanField dock inputRef={scanRef} value={code} onValueChange={setCode} onLookup={scanned} onSearch={() => { setFilter((value) => (value === null ? '' : null)); }} result={result} error={scanError} />
        </AppShell>
    );
}
