// The value formats of LOCALIZATION §3.3 and DESIGN_SYSTEM §16, one way in both languages — the English
// interface translates labels, never values (OQ-04 (a)). Money and quantities arrive as exact decimal strings
// and are never rounded; dates and times are WIB (BR-DT-06). app/Support/Localization/Formats.php writes the
// same formats on the server, both held to tests/Fixtures/Localization/formats.json (TASK-014).

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const MONTHS_LONG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const MINUS = '−';
// WIB is UTC+7 all year, with no daylight saving.
const WIB_OFFSET_MS = 7 * 60 * 60 * 1000;

function decimal(value: string): { negative: boolean; whole: string; fraction: string } {
    const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(value);

    if (match === null) {
        throw new RangeError('A value is an exact decimal string.');
    }

    const whole = (match[2] ?? '').replace(/^0+/, '') || '0';
    const fraction = match[3] ?? '';

    return { negative: match[1] === '-' && (whole !== '0' || /[1-9]/.test(fraction)), whole, fraction };
}

function thousands(digits: string): string {
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function exact(value: string): string {
    const { negative, whole, fraction } = decimal(value);
    const significant = fraction.replace(/0+$/, '');

    return (negative ? MINUS : '') + thousands(whole) + (significant === '' ? '' : `,${significant}`);
}

function day(date: string): [number, number, number] {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date);
    const [year, month, dayOfMonth] = [Number(match?.[1]), Number(match?.[2]), Number(match?.[3])];
    const check = new Date(Date.UTC(year, month - 1, dayOfMonth));

    if (match === null || check.getUTCMonth() !== month - 1 || check.getUTCDate() !== dayOfMonth) {
        throw new RangeError('A date is a Y-m-d day.');
    }

    return [year, month, dayOfMonth];
}

function wib(instant: string): Date {
    const time = Date.parse(instant);

    if (Number.isNaN(time)) {
        throw new RangeError('An instant is an ISO 8601 timestamp.');
    }

    return new Date(time + WIB_OFFSET_MS);
}

const two = (value: number): string => String(value).padStart(2, '0');

/** Rupiah: "Rp1.250.000", with sen "Rp1.250.000,50" — or always two decimals in a column where any value has sen. */
export function money(amount: string, withSen = false): string {
    const { negative, whole, fraction } = decimal(amount);

    if (fraction.replace(/0+$/, '').length > 2) {
        throw new RangeError('Rupiah has at most two decimals.');
    }

    const sen = fraction.slice(0, 2).padEnd(2, '0');

    return (negative ? MINUS : '') + 'Rp' + thousands(whole) + (withSen || sen !== '00' ? `,${sen}` : '');
}

/** A quantity, exact, then its unit, the alternate unit as a hint: "3,5 kg", "120 rim (24 dus)". */
export function quantity(value: string, unit: string, alternateValue?: string, alternateUnit?: string): string {
    const text = `${exact(value)} ${unit}`;

    return alternateValue === undefined || alternateUnit === undefined ? text : `${text} (${exact(alternateValue)} ${alternateUnit})`;
}

/** A percentage or rate: "11%", "0,25%". */
export function percent(value: string): string {
    return `${exact(value)}%`;
}

/** A date: "02 Okt 2026". */
export function date(value: string): string {
    const [year, month, dayOfMonth] = day(value);

    return `${two(dayOfMonth)} ${MONTHS[month - 1] ?? ''} ${String(year)}`;
}

/** A date in a sentence or a document: "2 Oktober 2026". */
export function dateLong(value: string): string {
    const [year, month, dayOfMonth] = day(value);

    return `${String(dayOfMonth)} ${MONTHS_LONG[month - 1] ?? ''} ${String(year)}`;
}

/** A time on the 24-hour clock in WIB: "14.05 WIB". */
export function time(instant: string): string {
    const local = wib(instant);

    return `${two(local.getUTCHours())}.${two(local.getUTCMinutes())} WIB`;
}

/** A date with its time: "02 Okt 2026 14.05 WIB". */
export function dateTime(instant: string): string {
    return `${date(dayOf(instant))} ${time(instant)}`;
}

/** An instant's day in WIB as `YYYY-MM-DD`, for the day formats above. */
export function dayOf(instant: string): string {
    const local = wib(instant);

    return `${String(local.getUTCFullYear())}-${two(local.getUTCMonth() + 1)}-${two(local.getUTCDate())}`;
}

/** A period: "Okt 2026" for a month (`YYYY-MM`), "2026" for a year. */
export function period(value: string): string {
    if (/^\d{4}$/.test(value)) {
        return value;
    }

    const match = /^(\d{4})-(\d{2})$/.exec(value);
    const month = Number(match?.[2]);

    if (match === null || month < 1 || month > 12) {
        throw new RangeError('A period is a year or a year and month.');
    }

    return `${MONTHS[month - 1] ?? ''} ${match[1] ?? ''}`;
}
