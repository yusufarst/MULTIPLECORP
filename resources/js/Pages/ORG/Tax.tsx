// PG-062 *Pajak* (ROUTE-062, ROUTE-138; NAV-062; SCR-56; IA §8.6; DATABASE §12; GAP-026; TASK-025), the Owner's alone: the
// tax configuration as data — tax types, effective-dated rate versions with their base fraction and rounding mode,
// company tax profiles and treatment rules by context, global or a company's. Each tab lists its rows and one form adds
// a row or changes the one chosen at the version it read; an overlapping range answers at its field (C-52), a stale
// edit CONFLICT (IP-03). On the phone the configuration is read-only (`[desk]`).
import { router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

import { DataTable } from '@/components/data-list';
import { FormField } from '@/components/form';
import { type Answer, OutcomeMessage } from '@/components/outcome-message';
import { PageHeader } from '@/components/page-header';
import { RegionEmpty } from '@/components/region-state';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs';
import { DateText } from '@/components/values';
import AppShell from '@/layouts/AppShell';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

type Kind = 'tax_type' | 'rate_version' | 'profile' | 'rule';
type Base = { handle: string; lock_version: number };
type Ranged = { effective_from: string | null; effective_to: string | null };
type TaxType = Base & { code: string; name: string; category: string; active: boolean };
type Rate = Base & Ranged & { type: string; rate_percent: string; base_numerator: number; base_denominator: number; rounding_mode: string };
type Profile = Base & Ranged & { company: string; registration_status: string };
type Rule = Base & Ranged & { company: string | null; context: string; type: string; treatment: string; evidence_required: boolean };
type Props = { types: TaxType[]; rates: Rate[]; profiles: Profile[]; rules: Rule[]; companies: { id: string; code: string; name: string }[]; section: Kind };
type Values = Record<string, string | boolean>;

const KINDS: Kind[] = ['tax_type', 'rate_version', 'profile', 'rule'];
const CATEGORIES = ['OUTPUT_TAX', 'INPUT_TAX', 'WITHHOLDING', 'COLLECTION', 'OTHER'];
const CONTEXTS = ['SALES_INVOICE', 'PURCHASE_LINE', 'PAYMENT_WITHHELD', 'PAYMENT_COLLECTED', 'TAX_REMITTANCE'];
const TREATMENTS = ['OUTPUT_EXCLUDED_FROM_REVENUE', 'INPUT_CREDITABLE', 'INPUT_TO_ACQUISITION_COST', 'SETTLEMENT_NO_EXPENSE', 'SETTLEMENT_TO_EXPENSE', 'REMITTANCE_NOT_COST', 'REMITTANCE_TO_EXPENSE'];

const label = (group: string, code: string): TranslationKey => `tax.${group}.${code}` as TranslationKey;

function PageAnswer() {
    const flash = usePage().flash as { answer?: { kind?: unknown; text?: unknown } };

    return flash.answer?.kind === 'COMMITTED' && typeof flash.answer.text === 'string' ? <OutcomeMessage answer={{ kind: 'COMMITTED', text: flash.answer.text }} /> : null;
}

function CommandAnswer({ command }: { command: { answer: Answer | null; unknown: boolean; retry: () => void } }) {
    const { t } = useTranslation();

    if (command.answer === null || command.answer.kind === 'COMMITTED') return null;

    return (
        <OutcomeMessage
            answer={command.answer}
            action={
                command.unknown ? (
                    <Button variant="outline" size="sm" onClick={command.retry}>
                        {t('actions.try_again')}
                    </Button>
                ) : undefined
            }
        />
    );
}

function Range({ from, to }: { from: string | null; to: string | null }) {
    const { t } = useTranslation();

    return (
        <span className="whitespace-nowrap">
            {from !== null ? <DateText value={from} /> : '—'} – {to !== null ? <DateText value={to} /> : t('tax.open_ended')}
        </span>
    );
}

/** One kind's form: a new row, or the chosen one at the version it read. */
function KindForm({ kind, row, initial, props, onDone }: { kind: Kind; row: Base | null; initial: Values; props: Props; onDone: () => void }) {
    const { t } = useTranslation();
    const [values, setValues] = useState<Values>(initial);
    const [leaving, setLeaving] = useState(false);
    const command = useCommand<Record<string, string | number | boolean | null>>(() => '/pengaturan/pajak', 'put');
    const set = (name: string, value: string | boolean) => { setValues((current) => ({ ...current, [name]: value })); };
    const text = (name: string) => (typeof values[name] === 'string' ? values[name] : '');
    const input = (name: string, title: TranslationKey, type = 'text', disabled = false) => (
        <FormField key={name} label={t(title)} required={name !== 'effective_to'} error={command.errors[name]}>
            {(control) => <Input {...control} type={type} value={text(name)} disabled={disabled} onChange={(event) => { set(name, event.target.value); }} />}
        </FormField>
    );
    const select = (name: string, title: TranslationKey, options: { value: string; label: string }[], disabled = false, optional = false) => (
        <FormField key={name} label={t(title)} required={!optional} error={command.errors[name]}>
            {(control) => (
                <NativeSelect {...control} value={text(name)} disabled={disabled} onChange={(event) => { set(name, event.target.value); }}>
                    <option value="">{optional ? t('tax.global') : t('forms.choose')}</option>
                    {options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </NativeSelect>
            )}
        </FormField>
    );
    const fixed = row !== null;
    const typeOptions = props.types.map((type) => ({ value: type.code, label: `${type.code} · ${type.name}` }));
    const companyOptions = props.companies.map((company) => ({ value: company.id, label: `${company.code} · ${company.name}` }));
    const range = [input('effective_from', 'tax.field.effective_from', 'date'), input('effective_to', 'tax.field.effective_to', 'date')];
    const fields = {
        tax_type: [
            input('code', 'tax.field.code', 'text', fixed),
            input('name', 'tax.field.name'),
            select('category', 'tax.field.category', CATEGORIES.map((code) => ({ value: code, label: t(label('category', code)) }))),
        ],
        rate_version: [
            select('type', 'tax.field.type', typeOptions, fixed),
            input('rate_percent', 'tax.field.rate_percent'),
            input('base_numerator', 'tax.field.base_numerator', 'number'),
            input('base_denominator', 'tax.field.base_denominator', 'number'),
            select('rounding_mode', 'tax.field.rounding_mode', ['HALF_UP', 'DOWN'].map((code) => ({ value: code, label: t(label('rounding', code)) }))),
            ...range,
        ],
        profile: [select('company', 'tax.field.company', companyOptions, fixed), input('registration_status', 'tax.field.registration_status'), ...range],
        rule: [
            select('company', 'tax.field.company', companyOptions, fixed, true),
            select('context', 'tax.field.context', CONTEXTS.map((code) => ({ value: code, label: t(label('context', code)) })), fixed),
            select('type', 'tax.field.type', typeOptions, fixed),
            select('treatment', 'tax.field.treatment', TREATMENTS.map((code) => ({ value: code, label: t(label('treatment', code)) }))),
            ...range,
        ],
    }[kind];
    const flag = kind === 'tax_type' ? 'active' : kind === 'rule' ? 'evidence_required' : null;

    return (
        <form
            className="flex min-w-0 flex-col gap-4 max-md:hidden"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                const payload: Record<string, string | number | boolean | null> = { kind, ...values, company: text('company') === '' ? null : text('company'), effective_to: text('effective_to') === '' ? null : text('effective_to') };
                command.submit(row === null ? payload : { ...payload, row: row.handle, lock_version: row.lock_version }, onDone);
            }}
        >
            <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2">{fields}</div>
            {flag !== null && (
                <div className="flex items-center gap-3">
                    <Checkbox id={`tax-${flag}`} checked={values[flag] === true} onCheckedChange={(checked) => { set(flag, checked); }} />
                    <label htmlFor={`tax-${flag}`} className="text-body text-text-primary">
                        {t(`tax.field.${flag}` as TranslationKey)}
                    </label>
                </div>
            )}
            <CommandAnswer command={command} />
            <UnsentInputDialog open={leaving} kind="leave" onStay={() => { setLeaving(false); }} onLeave={onDone} />
            <div className="flex justify-end gap-3">
                <Button type="button" variant="ghost" onClick={() => { if (JSON.stringify(values) === JSON.stringify(initial)) onDone(); else setLeaving(true); }}>
                    {t('actions.cancel')}
                </Button>
                <Button type="submit" pending={command.pending} disabled={command.frozen}>
                    {t('glossary.gl_157')}
                </Button>
            </div>
        </form>
    );
}

const BLANK: Record<Kind, Values> = {
    tax_type: { code: '', name: '', category: '', active: true },
    rate_version: { type: '', rate_percent: '', base_numerator: '1', base_denominator: '1', rounding_mode: 'HALF_UP', effective_from: '', effective_to: '' },
    profile: { company: '', registration_status: '', effective_from: '', effective_to: '' },
    rule: { company: '', context: '', type: '', treatment: '', evidence_required: false, effective_from: '', effective_to: '' },
};

export default function Tax(props: Props) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<{ kind: Kind; row: Base | null; values: Values } | null>(null);
    const companyId = (code: string | null) => props.companies.find((company) => company.code === code)?.id ?? '';
    const range = (row: Ranged) => ({ effective_from: row.effective_from ?? '', effective_to: row.effective_to ?? '' });
    const edit = (kind: Kind, row: Base | null, values: Values) => { setEditing({ kind, row, values }); };
    const tables = {
        tax_type: (
            <DataTable<TaxType>
                caption={t('tax.section.tax_type')}
                rows={props.types}
                rowKey={(row) => row.handle}
                rowHref={() => `/pengaturan/pajak?bagian=${props.section}`}
                rowActions={(row) => [{ label: t('tax.edit'), onSelect: () => { edit('tax_type', row, { code: row.code, name: row.name, category: row.category, active: row.active }); } }]}
                columns={[
                    { key: 'code', header: t('tax.field.code'), cell: (row) => row.code },
                    { key: 'name', header: t('tax.field.name'), cell: (row) => row.name },
                    { key: 'category', header: t('tax.field.category'), cell: (row) => t(label('category', row.category)) },
                    { key: 'active', header: t('tax.field.active'), cell: (row) => (row.active ? t('tax.yes') : t('tax.no')) },
                ]}
            />
        ),
        rate_version: (
            <DataTable<Rate>
                caption={t('tax.section.rate_version')}
                rows={props.rates}
                rowKey={(row) => row.handle}
                rowHref={() => `/pengaturan/pajak?bagian=${props.section}`}
                rowActions={(row) => [{ label: t('tax.edit'), onSelect: () => { edit('rate_version', row, { type: row.type, rate_percent: row.rate_percent, base_numerator: String(row.base_numerator), base_denominator: String(row.base_denominator), rounding_mode: row.rounding_mode, ...range(row) }); } }]}
                columns={[
                    { key: 'type', header: t('tax.field.type'), cell: (row) => row.type },
                    { key: 'rate', header: t('tax.field.rate_percent'), numeric: true, cell: (row) => `${row.rate_percent}%` },
                    { key: 'base', header: t('tax.field.base'), numeric: true, cell: (row) => `${String(row.base_numerator)}/${String(row.base_denominator)}` },
                    { key: 'rounding', header: t('tax.field.rounding_mode'), cell: (row) => t(label('rounding', row.rounding_mode)) },
                    { key: 'range', header: t('tax.field.effective'), cell: (row) => <Range from={row.effective_from} to={row.effective_to} /> },
                ]}
            />
        ),
        profile: (
            <DataTable<Profile>
                caption={t('tax.section.profile')}
                rows={props.profiles}
                rowKey={(row) => row.handle}
                rowHref={() => `/pengaturan/pajak?bagian=${props.section}`}
                rowActions={(row) => [{ label: t('tax.edit'), onSelect: () => { edit('profile', row, { company: companyId(row.company), registration_status: row.registration_status, ...range(row) }); } }]}
                columns={[
                    { key: 'company', header: t('tax.field.company'), cell: (row) => row.company },
                    { key: 'status', header: t('tax.field.registration_status'), cell: (row) => row.registration_status },
                    { key: 'range', header: t('tax.field.effective'), cell: (row) => <Range from={row.effective_from} to={row.effective_to} /> },
                ]}
            />
        ),
        rule: (
            <DataTable<Rule>
                caption={t('tax.section.rule')}
                rows={props.rules}
                rowKey={(row) => row.handle}
                rowHref={() => `/pengaturan/pajak?bagian=${props.section}`}
                rowActions={(row) => [{ label: t('tax.edit'), onSelect: () => { edit('rule', row, { company: companyId(row.company), context: row.context, type: row.type, treatment: row.treatment, evidence_required: row.evidence_required, ...range(row) }); } }]}
                columns={[
                    { key: 'company', header: t('tax.field.company'), cell: (row) => row.company ?? t('tax.global') },
                    { key: 'context', header: t('tax.field.context'), cell: (row) => t(label('context', row.context)) },
                    { key: 'type', header: t('tax.field.type'), cell: (row) => row.type },
                    { key: 'treatment', header: t('tax.field.treatment'), cell: (row) => t(label('treatment', row.treatment)) },
                    { key: 'range', header: t('tax.field.effective'), cell: (row) => <Range from={row.effective_from} to={row.effective_to} /> },
                ]}
            />
        ),
    };
    const counts = { tax_type: props.types.length, rate_version: props.rates.length, profile: props.profiles.length, rule: props.rules.length };

    return (
        <AppShell title={t('pages.pg_062.title')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('pages.pg_062.title')} />
                <p className="text-body text-text-secondary">{t('tax.intro')}</p>
                <PageAnswer />
                <Tabs value={props.section} onValueChange={(value) => { router.get('/pengaturan/pajak', { bagian: String(value) }, { preserveScroll: true }); }}>
                    <TabsList>
                        {KINDS.map((kind) => (
                            <TabsTab key={kind} value={kind}>
                                {t(`tax.section.${kind}` as TranslationKey)}
                            </TabsTab>
                        ))}
                    </TabsList>
                    {KINDS.map((kind) => (
                        <TabsPanel key={kind} value={kind}>
                            <div className="flex min-w-0 flex-col gap-4">
                                {counts[kind] === 0 ? <RegionEmpty text={t(`tax.empty.${kind}` as TranslationKey)} /> : tables[kind]}
                                {editing?.kind === kind ? (
                                    <KindForm key={editing.row?.handle ?? 'new'} kind={kind} row={editing.row} initial={editing.values} props={props} onDone={() => { setEditing(null); }} />
                                ) : (
                                    <div className="max-md:hidden">
                                        <Button variant="outline" onClick={() => { edit(kind, null, BLANK[kind]); }}>
                                            <Icon icon={Plus} size="sm" />
                                            {t(`tax.add.${kind}` as TranslationKey)}
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </TabsPanel>
                    ))}
                </Tabs>
            </div>
        </AppShell>
    );
}
