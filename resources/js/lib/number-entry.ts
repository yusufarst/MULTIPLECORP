// IP-13's entry of money and quantities (ADMIN_FLOW §7; ARCHITECTURE §10; LOCALIZATION §3.3): a purely textual
// normalization of the Indonesian separators — a dot accepted only as the thousands separator between groups of
// three digits and removed, the decimal comma turned into a point —, never through a floating-point number. Any
// other dot or character, or more decimals than the field's scale, is a validation error, never a silent
// reinterpretation or rounding (IP-14).

export type Parsed = { ok: true; value: string } | { ok: false; reason: 'format' | 'scale' | 'sign' };

const GROUPED = /^\d{1,3}(?:\.\d{3})+$/;
const PLAIN = /^\d+$/;

export function parseDecimal(input: string, scale: number, allowNegative = false): Parsed {
    const text = input.trim().replace(/−/g, '-');

    if (text === '') {
        return { ok: false, reason: 'format' };
    }

    const negative = text.startsWith('-');

    if (negative && !allowNegative) {
        return { ok: false, reason: 'sign' };
    }

    const unsigned = negative ? text.slice(1) : text;
    const [whole = '', fraction, extra] = unsigned.split(',');

    if (extra !== undefined || (!GROUPED.test(whole) && !PLAIN.test(whole))) {
        return { ok: false, reason: 'format' };
    }

    if (fraction !== undefined && !/^\d*$/.test(fraction)) {
        return { ok: false, reason: 'format' };
    }

    if (fraction !== undefined && fraction.length > scale) {
        return { ok: false, reason: 'scale' };
    }

    const digits = whole.replace(/\./g, '').replace(/^0+(?=\d)/, '');

    return { ok: true, value: (negative ? '-' : '') + digits + (fraction === undefined || fraction === '' ? '' : `.${fraction}`) };
}
