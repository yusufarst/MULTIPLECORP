// PG-024 *Keluarkan Barang* (ROUTE-024, ROUTE-186–ROUTE-189, ROUTE-282; NAV-024; SCR-22; J-INV-03; D-IA-16; IP-15; IP-19; UXS-22; UXS-34; TASK-041): the project's company and its warehouse lines, the lots from the pool with the relation marker for another company's, scan-led — on the phone the lot confirmation as a list with one tap per lot, on the desktop line, lots and serials side by side. Each line is recorded by its own command; dispatch and delivery stay two linked records. Never a cost or HPP (PJ-01). A scan never submits a command.
import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { ListContinuation } from '@/components/data-list';
import { OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { FactsList } from '@/components/record-blocks';
import { RegionEmpty } from '@/components/region-state';
import { ScanField, ScanNotice, useScanGuard } from '@/components/scan-field';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Code, DateText } from '@/components/values';
import { type Choices, type DispatchLine, DispatchLineForm } from '@/features/inv/dispatch-line';
import { type DispatchRecord, type Proofs, ReverseDispatchDialog, SubstituteDialog } from '@/features/inv/dispatch-record';
import { lookUp } from '@/features/inv/scan-client';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';
import { previousPage } from '@/lib/previous-page';
import { isBackground } from '@/lib/session-core';
import { useCommand } from '@/lib/use-command';
import { cn } from '@/lib/utils';

type Rack = { id: string; code: string; name: string };
type Props = {
    project: { id: string; number: string; title: string; state: string; company: string; company_id: string };
    lines: DispatchLine[];
    dispatches: DispatchRecord[];
    older: string | null;
    older_failed: boolean;
    locations: Rack[];
    today: string;
    choices?: Choices | null;
    proofs?: Proofs;
    can: { allocate: boolean; reverse: boolean; reserve: boolean; note: boolean; evidence: boolean; correct_document: boolean };
};
type Correction = { record: DispatchRecord; kind: 'DISPATCH_REVERSAL' | 'UNDELIVERED_RETURN' | 'SUBSTITUTION' };

// What the server answered replaces what the line knew; a narrowed answer adds to it. A row the list no longer reaches stays while it is chosen or the list is cut.
function merged(previous: Choices | undefined, next: Choices, chosen: string[]): Choices {
    if (previous === undefined) return next;

    const lots = next.lots.map((lot) => `${lot.lot}|${lot.location}`);
    const serials = next.serials.map((serial) => serial.handle);
    const narrowed = next.find !== null;

    return {
        ...next,
        lots: [...next.lots, ...previous.lots.filter((lot) => (narrowed || next.more) && !lots.includes(`${lot.lot}|${lot.location}`))],
        serials: [...next.serials, ...previous.serials.filter((serial) => (narrowed || next.more || chosen.includes(serial.handle)) && !serials.includes(serial.handle))],
        more: narrowed ? previous.more : next.more,
    };
}

export default function DispatchGoods({ project, lines, dispatches, older, older_failed: olderFailed, locations, today, choices, proofs, can }: Props) {
    const { t } = useTranslation();
    const open = lines.filter((line) => Number(line.remaining) > 0);
    const [current, setCurrent] = useState(open[0]?.handle ?? lines[0]?.handle ?? '');
    const [known, setKnown] = useState<Record<string, Choices>>({});
    const [absorbed, setAbsorbed] = useState<Choices | null>(null);
    const [unread, setUnread] = useState<Record<string, boolean>>({});
    const [chosen, setChosen] = useState<Record<string, string[]>>({});
    const [earlier, setEarlier] = useState<DispatchRecord[]>([]);
    const [done, setDone] = useState<string | null>(null);
    const [origin] = useState(() => previousPage() ?? '/gudang/barang-keluar');
    const [code, setCode] = useState('');
    const [result, setResult] = useState<string | null>(null);
    const [scanError, setScanError] = useState<string | null>(null);
    const [unsent, setUnsent] = useState<Record<string, boolean>>({});
    const [leaving, setLeaving] = useState<(() => void) | null>(null);
    const [correction, setCorrection] = useState<Correction | null>(null);
    const departing = useRef(false);
    const busy = useRef<Record<string, boolean>>({});
    const chosenNow = useRef<Record<string, string[]>>({});
    const knownNow = useRef<Record<string, Choices>>({});
    const waiting = useRef<{ line: string; serial: string }[]>([]);
    const scanRef = useRef<HTMLInputElement>(null);
    const dirty = Object.values(unsent).some(Boolean);
    const prepare = useCommand<Record<string, FormDataConvertible>>(() => '/dokumen', 'post');
    const guard = useScanGuard({ hasScanField: true });
    const closed = project.state !== 'ACTIVE';

    if (choices !== undefined && choices !== null && absorbed !== choices) {
        setAbsorbed(choices);
        setUnread({ ...unread, [choices.line]: false });
        setKnown({ ...known, [choices.line]: merged(known[choices.line], choices, chosen[choices.line] ?? []) });
    }

    const load = useCallback((handle: string, find?: string) => {
        const failed = () => { setUnread((all) => ({ ...all, [handle]: true })); };

        router.reload({ only: ['choices'], data: { baris: handle, ...(find === undefined ? {} : { cari: find }) }, preserveUrl: true, async: true, onHttpException: () => { failed(); return false; }, onNetworkError: () => { failed(); return false; } });
    }, []);
    const loadProofs = (find?: string) => {
        router.reload({ only: ['proofs'], data: find === undefined ? {} : { cari_bukti: find }, preserveUrl: true, async: true });
    };
    const replaceChosen = useCallback((handle: string, next: string[]) => {
        chosenNow.current = { ...chosenNow.current, [handle]: next };
        setChosen(chosenNow.current);
    }, []);
    const records = [...dispatches, ...earlier.filter((one) => !dispatches.some((record) => record.id === one.id))].sort((a, b) => b.id.localeCompare(a.id));

    // The chosen line's lots on request; read again after each answer, what the pool holds having changed.
    useEffect(() => {
        if (current !== '') load(current);
    }, [current, dispatches, load]);

    // A refused or failed Surat Jalan stays on the page, whose guard stays on.
    useEffect(() => {
        if (prepare.answer !== null) departing.current = false;
    }, [prepare.answer]);

    useEffect(() => {
        knownNow.current = known;
    }, [known]);

    // A scanned unit the line's list did not hold is asked of the server; added here when its answer arrives.
    useEffect(() => {
        if (absorbed === null || absorbed.find === null) return;

        const answered = waiting.current.filter((one) => one.line === absorbed.line && one.serial === absorbed.find);
        waiting.current = waiting.current.filter((one) => !answered.includes(one));
        const line = lines.find((one) => one.handle === absorbed.line);

        if (line !== undefined) {
            for (const one of answered) addSerial(line, one.serial, true);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only a new answer settles a waiting scan
    }, [absorbed]);

    // IP-15: one scan target, focused on entering the step.
    useEffect(() => {
        scanRef.current?.focus();
    }, []);

    // NAV-024; NV-05: leaving with an unsent line asks MSG-35.
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

    const setUnsentOf = useCallback((handle: string, next: boolean) => {
        setUnsent((all) => (all[handle] === next ? all : { ...all, [handle]: next }));
    }, []);
    const lineOf = (product: string) => lines.find((line) => line.product === product && open.includes(line)) ?? lines.find((line) => line.product === product);
    const clear = () => { setResult(null); setScanError(null); };
    function addSerial(line: DispatchLine, serial: string, settled = false) {
        const row = knownNow.current[line.handle]?.serials.find((one) => one.serial.toLowerCase() === serial.toLowerCase());

        if (busy.current[line.handle] === true) {
            setScanError(t('receiving.scan.line_busy', { seri: serial }));

            return;
        }

        if (row === undefined) {
            setCurrent(line.handle);

            if (settled) {
                setScanError(t('dispatch.scan.serial_absent', { seri: serial }));
            } else {
                waiting.current = [...waiting.current, { line: line.handle, serial }];
                load(line.handle, serial);
            }

            return;
        }

        const own = chosenNow.current[line.handle] ?? [];

        if (own.includes(row.handle)) {
            setScanError(t('messages.msg_36', { seri: serial }));

            return;
        }

        replaceChosen(line.handle, [...own, row.handle]);
        setCurrent(line.handle);
        setResult(t('receiving.scan.serial_added', { seri: serial }));
    }
    const scanned = (typed: string) => {
        clear();
        void lookUp(typed).then((scan) => {
            if (scan === null) {
                setScanError(t('messages.msg_06'));

                return;
            }

            if (scan.kind === 'ambiguous') {
                setScanError(t('messages.msg_37', { kode: scan.code }));

                return;
            }

            const match = scan.matches[0];

            if (scan.kind === 'unknown' || match === undefined) {
                setScanError(t('messages.msg_25', { kode: scan.code }));

                return;
            }

            const line = lineOf(match.product);

            if (line === undefined) {
                setScanError(t('dispatch.scan.not_on_project', { barang: match.name }));

                return;
            }

            if (match.kind === 'serial') {
                addSerial(line, match.serial ?? scan.code);

                return;
            }

            setCurrent(line.handle);
            setResult(t('receiving.scan.opened', { barang: line.name }));
        });
    };
    // The Surat Jalan opens PG-036: a POST the visit guard does not see, so an unsent line asks MSG-35 here (NV-05).
    const issueNote = (movement: string) => {
        const go = () => { prepare.submit({ jenis: 'DOC-03', subjek: { dispatch_movement_id: movement } }); };

        if (dirty) {
            setLeaving(() => go);
        } else {
            go();
        }
    };

    return (
        <AppShell title={t('pages.pg_024.title')} back={{ href: origin, label: t('dialogs.back') }}>
            <div className="flex min-w-0 flex-col gap-6 pb-18 md:pb-8">
                <PageHeader title={t('pages.pg_024.title')} crumbs={[{ label: t('pages.pg_023.title'), href: '/gudang/barang-keluar' }]} />
                <ScanNotice notice={guard.notice} />
                <FactsList columns={2} facts={[
                    { label: t('receiving.field.project'), value: <Code>{project.number}</Code> },
                    { label: t('dispatch.field.title'), value: project.title },
                    { label: t('receiving.field.company'), value: project.company },
                ]} />
                {closed && <p role="status" className="text-body text-text-secondary">{t('dispatch.refusal.project_state')}</p>}
                {lines.length === 0 ? <RegionEmpty text={t('dispatch.list.no_lines')} /> : (
                    <div className="grid min-w-0 gap-6 md:grid-cols-3">
                        <ul role="list" data-slot="dispatch-lines" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {lines.map((line) => (
                                <li key={line.handle}>
                                    <button type="button" aria-current={line.handle === current ? 'true' : undefined} className={cn('flex min-h-floor w-full min-w-0 items-center justify-between gap-3 px-2 py-2 text-left text-body', line.handle === current && 'bg-surface-sunken font-medium')} onClick={() => { setCurrent(line.handle); }}>
                                        <span className="min-w-0">{`${String(line.no)}. ${line.name}`}</span>
                                        <span className="flex shrink-0 items-center gap-2 tabular-nums text-text-secondary">
                                            {Number(line.remaining) === 0 && <Icon icon={Check} size="sm" />}
                                            {line.remaining}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                        <div className="min-w-0 md:col-span-2">
                            {lines.map((line) => (
                                <DispatchLineForm key={line.handle} line={line} url={`/gudang/barang-keluar/${project.id}/keluarkan`} closed={closed} today={today} choices={known[line.handle] ?? null} failed={unread[line.handle] === true} onRetry={() => { load(line.handle); }}
                                    chosen={chosen[line.handle] ?? []} onChosen={(next) => { replaceChosen(line.handle, next); }} hidden={line.handle !== current} onFind={(text) => { load(line.handle, text); }}
                                    onUnsent={(next) => { setUnsentOf(line.handle, next); }} onBusy={(next) => { busy.current[line.handle] = next; }} />
                            ))}
                        </div>
                    </div>
                )}
                {(records.length > 0 || olderFailed) && (
                    <section id="barang-keluar-tercatat" data-slot="dispatch-records" className="flex min-w-0 flex-col gap-3">
                        <h2 className="text-subhead">{t('dispatch.records.title')}</h2>
                        {done !== null && <OutcomeMessage answer={{ kind: 'COMMITTED', text: done }} />}
                        {prepare.answer !== null && prepare.answer.kind !== 'COMMITTED' && <OutcomeMessage answer={prepare.answer} />}
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {records.map((record) => (
                                <li key={record.id} data-slot="dispatch-record" className="flex min-w-0 flex-col gap-2 py-3 text-body">
                                    <span className="flex min-w-0 flex-wrap items-center gap-3">
                                        <DateText value={record.date} />
                                        <span>{`${String(record.line)}. ${record.name} · ${record.qty} ${record.unit ?? ''}`}</span>
                                        {record.note !== null && <a href={`/dokumen/${record.note.id}`} className="text-accent-link underline underline-offset-2">{t('dispatch.records.note', { nomor: record.note.number })}</a>}
                                    </span>
                                    <span className="flex min-w-0 flex-wrap items-center gap-2">
                                        {can.note && record.issuable && <Button variant="outline" size="sm" disabled={prepare.pending} onClick={() => { issueNote(record.id); }}>{t('actions.issue_surat_jalan')}</Button>}
                                        {record.reversible && (
                                            <>
                                                <Button variant="ghost" size="sm" onClick={() => { setDone(null); setCorrection({ record, kind: 'DISPATCH_REVERSAL' }); }}>{t('dispatch.reverse.cancel')}</Button>
                                                <Button variant="ghost" size="sm" onClick={() => { setDone(null); setCorrection({ record, kind: 'UNDELIVERED_RETURN' }); }}>{t('dispatch.reverse.returned')}</Button>
                                                <Button variant="ghost" size="sm" onClick={() => { setDone(null); setCurrent(record.handle); loadProofs(); setCorrection({ record, kind: 'SUBSTITUTION' }); }}>{t('dispatch.substitute.action')}</Button>
                                            </>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        {(older !== null || olderFailed) && (
                            <ListContinuation state={olderFailed ? 'cursor_failed' : 'more'} onLoadMore={() => {
                                setEarlier(records);
                                router.reload({ only: ['dispatches', 'older', 'older_failed'], data: { riwayat: older }, preserveUrl: true });
                            }} onReload={() => { router.visit(window.location.pathname); }} />
                        )}
                        {open.length === 0 && <Button className="self-start" render={<a href="/gudang/barang-keluar" />}>{t('dispatch.records.done')}</Button>}
                    </section>
                )}
            </div>
            {correction !== null && correction.kind !== 'SUBSTITUTION' && <ReverseDispatchDialog key={correction.record.id} record={correction.record} kind={correction.kind} racks={locations} today={today} canReserve={can.reserve} canCorrect={can.correct_document} onClose={() => { setCorrection(null); }} onDone={() => { setCorrection(null); setEarlier([]); setDone(t('dispatch.answer.reversed')); }} />}
            {correction !== null && correction.kind === 'SUBSTITUTION' && <SubstituteDialog key={correction.record.id} record={correction.record} choices={known[correction.record.handle] ?? null} proofs={proofs ?? null} company={project.company_id} canEvidence={can.evidence} canCorrect={can.correct_document} today={today} onClose={() => { setCorrection(null); }} onDone={() => { setCorrection(null); setEarlier([]); setDone(t('dispatch.answer.substituted')); }} onFind={(text) => { load(correction.record.handle, text); }} onFindProof={loadProofs} />}
            <UnsentInputDialog open={leaving !== null} kind="leave" onStay={() => { setLeaving(null); }}
                onLeave={() => { const go = leaving; departing.current = true; setLeaving(null); go?.(); }} />
            <ScanField dock inputRef={scanRef} value={code} onValueChange={setCode} onLookup={scanned} result={result} error={scanError} />
        </AppShell>
    );
}
