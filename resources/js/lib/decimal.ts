// Exact decimal strings for a form's provisional figures (ADMIN_FLOW IP-13; DESIGN_SYSTEM §14.1): a line's
// *Perkiraan* subtotal is computed on the entered strings with integers, never through a floating-point number,
// and is replaced by the server's figure after the answer (UXI-14).
type Scaled = { units: bigint; scale: number };

function scaled(value: string): Scaled {
    const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(value.trim());

    if (match === null) {
        throw new RangeError(`Not a decimal string: ${value}`);
    }

    const fraction = match[3] ?? '';
    const units = BigInt(`${match[2] ?? '0'}${fraction}`);

    return { units: match[1] === '-' ? -units : units, scale: fraction.length };
}

function text({ units, scale }: Scaled): string {
    const negative = units < 0n;
    const digits = (negative ? -units : units).toString().padStart(scale + 1, '0');
    const whole = scale === 0 ? digits : digits.slice(0, -scale);
    const fraction = scale === 0 ? '' : digits.slice(-scale);

    return `${negative && /[1-9]/.test(digits) ? '-' : ''}${whole}${fraction === '' ? '' : `.${fraction}`}`;
}

export function multiply(a: string, b: string): string {
    const x = scaled(a);
    const y = scaled(b);

    return text({ units: x.units * y.units, scale: x.scale + y.scale });
}

export function add(...values: string[]): string {
    const all = values.map(scaled);
    const scale = Math.max(0, ...all.map((value) => value.scale));

    return text({ units: all.reduce((sum, value) => sum + value.units * 10n ** BigInt(scale - value.scale), 0n), scale });
}

/** Rounds half away from zero to `scale` decimals — the presentation of a provisional figure only. */
export function round(value: string, scale: number): string {
    const x = scaled(value);

    if (x.scale <= scale) {
        return text({ units: x.units * 10n ** BigInt(scale - x.scale), scale });
    }

    const divisor = 10n ** BigInt(x.scale - scale);
    const magnitude = x.units < 0n ? -x.units : x.units;
    const rounded = (magnitude + divisor / 2n) / divisor;

    return text({ units: x.units < 0n ? -rounded : rounded, scale });
}

/** `value ÷ divisor` to `scale` decimals, half away from zero or truncated, on integers as the server's BigDecimal computes it. */
export function quotient(value: string, divisor: string, scale: number, mode: 'HALF_UP' | 'DOWN'): string {
    const x = scaled(value);
    const y = scaled(divisor);
    const top = x.units * 10n ** BigInt(y.scale + scale);
    const bottom = y.units * 10n ** BigInt(x.scale);
    const a = top < 0n ? -top : top;
    const b = bottom < 0n ? -bottom : bottom;
    const units = mode === 'DOWN' ? a / b : (2n * a + b) / (2n * b);

    return text({ units: top < 0n !== bottom < 0n ? -units : units, scale });
}

export type ProvisionalTotals = { lines: string[]; subtotal: string; tax: string; total: string };

/** A document composer's provisional totals (DESIGN_SYSTEM §14.3; DC-3): each line rounded half away from zero to
    sen, the tax on the subtotal, the total their sum — as PostgreSQL's numeric arithmetic, the server's, computes them,
    held to one list by tests/Frontend/provisional-totals.json (TL-03; TASK-029) —, labelled "perkiraan" until saved. */
export function provisionalTotals(lines: [quantity: string, unitPrice: string][], taxPercent: string): ProvisionalTotals {
    const amounts = lines.map(([quantity, unitPrice]) => round(multiply(quantity, unitPrice), 2));
    const subtotal = round(amounts.length === 0 ? '0' : add(...amounts), 2);
    const tax = round(multiply(multiply(subtotal, taxPercent), '0.01'), 2);

    return { lines: amounts, subtotal, tax, total: round(add(subtotal, tax), 2) };
}
