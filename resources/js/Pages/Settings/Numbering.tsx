// PG-061 *Penomoran Dokumen* (ADMIN_FLOW §12; INFORMATION_ARCHITECTURE §8.6; NAV-061; TASK-021): Owner only. Per
// company and numbered type it shows exactly what NM-03 and HO-39 name — the type's schemes with their effective
// dates and granularity, the counters that exist, the seed boundary, go-live and the import batches that cover the
// company (§12.1) — and offers the numbering commands: a scheme (ROUTE-136), the start and the Owner's seed with the
// vouching statements and their one required tick (§12.2; IP-08), and after a restore the Owner's raise, whose
// confirmation lists each counter with its new next number (DIR-083 OE-11). Counters are shown, never edited; their
// date is the day of recording, a fixed fact (IP-07). Readable on the phone, the commands on the desktop (`[desk]`).
import { router, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { createContext, type ReactNode, useContext, useEffect, useRef, useState } from 'react';
import type { FormDataConvertible } from '@inertiajs/core';

import { ConfirmCommand } from '@/components/confirm-dialog';
import { FormField, FormSection } from '@/components/form';
import { type Answer, OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Code, DateText } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { type Outcome } from '@/lib/command-intent';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { isBackground } from '@/lib/session-core';
import { useCommandIntent } from '@/lib/use-command-intent';

type Granularity = 'YEAR' | 'MONTH';
type Scheme = { id: number; granularity: Granularity; template: string; revision_format: 'NEW_NUMBER' | 'BASE_SUFFIX'; effective_from: string; lock_version: number; in_force: boolean; example: string };
type Detail = {
    today: string;
    schemes: Scheme[];
    counters: { period_key: string; next_value: number; seed_value: number | null }[];
    boundary: string | null;
    go_live: string | null;
    start_periods: string[];
    pending_batches: { public_id: string; cutoff_date: string; periods: string[] }[];
    batches: { public_id: string; state: string; cutoff_date: string; holds_seed_rows: boolean }[];
};
type Props = {
    companies: { id: string; code: string; name: string }[];
    company: string | null;
    types: { slug: string; code: string; label: TranslationKey }[];
    type: string;
    detail: Detail | null;
};
type Flash = { answer?: { kind: Outcome | 'FAILED'; cause: string | null; text: string } };
type Errors = Record<string, string>;

const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

/** The template's number for the first number of a period, as the server formats it at the draw (DIR-098 D1). */
function example(template: string, period: string): string {
    const [year, month] = period.split('-');

    return template.replace(/\{([^{}]*)\}/g, (whole, token: string) => {
        if (token === 'nomor') return '1';
        if (token.startsWith('nomor:')) return '1'.padStart(Number(token.slice(6)), '0');
        if (token === 'tahun') return year ?? whole;
        if (token === 'tahun2') return (year ?? '').slice(2);
        if (token === 'bulan' && month !== undefined) return month;
        if (token === 'bulan_romawi' && month !== undefined) return ROMAN[Number(month) - 1] ?? whole;

        return whole;
    });
}

function periodOf(day: string, granularity: Granularity): string {
    return granularity === 'YEAR' ? day.slice(0, 4) : day.slice(0, 7);
}

function lastDay(period: string): string {
    if (period.length === 4) return `${period}-12-31`;
    const [year, month] = period.split('-').map(Number);
    const days = new Date(Date.UTC(year ?? 1970, month ?? 1, 0)).getUTCDate();

    return `${period}-${String(days).padStart(2, '0')}`;
}

function firstDay(period: string): string {
    return period.length === 4 ? `${period}-01-01` : `${period}-01`;
}

/** NM-03: a period is live when it begins after the seed boundary and does not lie wholly before go-live. */
function isLive(period: string, boundary: string | null, goLive: string | null): boolean {
    return boundary !== null && goLive !== null && firstDay(period) > boundary && lastDay(period) >= goLive;
}

/** One command form's sending and answering: one `command_id` per intent (IP-02), the answer in place (IP-01). */
function useNumberingCommand<P extends Record<string, FormDataConvertible>>(url: () => string, method: 'put' | 'post') {
    const { t } = useTranslation();
    const intent = useCommandIntent<P>();
    const [answer, setAnswer] = useState<Answer | null>(null);
    const [errors, setErrors] = useState<Errors>({});
    const finished = useRef<(() => void) | undefined>(undefined);
    const unknown = (): void => {
        intent.failed();
        setAnswer({ kind: 'FAILED', cause: 'unknown', text: t('messages.msg_07') });
    };

    const send = (sent: { commandId: string; payload: P } | null, done?: () => void) => {
        if (sent === null) return;
        setErrors({});
        router[method](url(), { command_id: sent.commandId, ...sent.payload }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const flash = page.flash as Flash;
                const given = flash.answer;

                if (given === undefined) {
                    unknown();

                    return;
                }

                if (given.kind === 'FAILED') {
                    intent.failed();
                    setAnswer({ kind: 'FAILED', cause: 'not_processed', text: given.text });

                    return;
                }

                intent.outcome(given.kind);
                // A COMMITTED answer is the page's own, above the type's facts, which the visit has refreshed.
                setAnswer(
                    given.kind === 'COMMITTED'
                        ? null
                        : given.kind === 'CONFLICT'
                          ? { kind: 'CONFLICT', cause: given.cause === 'stale' ? 'stale' : 'recorded', text: given.text }
                          : given.kind === 'PENDING'
                            ? { kind: 'PENDING', text: given.text }
                            : { kind: given.kind, text: given.text },
                );

                if (given.kind === 'COMMITTED') {
                    done?.();
                }
            },
            onError: (bag) => {
                intent.notAnOutcome('VALIDATION');
                setErrors(bag);
            },
            // The answer lost on the way: the outcome is unknown, and "Coba Lagi" sends the same identifier (IP-04; HO-02).
            onNetworkError: () => {
                unknown();

                return false;
            },
        });
    };

    return {
        intent,
        answer,
        errors,
        pending: intent.state.phase === 'in_flight',
        unknown: intent.state.phase === 'unknown',
        clear: () => {
            setAnswer(null);
        },
        /** Sends the payload as a new intent — or, while an earlier one's outcome is unknown, retries it unchanged (IP-02). */
        submit: (payload: P, done?: () => void) => {
            if (intent.state.phase === 'unknown') {
                send(intent.retry(), finished.current);

                return;
            }

            finished.current = done;
            send(intent.trigger(payload), done);
        },
        retry: () => {
            send(intent.retry(), finished.current);
        },
    };
}

/** A form's answer in place (IP-01), with "Coba Lagi" while an earlier attempt's outcome is unknown (IP-04; IG-02). */
function CommandAnswer({ command, hidden = false }: { command: { answer: Answer | null; unknown: boolean; retry: () => void }; hidden?: boolean }) {
    const { t } = useTranslation();

    if (hidden || command.answer === null) {
        return null;
    }

    return (
        <OutcomeMessage
            answer={command.answer}
            action={
                command.unknown ? (
                    <Button variant="outline" size="md" onClick={command.retry}>
                        {t('actions.try_again')}
                    </Button>
                ) : undefined
            }
        />
    );
}

/** INT-009: the page's forms report their unsent input, and a visit away asks MSG-35 first. */
const Unsent = createContext<{ mark: (form: string, dirty: boolean) => void; leave: (action: () => void) => void }>({
    mark: () => undefined,
    leave: (action) => {
        action();
    },
});

function useUnsent(form: string, dirty: boolean) {
    const unsent = useContext(Unsent);

    useEffect(() => {
        unsent.mark(form, dirty);

        return () => {
            unsent.mark(form, false);
        };
    }, [unsent, form, dirty]);

    return unsent;
}

function Fact({ children }: { children: ReactNode }) {
    return <p className="text-body text-text-primary">{children}</p>;
}

export default function Numbering({ companies, company, types, type, detail }: Props) {
    const { t } = useTranslation();
    const page = usePage();
    const current = types.find((entry) => entry.slug === type) ?? types[0];
    const jenis = current === undefined ? '' : t(current.label);
    const base = `/pengaturan/penomoran/${type}`;
    const visit = (query: Record<string, string>) => {
        router.get('/pengaturan/penomoran', { perusahaan: company ?? '', jenis: type, ...query }, { preserveScroll: true });
    };
    const flashAnswer = (page.flash as Flash).answer;
    const dirty = useRef(new Set<string>());
    const [leaving, setLeaving] = useState<(() => void) | null>(null);
    const [unsent] = useState(() => ({
        mark: (form: string, isDirty: boolean) => {
            if (isDirty) {
                dirty.current.add(form);
            } else {
                dirty.current.delete(form);
            }
        },
        leave: (action: () => void) => {
            if (dirty.current.size > 0) {
                setLeaving(() => action);
            } else {
                action();
            }
        },
    }));

    // Any other visit away — a shell entry, the back action — asks first too, never a background one (RB-05); the
    // browser's own leave for a tab.
    useEffect(() => {
        const off = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (dirty.current.size === 0 || visit.method !== 'get' || isBackground(visit)) {
                return;
            }

            const href = visit.url.href;
            setLeaving(() => () => {
                router.visit(href);
            });

            return false;
        });
        const unload = (event: BeforeUnloadEvent) => {
            if (dirty.current.size > 0) {
                event.preventDefault();
            }
        };
        window.addEventListener('beforeunload', unload);

        return () => {
            off();
            window.removeEventListener('beforeunload', unload);
        };
    }, []);

    return (
        <Unsent.Provider value={unsent}>
        <AppShell title={t('pages.pg_061.title')}>
            <PageHeader title={t('pages.pg_061.title')} />
            <div className="flex min-w-0 flex-col gap-6 md:max-w-page-form">
                <div className="flex flex-col gap-4 md:flex-row">
                    <FormField label={t('pages.pg_061.company')} className="md:flex-1">
                        {(control) => (
                            <NativeSelect
                                {...control}
                                value={company ?? ''}
                                onChange={(event) => {
                                    const chosen = event.target.value;
                                    unsent.leave(() => {
                                        visit({ perusahaan: chosen });
                                    });
                                }}
                            >
                                {companies.map((entry) => (
                                    <option key={entry.id} value={entry.id}>
                                        {entry.code} — {entry.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>
                    <FormField label={t('pages.pg_061.type')} className="md:flex-1">
                        {(control) => (
                            <NativeSelect
                                {...control}
                                value={type}
                                onChange={(event) => {
                                    const chosen = event.target.value;
                                    unsent.leave(() => {
                                        visit({ jenis: chosen });
                                    });
                                }}
                            >
                                {types.map((entry) => (
                                    <option key={entry.slug} value={entry.slug}>
                                        {t(entry.label)}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>
                </div>
                {detail === null || company === null ? (
                    <RegionEmpty text={t('pages.pg_061.no_scheme')} />
                ) : (
                    <TypeArea key={`${company}-${type}`} detail={detail} company={company} jenis={jenis} base={base} flashAnswer={flashAnswer} />
                )}
            </div>
            <UnsentInputDialog
                open={leaving !== null}
                kind="leave"
                onStay={() => {
                    setLeaving(null);
                }}
                onLeave={() => {
                    const action = leaving;
                    dirty.current.clear();
                    setLeaving(null);
                    action?.();
                }}
            />
        </AppShell>
        </Unsent.Provider>
    );
}

function TypeArea({ detail, company, jenis, base, flashAnswer }: { detail: Detail; company: string; jenis: string; base: string; flashAnswer: Flash['answer'] }) {
    const { t } = useTranslation();
    const started = detail.boundary !== null;

    return (
        <div className="flex flex-col gap-6">
            {flashAnswer?.kind === 'COMMITTED' && <OutcomeMessage answer={{ kind: 'COMMITTED', text: flashAnswer.text }} />}
            <FormSection title={t('glossary.gl_179')}>
                <Fact>{started && detail.boundary !== null ? t('pages.pg_061.boundary', { tanggal: detail.boundary }) : t('status.part.not_started')}</Fact>
                <Fact>{detail.go_live !== null ? t('pages.pg_061.go_live', { tanggal: detail.go_live }) : t('pages.pg_061.go_live_missing')}</Fact>
                {detail.batches.length > 0 && (
                    <div className="flex flex-col gap-1">
                        <p className="text-body font-semibold text-text-primary">{t('pages.pg_061.batches')}</p>
                        <ul role="list" className="flex flex-col gap-1">
                            {detail.batches.map((batch) => (
                                <li key={batch.public_id} className="text-body text-text-secondary">
                                    {t('pages.pg_061.batch_line', { batch: batch.public_id.slice(0, 8), status: batch.state, tanggal: batch.cutoff_date })}
                                    {batch.holds_seed_rows && <> — {t('pages.pg_061.holds_seed_rows')}</>}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </FormSection>
            <SchemeArea detail={detail} company={company} jenis={jenis} base={base} />
            <FormSection title={t('pages.pg_061.counters')}>
                {detail.counters.length === 0 ? (
                    <p className="text-body text-text-secondary">{t('pages.pg_061.no_counters')}</p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('pages.pg_061.period')}</TableHead>
                                <TableHead numeric>{t('pages.pg_061.next_number')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {detail.counters.map((counter) => (
                                <TableRow key={counter.period_key}>
                                    <TableCell>{counter.period_key}</TableCell>
                                    <TableCell numeric>{counter.next_value}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </FormSection>
            {detail.go_live === null ? (
                <OutcomeMessage answer={{ kind: 'PENDING', text: t('pages.pg_061.go_live_explained') }} />
            ) : (
                <div className="hidden flex-col gap-6 md:flex">
                    {!started && <StartArea detail={detail} company={company} jenis={jenis} base={base} />}
                    {started && <SeedArea detail={detail} company={company} jenis={jenis} base={base} />}
                </div>
            )}
            <div className="hidden md:flex">
                <RaiseArea detail={detail} company={company} jenis={jenis} base={base} />
            </div>
        </div>
    );
}

function Recording({ today }: { today: string }) {
    const { t } = useTranslation();

    return <p className="text-caption text-text-secondary">{t('pages.pg_061.recording_day', { tanggal: today })}</p>;
}

type SchemePayload = {
    company: string;
    scheme_id: number | null;
    lock_version: number | null;
    period_granularity: Granularity;
    format_template: string;
    revision_format: 'NEW_NUMBER' | 'BASE_SUFFIX';
    effective_from: string;
    first_number: number | null;
    business_date: string;
    business_date_prefilled: boolean;
};

function SchemeArea({ detail, company, jenis, base }: { detail: Detail; company: string; jenis: string; base: string }) {
    const { t } = useTranslation();
    const command = useNumberingCommand<SchemePayload>(() => base, 'put');
    const [editing, setEditing] = useState<Scheme | 'new' | null>(null);
    const [granularity, setGranularity] = useState<Granularity>('YEAR');
    const [template, setTemplate] = useState('');
    const [revision, setRevision] = useState<'NEW_NUMBER' | 'BASE_SUFFIX'>('NEW_NUMBER');
    const [effective, setEffective] = useState(detail.today);
    const [backward, setBackward] = useState(false);
    const [first, setFirst] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [vouched, setVouched] = useState(false);
    const started = detail.boundary !== null;
    const earliestScheme = detail.schemes[0]?.effective_from ?? null;
    const period = periodOf(effective, granularity);
    const hasCounter = detail.counters.some((counter) => counter.period_key === period);
    const needsSeed = started && !backward && !hasCounter && !isLive(period, detail.boundary, detail.go_live);
    const [opened, setOpened] = useState('');
    const values = JSON.stringify([granularity, template, revision, effective, backward, first]);
    const unsent = useUnsent('scheme', editing !== null && values !== opened);

    const open = (scheme: Scheme | 'new') => {
        const start: [Granularity, string, 'NEW_NUMBER' | 'BASE_SUFFIX', string] =
            scheme === 'new' ? ['YEAR', '', 'NEW_NUMBER', detail.today] : [scheme.granularity, scheme.template, scheme.revision_format, scheme.effective_from];
        setOpened(JSON.stringify([...start, false, '']));
        command.clear();
        setEditing(scheme);
        setGranularity(start[0]);
        setTemplate(start[1]);
        setRevision(start[2]);
        setEffective(start[3]);
        setBackward(false);
        setFirst('');
    };
    const payload = (): SchemePayload => ({
        company,
        scheme_id: editing !== null && editing !== 'new' ? editing.id : null,
        lock_version: editing !== null && editing !== 'new' ? editing.lock_version : null,
        period_granularity: granularity,
        format_template: template,
        revision_format: revision,
        effective_from: effective,
        first_number: needsSeed && first !== '' ? Number(first) : null,
        business_date: detail.today,
        business_date_prefilled: true,
    });
    const submit = () => {
        command.submit(payload(), () => {
            setEditing(null);
            setConfirming(false);
        });
    };

    return (
        <FormSection title={t('glossary.gl_176')}>
            {detail.schemes.length === 0 ? (
                <p className="text-body text-text-secondary">{t('pages.pg_061.no_scheme')}</p>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('pages.pg_061.effective_from')}</TableHead>
                            <TableHead>{t('pages.pg_061.granularity')}</TableHead>
                            <TableHead>{t('pages.pg_061.template')}</TableHead>
                            <TableHead className="hidden md:table-cell" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {detail.schemes.map((scheme) => (
                            <TableRow key={scheme.id}>
                                <TableCell>
                                    <DateText value={scheme.effective_from} />
                                </TableCell>
                                <TableCell>{t(scheme.granularity === 'YEAR' ? 'pages.pg_061.yearly' : 'pages.pg_061.monthly')}</TableCell>
                                <TableCell>
                                    <Code>{scheme.template}</Code>
                                    <span className="block text-caption text-text-secondary">{t('pages.pg_061.example', { nomor: scheme.example })}</span>
                                </TableCell>
                                <TableCell className="hidden md:table-cell">
                                    {!scheme.in_force && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                open(scheme);
                                            }}
                                        >
                                            {t('pages.pg_061.change_scheme')}
                                        </Button>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
            {editing === null ? (
                <div className="hidden md:flex">
                    <Button
                        variant="outline"
                        size="lg"
                        onClick={() => {
                            open('new');
                        }}
                    >
                        <Plus aria-hidden />
                        {t('pages.pg_061.add_scheme')}
                    </Button>
                </div>
            ) : (
                <form
                    className="hidden flex-col gap-4 md:flex"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (needsSeed && first !== '') {
                            setVouched(false);
                            setConfirming(true);
                        } else {
                            submit();
                        }
                    }}
                >
                    <FormField label={t('pages.pg_061.granularity')} required error={command.errors.period_granularity}>
                        {(control) => (
                            <NativeSelect
                                {...control}
                                value={granularity}
                                disabled={command.intent.frozen}
                                onChange={(event) => {
                                    setGranularity(event.target.value === 'MONTH' ? 'MONTH' : 'YEAR');
                                }}
                            >
                                <option value="YEAR">{t('pages.pg_061.yearly')}</option>
                                <option value="MONTH">{t('pages.pg_061.monthly')}</option>
                            </NativeSelect>
                        )}
                    </FormField>
                    <FormField label={t('pages.pg_061.template')} required help={t('pages.pg_061.template_hint')} error={command.errors.format_template}>
                        {(control) => (
                            <Input
                                {...control}
                                value={template}
                                maxLength={60}
                                disabled={command.intent.frozen}
                                onChange={(event) => {
                                    setTemplate(event.target.value);
                                }}
                            />
                        )}
                    </FormField>
                    {template !== '' && <p className="text-caption text-text-secondary">{t('pages.pg_061.example', { nomor: example(template, period) })}</p>}
                    <FormField label={t('pages.pg_061.revision')} required error={command.errors.revision_format}>
                        {(control) => (
                            <NativeSelect
                                {...control}
                                value={revision}
                                disabled={command.intent.frozen}
                                onChange={(event) => {
                                    setRevision(event.target.value === 'BASE_SUFFIX' ? 'BASE_SUFFIX' : 'NEW_NUMBER');
                                }}
                            >
                                <option value="NEW_NUMBER">{t('pages.pg_061.revision_new_number')}</option>
                                <option value="BASE_SUFFIX">{t('pages.pg_061.revision_base_suffix')}</option>
                            </NativeSelect>
                        )}
                    </FormField>
                    {started && editing === 'new' && earliestScheme !== null && (
                        <label className="flex items-center gap-3 text-body text-text-primary">
                            <Checkbox
                                checked={backward}
                                onCheckedChange={(checked) => {
                                    setBackward(checked);
                                }}
                            />
                            {t('pages.pg_061.backward')}
                        </label>
                    )}
                    <FormField label={t('pages.pg_061.effective_from')} required error={command.errors.effective_from}>
                        {(control) => (
                            <Input
                                {...control}
                                type="date"
                                value={effective}
                                min={started && !backward ? detail.today : undefined}
                                disabled={command.intent.frozen}
                                onChange={(event) => {
                                    setEffective(event.target.value);
                                }}
                            />
                        )}
                    </FormField>
                    {effective === detail.today && <p className="text-caption text-text-secondary">{t('pages.pg_061.today_notice')}</p>}
                    {needsSeed && (
                        <FormField label={t('pages.pg_061.first_number_of', { periode: period })} required error={command.errors.first_number}>
                            {(control) => (
                                <Input
                                    {...control}
                                    numeric
                                    inputMode="numeric"
                                    value={first}
                                    disabled={command.intent.frozen}
                                    onChange={(event) => {
                                        setFirst(event.target.value.replace(/\D/g, ''));
                                    }}
                                />
                            )}
                        </FormField>
                    )}
                    <Recording today={detail.today} />
                    <CommandAnswer command={command} hidden={confirming} />
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" variant="primary" size="lg" pending={command.pending} pendingLabel={t('pages.pg_061.save_scheme')}>
                            {t('pages.pg_061.save_scheme')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="lg"
                            disabled={command.intent.frozen}
                            onClick={() => {
                                unsent.leave(() => {
                                    setEditing(null);
                                    command.intent.leave();
                                });
                            }}
                        >
                            {t('dialogs.back')}
                        </Button>
                    </div>
                </form>
            )}
            {editing === null && <CommandAnswer command={command} />}
            <ConfirmCommand
                open={confirming}
                onOpenChange={setConfirming}
                title={t('pages.pg_061.seed_title', { jenis, periode: period })}
                consequence={t('pages.pg_061.seed_consequence')}
                items={<p className="text-body text-text-primary">{t('pages.pg_061.first_line', { periode: period, nomor: first })}</p>}
                ticks={[{ id: 'vouch', label: <Statements keys={['pages.pg_061.statement_a']} jenis={jenis} />, checked: vouched }]}
                onTick={(_id, checked) => {
                    setVouched(checked);
                }}
                confirmLabel={t('pages.pg_061.save_scheme')}
                pending={command.pending}
                answer={command.answer}
                onConfirm={submit}
            />
        </FormSection>
    );
}

/** The vouching statements of HO-39 in full, never behind a link, acknowledged by one tick (§12.2). */
function Statements({ keys, jenis }: { keys: TranslationKey[]; jenis: string }) {
    const { t } = useTranslation();

    return (
        <span className="flex flex-col gap-2">
            {keys.map((key) => (
                <span key={key}>{t(key, { jenis })}</span>
            ))}
            <span className="font-semibold">{t('pages.pg_061.acknowledge')}</span>
        </span>
    );
}

type StartPayload = { company: string; mode: 'start'; first_numbers: Record<string, number>; business_date: string; business_date_prefilled: boolean };

function StartArea({ detail, company, jenis, base }: { detail: Detail; company: string; jenis: string; base: string }) {
    const { t } = useTranslation();
    const command = useNumberingCommand<StartPayload>(() => `${base}/mulai`, 'post');
    const [numbers, setNumbers] = useState<Record<string, string>>({});
    const [confirming, setConfirming] = useState(false);
    const [vouched, setVouched] = useState(false);
    useUnsent('start', Object.values(numbers).some((value) => value !== ''));

    if (detail.pending_batches.length > 0) {
        const batch = detail.pending_batches[0];

        return <OutcomeMessage answer={{ kind: 'REJECTED', text: t('numbering.refusal.batch', { batch: batch?.public_id.slice(0, 8) ?? '', jenis }) }} />;
    }

    if (detail.start_periods.length === 0) {
        return <p className="text-body text-text-secondary">{t('numbering.refusal.no_scheme', { jenis })}</p>;
    }

    const filled = detail.start_periods.every((period) => (numbers[period] ?? '') !== '');
    const payload = (): StartPayload => ({
        company,
        mode: 'start',
        first_numbers: Object.fromEntries(detail.start_periods.map((period) => [period, Number(numbers[period] ?? '0')])),
        business_date: detail.today,
        business_date_prefilled: true,
    });

    return (
        <FormSection title={t('glossary.gl_177')}>
            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();

                    if (filled) {
                        command.clear();
                        setVouched(false);
                        setConfirming(true);
                    }
                }}
            >
                {detail.start_periods.map((period) => (
                    <FormField key={period} label={t('pages.pg_061.first_number_of', { periode: period })} required error={command.errors[`first_numbers.${period}`] ?? command.errors.first_numbers}>
                        {(control) => (
                            <Input
                                {...control}
                                numeric
                                inputMode="numeric"
                                value={numbers[period] ?? ''}
                                disabled={command.intent.frozen}
                                onChange={(event) => {
                                    setNumbers({ ...numbers, [period]: event.target.value.replace(/\D/g, '') });
                                }}
                            />
                        )}
                    </FormField>
                ))}
                <Recording today={detail.today} />
                <CommandAnswer command={command} hidden={confirming} />
                <div>
                    <Button type="submit" variant="primary" size="lg" disabled={!filled}>
                        {t('glossary.gl_177')}
                    </Button>
                </div>
            </form>
            <ConfirmCommand
                open={confirming}
                onOpenChange={setConfirming}
                title={t('pages.pg_061.start_title', { jenis })}
                consequence={t('pages.pg_061.start_consequence')}
                items={<SummaryLines detail={detail} lines={detail.start_periods.map((period) => t('pages.pg_061.first_line', { periode: period, nomor: numbers[period] ?? '' }))} />}
                ticks={[
                    {
                        id: 'vouch',
                        label: <Statements keys={['pages.pg_061.statement_a', 'pages.pg_061.statement_b', 'pages.pg_061.statement_c', 'pages.pg_061.statement_d']} jenis={jenis} />,
                        checked: vouched,
                    },
                ]}
                onTick={(_id, checked) => {
                    setVouched(checked);
                }}
                confirmLabel={t('glossary.gl_177')}
                pending={command.pending}
                answer={command.answer}
                onConfirm={() => {
                    command.submit(payload(), () => {
                        setConfirming(false);
                    });
                }}
            />
        </FormSection>
    );
}

/** What 12.1 shows for the type, repeated above the statements of a start or seed confirmation (§12.2; HO-39). */
function SummaryLines({ detail, lines }: { detail: Detail; lines: string[] }) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col gap-2 text-body text-text-primary">
            <ul role="list" className="flex flex-col gap-1">
                {lines.map((line) => (
                    <li key={line} className="font-semibold">
                        {line}
                    </li>
                ))}
            </ul>
            <p className="text-text-secondary">{detail.boundary !== null ? t('pages.pg_061.boundary', { tanggal: detail.boundary }) : t('status.part.not_started')}</p>
            <p className="text-text-secondary">{detail.go_live !== null ? t('pages.pg_061.go_live', { tanggal: detail.go_live }) : t('pages.pg_061.go_live_missing')}</p>
            {detail.counters.map((counter) => (
                <p key={counter.period_key} className="text-text-secondary">
                    {t('pages.pg_061.raise_line', { periode: counter.period_key, nomor: String(counter.next_value) })}
                </p>
            ))}
        </div>
    );
}

type SeedPayload = { company: string; mode: 'seed'; period_key: string; first_number: number; business_date: string; business_date_prefilled: boolean };

function SeedArea({ detail, company, jenis, base }: { detail: Detail; company: string; jenis: string; base: string }) {
    const { t } = useTranslation();
    const command = useNumberingCommand<SeedPayload>(() => `${base}/mulai`, 'post');
    const [period, setPeriod] = useState('');
    const [first, setFirst] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [vouched, setVouched] = useState(false);
    useUnsent('seed', period !== '' || first !== '');

    return (
        <FormSection title={t('actions.set_first_number')}>
            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();

                    if (period !== '' && first !== '') {
                        command.clear();
                        setVouched(false);
                        setConfirming(true);
                    }
                }}
            >
                <FormField label={t('pages.pg_061.seed_period')} required help="YYYY · YYYY-MM" error={command.errors.period_key}>
                    {(control) => (
                        <Input
                            {...control}
                            value={period}
                            placeholder={detail.today.slice(0, 4)}
                            disabled={command.intent.frozen}
                            onChange={(event) => {
                                setPeriod(event.target.value.trim());
                            }}
                        />
                    )}
                </FormField>
                <FormField label={t('glossary.gl_178')} required error={command.errors.first_number}>
                    {(control) => (
                        <Input
                            {...control}
                            numeric
                            inputMode="numeric"
                            value={first}
                            disabled={command.intent.frozen}
                            onChange={(event) => {
                                setFirst(event.target.value.replace(/\D/g, ''));
                            }}
                        />
                    )}
                </FormField>
                <Recording today={detail.today} />
                <CommandAnswer command={command} hidden={confirming} />
                <div>
                    <Button type="submit" variant="primary" size="lg" disabled={period === '' || first === ''}>
                        {t('actions.set_first_number')}
                    </Button>
                </div>
            </form>
            <ConfirmCommand
                open={confirming}
                onOpenChange={setConfirming}
                title={t('pages.pg_061.seed_title', { jenis, periode: period })}
                consequence={t('pages.pg_061.seed_consequence')}
                items={<SummaryLines detail={detail} lines={[t('pages.pg_061.first_line', { periode: period, nomor: first })]} />}
                ticks={[{ id: 'vouch', label: <Statements keys={['pages.pg_061.statement_a']} jenis={jenis} />, checked: vouched }]}
                onTick={(_id, checked) => {
                    setVouched(checked);
                }}
                confirmLabel={t('actions.set_first_number')}
                pending={command.pending}
                answer={command.answer}
                onConfirm={() => {
                    command.submit(
                        { company, mode: 'seed', period_key: period, first_number: Number(first), business_date: detail.today, business_date_prefilled: true },
                        () => {
                            setConfirming(false);
                            setPeriod('');
                            setFirst('');
                        },
                    );
                }}
            />
        </FormSection>
    );
}

type RaisePayload = {
    company: string;
    mode: 'raise';
    restore_reference: string;
    recovery_point: string;
    lines: { period_key: string; highest: number }[];
    business_date: string;
    business_date_prefilled: boolean;
};

function RaiseArea({ detail, company, jenis, base }: { detail: Detail; company: string; jenis: string; base: string }) {
    const { t } = useTranslation();
    const command = useNumberingCommand<RaisePayload>(() => `${base}/mulai`, 'post');
    const [open, setOpen] = useState(false);
    const [reference, setReference] = useState('');
    const [point, setPoint] = useState('');
    const [lines, setLines] = useState<{ period: string; highest: string }[]>([{ period: '', highest: '' }]);
    const [confirming, setConfirming] = useState(false);
    const filled = reference.trim() !== '' && point !== '' && lines.every((line) => line.period !== '' && line.highest !== '');
    useUnsent('raise', open && (reference !== '' || point !== '' || lines.some((line) => line.period !== '' || line.highest !== '')));

    if (!open) {
        return (
            <FormSection title={t('pages.pg_061.raise')}>
                <p className="text-body text-text-secondary">{t('pages.pg_061.raise_intro')}</p>
                <div>
                    <Button
                        variant="outline"
                        size="lg"
                        onClick={() => {
                            setOpen(true);
                        }}
                    >
                        {t('pages.pg_061.raise')}
                    </Button>
                </div>
            </FormSection>
        );
    }

    return (
        <FormSection title={t('pages.pg_061.raise')}>
            <p className="text-body text-text-secondary">{t('pages.pg_061.raise_intro')}</p>
            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();

                    if (filled) {
                        command.clear();
                        setConfirming(true);
                    }
                }}
            >
                <FormField label={t('pages.pg_061.restore_reference')} required error={command.errors.restore_reference}>
                    {(control) => (
                        <Input
                            {...control}
                            value={reference}
                            maxLength={120}
                            disabled={command.intent.frozen}
                            onChange={(event) => {
                                setReference(event.target.value);
                            }}
                        />
                    )}
                </FormField>
                <FormField label={t('pages.pg_061.recovery_point')} required error={command.errors.recovery_point}>
                    {(control) => (
                        <Input
                            {...control}
                            type="datetime-local"
                            value={point}
                            disabled={command.intent.frozen}
                            onChange={(event) => {
                                setPoint(event.target.value);
                            }}
                        />
                    )}
                </FormField>
                {lines.map((line, index) => (
                    <div key={index} className="flex flex-wrap items-end gap-3">
                        <FormField label={t('pages.pg_061.period')} required error={command.errors[`lines.${String(index)}.period_key`]} className="flex-1">
                            {(control) => (
                                <Input
                                    {...control}
                                    value={line.period}
                                    placeholder={detail.today.slice(0, 4)}
                                    disabled={command.intent.frozen}
                                    onChange={(event) => {
                                        setLines(lines.map((entry, at) => (at === index ? { ...entry, period: event.target.value.trim() } : entry)));
                                    }}
                                />
                            )}
                        </FormField>
                        <FormField label={t('pages.pg_061.highest_paper')} required error={command.errors[`lines.${String(index)}.highest`]} className="flex-1">
                            {(control) => (
                                <Input
                                    {...control}
                                    numeric
                                    inputMode="numeric"
                                    value={line.highest}
                                    disabled={command.intent.frozen}
                                    onChange={(event) => {
                                        setLines(lines.map((entry, at) => (at === index ? { ...entry, highest: event.target.value.replace(/\D/g, '') } : entry)));
                                    }}
                                />
                            )}
                        </FormField>
                        {lines.length > 1 && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-md"
                                aria-label={t('dialogs.back')}
                                onClick={() => {
                                    setLines(lines.filter((_entry, at) => at !== index));
                                }}
                            >
                                <Trash2 aria-hidden />
                            </Button>
                        )}
                    </div>
                ))}
                <div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="md"
                        onClick={() => {
                            setLines([...lines, { period: '', highest: '' }]);
                        }}
                    >
                        <Plus aria-hidden />
                        {t('pages.pg_061.add_period')}
                    </Button>
                </div>
                <Recording today={detail.today} />
                <CommandAnswer command={command} hidden={confirming} />
                <div>
                    <Button type="submit" variant="primary" size="lg" disabled={!filled}>
                        {t('pages.pg_061.raise')}
                    </Button>
                </div>
            </form>
            <ConfirmCommand
                open={confirming}
                onOpenChange={setConfirming}
                title={t('pages.pg_061.raise_title', { jenis })}
                consequence={t('pages.pg_061.raise_consequence')}
                items={
                    <ul role="list" className="flex flex-col gap-1 text-body text-text-primary">
                        {lines.map((line) => (
                            <li key={line.period}>{t('pages.pg_061.raise_line', { periode: line.period, nomor: String(Number(line.highest) + 1) })}</li>
                        ))}
                    </ul>
                }
                confirmLabel={t('pages.pg_061.raise')}
                pending={command.pending}
                answer={command.answer}
                onConfirm={() => {
                    command.submit(
                        {
                            company,
                            mode: 'raise',
                            restore_reference: reference.trim(),
                            // The recovery point is a WIB instant, whatever the browser's own time zone (BR-DT-06).
                            recovery_point: `${point}:00+07:00`,
                            lines: lines.map((line) => ({ period_key: line.period, highest: Number(line.highest) })),
                            business_date: detail.today,
                            business_date_prefilled: true,
                        },
                        () => {
                            setConfirming(false);
                            setOpen(false);
                            setReference('');
                            setPoint('');
                            setLines([{ period: '', highest: '' }]);
                        },
                    );
                }}
            />
        </FormSection>
    );
}
