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
