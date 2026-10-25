import { CircleAlert } from 'lucide-react';
import { type ComponentProps, type ReactNode, useEffect, useId, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { parseDecimal } from '@/lib/number-entry';
import * as format from '@/lib/format';
import { cn } from '@/lib/utils';

// PT-09's form layout and validation (DESIGN_SYSTEM §9.3; ADMIN_FLOW IP-13, IP-14): sections under subheading titles,
// not in panels; labels above fields with "(wajib)" after a required one; helper text only where it prevents an
// error; errors under their field, the field marked `aria-invalid` and described by its message; the server's
// messages arrive as data. A field that depends on another stays disabled with its reason beside it (GF-12).
export function FormSection({ title, children, className }: { title: ReactNode; children: ReactNode; className?: string }) {
    const id = useId();

    return (
        <section aria-labelledby={id} className={cn('flex min-w-0 flex-col gap-4', className)}>
            <h2 id={id} className="text-heading-sm font-semibold text-text-primary">
                {title}
            </h2>
            {children}
        </section>
    );
}

export type FieldControlProps = { id: string; 'aria-invalid'?: true; 'aria-describedby'?: string; 'aria-required'?: true; disabled?: boolean };

export function FormField({
    label,
    required = false,
    help,
    error,
    dependsOn,
    id: given,
    className,
    children,
}: {
    label: ReactNode;
    required?: boolean;
    help?: ReactNode;
    error?: ReactNode;
    /** GF-12: the field it waits for, named in its reason; the control stays disabled until then. */
    dependsOn?: string;
    /** The control's id where the page addresses it, e.g. for IP-14's "Perbaiki"; otherwise one is made. */
    id?: string;
    className?: string;
    children: (control: FieldControlProps) => ReactNode;
}) {
    const { t } = useTranslation();
    const made = useId();
    const id = given ?? made;
    const helpId = `${id}-help`;
    const errorId = `${id}-error`;
    const reasonId = `${id}-reason`;
    const described = [help !== undefined ? helpId : null, dependsOn !== undefined ? reasonId : null, error !== undefined && error !== null ? errorId : null].filter((part) => part !== null).join(' ');

    return (
        <Field className={className}>
            <FieldLabel htmlFor={id} required={required} requiredText={t('forms.required')}>
                {label}
            </FieldLabel>
            {children({
                id,
                'aria-invalid': error !== undefined && error !== null ? true : undefined,
                'aria-describedby': described === '' ? undefined : described,
                'aria-required': required ? true : undefined,
                disabled: dependsOn !== undefined ? true : undefined,
            })}
            {dependsOn !== undefined && (
                <FieldDescription id={reasonId} className="text-text-secondary">
                    {t('forms.depends_on', { kolom: dependsOn })}
                </FieldDescription>
            )}
            {help !== undefined && <FieldDescription id={helpId}>{help}</FieldDescription>}
            <FieldError id={errorId}>{error}</FieldError>
        </Field>
    );
}

/** IP-14's summary line and "Pemeriksaan": how many fields need attention, each with "Perbaiki" taking focus to it. */
export function ValidationSummary({ fields }: { fields: { id: string; label: string; message: string }[] }) {
    const { t, tc } = useTranslation();
    const first = fields[0]?.id;

    useEffect(() => {
        if (first !== undefined) {
            document.getElementById(first)?.focus();
        }
    }, [fields, first]);

    if (fields.length === 0) {
        return null;
    }

    return (
        <div role="alert" className="flex flex-col gap-2 rounded-md border border-border bg-surface p-3">
            <p className="flex items-center gap-2 text-body font-semibold text-text-primary">
                <Icon icon={CircleAlert} size="md" className="text-danger-icon" />
                {tc('messages.msg_12', fields.length)}
            </p>
            <ul role="list" className="flex flex-col gap-1">
                {fields.map((field) => (
                    <li key={field.id} className="flex flex-wrap items-center justify-between gap-2 text-body">
                        <span>
                            <span className="font-medium">{field.label}</span>: {field.message}
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                document.getElementById(field.id)?.focus();
                            }}
                        >
                            {t('actions.fix')}
                        </Button>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** IP-13's money or quantity field: the entered text kept while typing, the formatted value shown on leaving it,
    the normalized decimal string reported for sending — never a number. */
export function DecimalInput({
    value,
    onValueChange,
    scale,
    kind = 'quantity',
    unit,
    invalidMessage,
    ...props
}: Omit<ComponentProps<'input'>, 'value' | 'onChange'> & {
    value: string;
    onValueChange: (decimal: string | null, text: string) => void;
    scale: number;
    kind?: 'money' | 'quantity';
    unit?: string;
    invalidMessage?: (reason: 'format' | 'scale' | 'sign') => void;
}) {
    const [text, setText] = useState(value === '' ? '' : display(value, kind, unit));

    return (
        <Input
            {...props}
            numeric
            inputMode="decimal"
            autoComplete="off"
            value={text}
            onChange={(event) => {
                setText(event.target.value);
                const parsed = parseDecimal(entered(event.target.value, kind, unit), scale);
                onValueChange(parsed.ok ? parsed.value : null, event.target.value);
            }}
            onBlur={() => {
                const parsed = parseDecimal(entered(text, kind, unit), scale);

                if (parsed.ok) {
                    setText(display(parsed.value, kind, unit));
                } else if (text.trim() !== '') {
                    invalidMessage?.(parsed.reason);
                }
            }}
        />
    );
}

/** The entered text without the field's own presentation — "Rp" before money, the unit after a quantity — so a
    formatted value can be edited in place; anything else stays for the parser to refuse. */
function entered(text: string, kind: 'money' | 'quantity', unit?: string): string {
    const trimmed = text.trim();

    if (kind === 'money') {
        return trimmed.replace(/^(−|-)?Rp\s?/, '$1');
    }

    return unit !== undefined && trimmed.endsWith(` ${unit}`) ? trimmed.slice(0, -unit.length - 1) : trimmed;
}

function display(decimal: string, kind: 'money' | 'quantity', unit?: string): string {
    if (kind === 'money') {
        return format.money(decimal);
    }

    return unit === undefined ? format.quantity(decimal, '').trimEnd() : format.quantity(decimal, unit);
}
