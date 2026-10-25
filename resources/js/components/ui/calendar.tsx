import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ComponentProps } from 'react';
import { DayPicker } from 'react-day-picker';
import { id } from 'react-day-picker/locale';

import { buttonVariants } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { cn } from '@/lib/utils';

// The calendar of the date field and the business date (DESIGN_SYSTEM §10; PT-25): a month grid whose day names
// and month captions are date values, formatted one way in both languages (OQ-04 (a); LOCALIZATION §3.3); the
// navigation's accessible names are the caller's catalogue strings. Today is ringed, the chosen day filled in
// primary; a day after the latest allowed date is disabled — the business date never offers one (IP-07).
export type CalendarProps = ComponentProps<typeof DayPicker> & { previousLabel: string; nextLabel: string };

export function Calendar({ className, classNames, previousLabel, nextLabel, showOutsideDays = true, ...props }: CalendarProps) {
    return (
        <DayPicker
            locale={id}
            showOutsideDays={showOutsideDays}
            labels={{ labelPrevious: () => previousLabel, labelNext: () => nextLabel }}
            className={cn('w-fit', className)}
            classNames={{
                months: 'relative flex flex-col gap-4',
                month: 'flex flex-col gap-3',
                nav: 'absolute inset-x-0 top-0 flex items-center justify-between',
                button_previous: cn(buttonVariants({ variant: 'ghost', size: 'icon-sm' })),
                button_next: cn(buttonVariants({ variant: 'ghost', size: 'icon-sm' })),
                month_caption: 'flex h-control-sm items-center justify-center px-10',
                caption_label: 'text-body font-semibold text-text-primary',
                month_grid: 'w-full border-collapse',
                weekdays: 'flex',
                weekday: 'flex size-control-md items-center justify-center text-caption font-medium text-text-tertiary',
                week: 'mt-1 flex',
                day: 'flex size-control-md items-center justify-center p-0 text-body',
                day_button:
                    'inline-flex size-control-sm items-center justify-center rounded-sm text-text-primary tabular-nums outline-none hover:bg-row-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus aria-selected:bg-primary aria-selected:font-semibold aria-selected:text-text-on-primary',
                today: '[&>button]:ring-1 [&>button]:ring-accent-olive',
                selected: '',
                outside: '[&>button]:text-text-disabled',
                disabled: '[&>button]:cursor-not-allowed [&>button]:text-text-disabled [&>button]:hover:bg-transparent',
                range_start: '[&>button]:bg-primary [&>button]:text-text-on-primary',
                range_end: '[&>button]:bg-primary [&>button]:text-text-on-primary',
                range_middle: '[&>button]:rounded-none [&>button]:bg-lime-150 [&>button]:text-text-primary',
                hidden: 'invisible',
                ...classNames,
            }}
            components={{
                Chevron: ({ orientation }) => <Icon icon={orientation === 'left' ? ChevronLeft : ChevronRight} size="sm" />,
            }}
            {...props}
        />
    );
}
