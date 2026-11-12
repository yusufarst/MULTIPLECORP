// UXS-14 (PT-25; NM-05): the numbering period a backdated business date's number follows — the scheme in force on
// that day (the latest whose first day is not after it), its period `YYYY` under YEAR and `YYYY-MM` under MONTH, as
// PG-061 names periods. A day before every scheme has none: the form then shows no period line, and the command's own
// answer (MSG-17) states why.

export type NumberPeriods = { from: string; granularity: string }[];

/** The period of a day under a type's schemes (oldest first), or null before any. */
export function numberPeriod(periods: NumberPeriods, day: string): string | null {
    let granularity: string | null = null;

    for (const scheme of periods) {
        if (scheme.from <= day) granularity = scheme.granularity;
    }

    if (granularity === null) return null;

    return granularity === 'YEAR' ? day.slice(0, 4) : day.slice(0, 7);
}

/** A `BusinessDateControl`'s `periodOf` for a type's schemes, or none when the form draws no number of that type. */
export function periodOf(periods: NumberPeriods | null | undefined): ((day: string) => string | null) | undefined {
    if (periods === null || periods === undefined) return undefined;

    return (day) => numberPeriod(periods, day);
}
