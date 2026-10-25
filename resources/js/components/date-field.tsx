import { CalendarDays } from 'lucide-react';
import { type ComponentProps, useState } from 'react';
import type { DateRange as PickerRange } from 'react-day-picker';

import { Mark } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { InputGroup, InputGroupInput } from '@/components/ui/input-group';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import * as format from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

// Dates are the server's ISO days (`YYYY-MM-DD`, WIB) in and out; the calendar works on local midnights only to
// draw its grid, so no time zone reaches the value (LOCALIZATION §3.3).
export function toDay(date: Date): string {
    return `${String(date.getFullYear())}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export function fromDay(day: string): Date {
    const [year = 0, month = 1, date = 1] = day.split('-').map(Number);

    return new Date(year, month - 1, date);
}

/** A typed date: "02/10/2026", "2-10-2026" or "2026-10-02"; anything else, or an impossible day, is null. */
export function parseTypedDay(text: string): string | null {
    const value = text.trim();
    const iso = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(value);
    const local = /^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$/.exec(value);
    const parts = iso !== null ? [iso[1], iso[2], iso[3]] : local !== null ? [local[3], local[2], local[1]] : null;

    if (parts === null) {
        return null;
    }

    const [year, month, date] = parts.map(Number) as [number, number, number];
    const candidate = new Date(year, month - 1, date);

    return candidate.getFullYear() === year && candidate.getMonth() === month - 1 && candidate.getDate() === date ? toDay(candidate) : null;
}

export type BusinessDate = { date: string; chosen: boolean };

// PT-25's business date "Tanggal transaksi" (DESIGN_SYSTEM §9.3; ADMIN_FLOW IP-07; HO-08): one compact line in the
// form header, prefilled with the server's day — "Hari ini, {tanggal}", the payload saying it was only prefilled
// —; "Ubah" opens a calendar that offers no day after today; a chosen day reads "{tanggal} — dipilih", a day
// before today adds "Tanggal Mundur" and, on a numbered document, "Nomor mengikuti periode {periode}". A
// numbering command shows its date as a fixed fact (NM-03).
export function BusinessDateControl({
    today,
    value,
    onChange,
    periodOf,
    fixed = false,
}: {
    /** The server's current day in WIB. */
    today: string;
    value: BusinessDate;
    onChange: (value: BusinessDate) => void;
    /** On a numbered document: the numbering period a day falls in, as the document type's numbering states it. */
    periodOf?: (day: string) => string;
    fixed?: boolean;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const backdated = value.date < today;
    const line = value.chosen ? t('date.chosen', { tanggal: format.date(value.date) }) : t('date.today', { tanggal: format.date(value.date) });

    return (
        <div data-slot="business-date" className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-body text-text-secondary">
            <span>
                <span className="text-text-tertiary">{t('pt25.label')}: </span>
                <time dateTime={value.date} className="font-medium text-text-primary tabular-nums">
                    {line}
                </time>
            </span>
            {!fixed && (
                <>
                    <span aria-hidden className="text-text-tertiary">
                        ·
                    </span>
                    <Popover open={open} onOpenChange={setOpen}>
                        <PopoverTrigger render={<Button variant="ghost" size="xs" className="text-accent-olive underline-offset-2 hover:underline" />}>
                            {t('pt25.today.change')}
                        </PopoverTrigger>
                        <PopoverContent aria-label={t('pt25.label')}>
                            <Calendar
                                mode="single"
                                required
                                selected={fromDay(value.date)}
                                defaultMonth={fromDay(value.date)}
                                disabled={{ after: fromDay(today) }}
                                endMonth={fromDay(today)}
                                previousLabel={t('date.previous_month')}
                                nextLabel={t('date.next_month')}
                                onSelect={(date: Date) => {
                                    onChange({ date: toDay(date), chosen: true });
                                    setOpen(false);
                                }}
                            />
                        </PopoverContent>
                    </Popover>
                </>
            )}
            {value.chosen && backdated && <Mark kind="backdated" />}
            {value.chosen && backdated && periodOf !== undefined && <span className="basis-full text-caption">{t('date.number_period', { periode: periodOf(value.date) })}</span>}
        </div>
    );
}

// The date field (DESIGN_SYSTEM §10 "Date field"): the typed fallback with the calendar addon; the formatted day
// shown on leaving it, an unreadable text kept for the error under the field (IP-14).
export function DateField({
    value,
    onValueChange,
    latest,
    className,
    ...props
}: Omit<ComponentProps<'input'>, 'value' | 'onChange'> & { value: string | null; onValueChange: (day: string | null, text: string) => void; latest?: string }) {
    const { t } = useTranslation();
    const [text, setText] = useState(value === null ? '' : format.date(value));
    const [open, setOpen] = useState(false);

    return (
        <InputGroup className={className}>
            <InputGroupInput
                {...props}
                inputMode="numeric"
                autoComplete="off"
                placeholder={t('date.format_hint')}
                value={text}
                onChange={(event) => {
                    setText(event.target.value);
                    onValueChange(parseTypedDay(event.target.value), event.target.value);
                }}
                onFocus={() => {
                    if (value !== null) {
                        setText(format.date(value) === text ? value.split('-').reverse().join('/') : text);
                    }
                }}
                onBlur={() => {
                    if (value !== null) {
                        setText(format.date(value));
                    }
                }}
            />
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger render={<Button variant="ghost" size="icon-sm" className="mr-1" aria-label={t('pt25.today.change')} />}>
                    <Icon icon={CalendarDays} size="sm" />
                </PopoverTrigger>
                <PopoverContent align="end">
                    <Calendar
                        mode="single"
                        selected={value === null ? undefined : fromDay(value)}
                        defaultMonth={value === null ? undefined : fromDay(value)}
                        disabled={latest === undefined ? undefined : { after: fromDay(latest) }}
                        previousLabel={t('date.previous_month')}
                        nextLabel={t('date.next_month')}
                        onSelect={(date: Date | undefined) => {
                            const day = date === undefined ? null : toDay(date);
                            setText(day === null ? '' : format.date(day));
                            onValueChange(day, day ?? '');
                            setOpen(false);
                        }}
                    />
                </PopoverContent>
            </Popover>
        </InputGroup>
    );
}

export type DayRange = { from: string; to: string };
export type RangePreset = { id: string; label: string; range: DayRange };

// The date range (DESIGN_SYSTEM §10 "Date range"; GR-19): presets bound to the repository's periods — the page
// passes them — and a custom range in a dialog with the calendar in range mode, a bottom sheet on the phone.
export function DateRangeControl({ presets, value, onChange, label, latest }: { presets: RangePreset[]; value: DayRange; onChange: (range: DayRange) => void; label: string; latest?: string }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<PickerRange | undefined>({ from: fromDay(value.from), to: fromDay(value.to) });
    const active = presets.find((preset) => preset.range.from === value.from && preset.range.to === value.to)?.id ?? 'custom';

    return (
        <div role="group" aria-label={label} className="flex min-w-0 flex-wrap items-center gap-2">
            <ToggleGroup
                value={[active]}
                onValueChange={(next: string[]) => {
                    const preset = presets.find((candidate) => candidate.id === next[0]);

                    if (preset !== undefined) {
                        onChange(preset.range);
                    }
                }}
            >
                {presets.map((preset) => (
                    <ToggleGroupItem key={preset.id} value={preset.id}>
                        {preset.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
            <Button
                variant="outline"
                size="md"
                className={cn(active === 'custom' && 'border-accent-olive')}
                onClick={() => {
                    setDraft({ from: fromDay(value.from), to: fromDay(value.to) });
                    setOpen(true);
                }}
            >
                <Icon icon={CalendarDays} size="sm" />
                <span className="tabular-nums">
                    {format.date(value.from)} – {format.date(value.to)}
                </span>
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent phone="bottom" closeLabel={t('actions.close')} className="md:w-auto">
                    <DialogHeader>
                        <DialogTitle>{label}</DialogTitle>
                    </DialogHeader>
                    <Calendar
                        mode="range"
                        selected={draft}
                        onSelect={setDraft}
                        defaultMonth={fromDay(value.from)}
                        disabled={latest === undefined ? undefined : { after: fromDay(latest) }}
                        previousLabel={t('date.previous_month')}
                        nextLabel={t('date.next_month')}
                    />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            size="lg"
                            onClick={() => {
                                setOpen(false);
                            }}
                        >
                            {t('actions.cancel')}
                        </Button>
                        <Button
                            variant="primary"
                            size="lg"
                            disabled={draft?.from === undefined || draft.to === undefined}
                            onClick={() => {
                                if (draft?.from !== undefined && draft.to !== undefined) {
                                    onChange({ from: toDay(draft.from), to: toDay(draft.to) });
                                    setOpen(false);
                                }
                            }}
                        >
                            {t('actions.apply')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

// Code entry (DESIGN_SYSTEM §10 "Code entry"; GR-12): one input with `autocomplete="one-time-code"` — paste and
// autofill work, the digits only, at most `length`; S2-2's reading of the two shadcn/ui options.
export function CodeEntry({ length = 6, value, onValueChange, className, ...props }: Omit<ComponentProps<'input'>, 'value' | 'onChange'> & { length?: number; value: string; onValueChange: (value: string) => void }) {
    return (
        <Input
            {...props}
            numeric
            data-code
            inputMode="numeric"
            autoComplete="one-time-code"
            pattern={`\\d{${String(length)}}`}
            maxLength={length}
            value={value}
            className={cn('max-w-48 text-center', className)}
            onChange={(event) => {
                onValueChange(event.target.value.replace(/\D/g, '').slice(0, length));
            }}
        />
    );
}
