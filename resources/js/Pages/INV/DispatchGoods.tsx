// PG-024 *Keluarkan Barang* (ROUTE-024, ROUTE-186–ROUTE-189, ROUTE-282; NAV-024; SCR-22; J-INV-03; D-IA-16; IP-15; IP-19; UXS-22; UXS-34; TASK-041): the project's company and its warehouse lines, the lots from the pool with the relation marker for another company's, scan-led — on the phone the lot confirmation as a list with one tap per lot, on the desktop line, lots and serials side by side. Each line is recorded by its own command; dispatch and delivery stay two linked records. Never a cost or HPP (PJ-01). A scan never submits a command.
import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

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
import { type DispatchRecord, ReverseDispatchDialog, SubstituteDialog } from '@/features/inv/dispatch-record';
import { lookUp } from '@/features/inv/scan-client';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';
import { isBackground } from '@/lib/session-core';
import { useCommand } from '@/lib/use-command';
import { cn } from '@/lib/utils';

type Rack = { id: string; code: string; name: string };
type Props = {
    project: { id: string; number: string; title: string; state: string; company: string; company_id: string };
    lines: DispatchLine[];
    dispatches: DispatchRecord[];
    locations: Rack[];
    today: string;
    choices?: Choices | null;
    can: { allocate: boolean; reverse: boolean; reserve: boolean; note: boolean; evidence: boolean };
};
type Correction = { record: DispatchRecord; kind: 'DISPATCH_REVERSAL' | 'UNDELIVERED_RETURN' | 'SUBSTITUTION' };

export default function DispatchGoods({ project, lines, dispatches, locations, today, choices, can }: Props) {
    const { t } = useTranslation();
    const open = lines.filter((line) => Number(line.remaining) > 0);
    const [current, setCurrent] = useState(open[0]?.handle ?? lines[0]?.handle ?? '');
    const [known, setKnown] = useState<Record<string, Choices>>({});
    const [chosen, setChosen] = useState<Record<string, string[]>>({});
    const [code, setCode] = useState('');
    const [result, setResult] = useState<string | null>(null);
    const [scanError, setScanError] = useState<string | null>(null);
    const [unsent, setUnsent] = useState<Record<string, boolean>>({});
    const [leaving, setLeaving] = useState<(() => void) | null>(null);
    const [correction, setCorrection] = useState<Correction | null>(null);
    const departing = useRef(false);
    const busy = useRef<Record<string, boolean>>({});
    const scanRef = useRef<HTMLInputElement>(null);
    const dirty = Object.values(unsent).some(Boolean);
    const prepare = useCommand<Record<string, FormDataConvertible>>(() => '/dokumen', 'post');
    const guard = useScanGuard({ hasScanField: true });
    const closed = project.state !== 'ACTIVE';

    if (choices !== undefined && choices !== null && known[choices.line] !== choices) {
        setKnown({ ...known, [choices.line]: choices });
    }

    const load = useCallback((handle: string) => {
        router.reload({ only: ['choices'], data: { baris: handle }, preserveUrl: true });
    }, []);

    // The chosen line's lots on request; read again after each answer, what the pool holds having changed.
    useEffect(() => {
        if (current !== '') load(current);
    }, [current, dispatches, load]);

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
    const addSerial = (line: DispatchLine, serial: string) => {
        const row = known[line.handle]?.serials.find((one) => one.serial.toLowerCase() === serial.toLowerCase());

        if (busy.current[line.handle] === true) {
            setScanError(t('receiving.scan.line_busy', { seri: serial }));

            return;
        }

        if (row === undefined) {
            setCurrent(line.handle);
            setScanError(t('dispatch.scan.serial_absent', { seri: serial }));

            return;
        }

        const own = chosen[line.handle] ?? [];

        if (own.includes(row.handle)) {
            setScanError(t('messages.msg_36', { seri: serial }));

            return;
        }

        setChosen({ ...chosen, [line.handle]: [...own, row.handle] });
        setCurrent(line.handle);
        setResult(t('receiving.scan.serial_added', { seri: serial }));
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
        <AppShell title={t('pages.pg_024.title')} back={{ href: '/gudang/barang-keluar', label: t('pages.pg_023.title') }}>
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
                                <DispatchLineForm key={line.handle} line={line} url={`/gudang/barang-keluar/${project.id}/keluarkan`} closed={closed} today={today} choices={known[line.handle] ?? null}
                                    chosen={chosen[line.handle] ?? []} onChosen={(next) => { setChosen((all) => ({ ...all, [line.handle]: next })); }} hidden={line.handle !== current}
                                    onUnsent={(next) => { setUnsentOf(line.handle, next); }} onBusy={(next) => { busy.current[line.handle] = next; }} />
                            ))}
                        </div>
                    </div>
                )}
                {dispatches.length > 0 && (
                    <section data-slot="dispatch-records" className="flex min-w-0 flex-col gap-3">
                        <h2 className="text-subhead">{t('dispatch.records.title')}</h2>
                        {prepare.answer !== null && prepare.answer.kind !== 'COMMITTED' && <OutcomeMessage answer={prepare.answer} />}
                        <ul role="list" className="flex min-w-0 flex-col divide-y divide-border-subtle">
                            {dispatches.map((record) => (
                                <li key={record.id} data-slot="dispatch-record" className="flex min-w-0 flex-col gap-2 py-3 text-body">
                                    <span className="flex min-w-0 flex-wrap items-center gap-3">
                                        <DateText value={record.date} />
                                        <span>{`${String(record.line)}. ${record.name} · ${record.qty} ${record.unit ?? ''}`}</span>
                                    </span>
                                    <span className="flex min-w-0 flex-wrap items-center gap-2">
                                        {can.note && record.reversible && <Button variant="outline" size="sm" disabled={prepare.pending} onClick={() => { issueNote(record.id); }}>{t('actions.issue_surat_jalan')}</Button>}
                                        {record.reversible && (
                                            <>
                                                <Button variant="ghost" size="sm" onClick={() => { setCorrection({ record, kind: 'DISPATCH_REVERSAL' }); }}>{t('dispatch.reverse.cancel')}</Button>
                                                <Button variant="ghost" size="sm" onClick={() => { setCorrection({ record, kind: 'UNDELIVERED_RETURN' }); }}>{t('dispatch.reverse.returned')}</Button>
                                                <Button variant="ghost" size="sm" onClick={() => { setCurrent(record.handle); setCorrection({ record, kind: 'SUBSTITUTION' }); }}>{t('dispatch.substitute.action')}</Button>
                                            </>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        {open.length === 0 && <Button className="self-start" render={<a href="/gudang/barang-keluar" />}>{t('dispatch.records.done')}</Button>}
                    </section>
                )}
            </div>
            {correction !== null && correction.kind !== 'SUBSTITUTION' && <ReverseDispatchDialog key={correction.record.id} record={correction.record} kind={correction.kind} racks={locations} today={today} canReserve={can.reserve} onClose={() => { setCorrection(null); }} />}
            {correction !== null && correction.kind === 'SUBSTITUTION' && <SubstituteDialog key={correction.record.id} record={correction.record} choices={known[correction.record.handle] ?? null} company={project.company_id} canEvidence={can.evidence} today={today} onClose={() => { setCorrection(null); }} />}
            <UnsentInputDialog open={leaving !== null} kind="leave" onStay={() => { setLeaving(null); }}
                onLeave={() => { const go = leaving; departing.current = true; setLeaving(null); go?.(); }} />
            <ScanField dock inputRef={scanRef} value={code} onValueChange={setCode} onLookup={scanned} result={result} error={scanError} />
        </AppShell>
    );
}
