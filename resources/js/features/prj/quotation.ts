// The quotation's shared reading (WF-QUO-01; L-25; INT-012; TASK-033): a revision's shown state — *Kedaluwarsa* derived
// from its validity, never stored — and the composer's live paper, each line's value and its output taxes computed on
// the entered strings as the save computes them, so the preview never shows a figure the server will not write.
import { type Paper } from '@/features/doc/paper';
import { add, multiply, round } from '@/lib/decimal';
import { dateLong, money, quantity } from '@/lib/format';

export type RevisionState = 'DRAFT' | 'SENT' | 'APPROVED' | 'REJECTED' | 'EXPIRED';
export type TaxRule = { type: string; rate: string; numerator: string; denominator: string; rounding: string };
export type PreviewLine = { description: string; quantity: string | null; unit: string; price: string | null };

/** The status family's state for a shown revision state: *Kedaluwarsa* is a sent revision past its validity. */
export function revisionState(state: RevisionState): 'DRAFT' | 'SENT' | 'APPROVED' | 'REJECTED' | 'SENT_PAST_VALIDITY' {
    return state === 'EXPIRED' ? 'SENT_PAST_VALIDITY' : state;
}

/** A line's value: quantity × price, half away from zero to sen. */
export function lineValue(line: PreviewLine): string | null {
    return line.quantity === null || line.price === null ? null : round(multiply(line.quantity, line.price), 2);
}

/** value × numerator / denominator × rate / 100 to sen, rounded half away from zero or cut ("DOWN"), as PostgreSQL's numeric does. */
export function taxOf(value: string, rule: TaxRule): string {
    const product = multiply(multiply(value, rule.numerator), rule.rate);
    const [whole = '0', fraction = ''] = product.replace('-', '').split('.');
    const units = BigInt(`${whole}${fraction}`);
    // The result in sen: product / (denominator × 100) × 100.
    const divisor = 10n ** BigInt(fraction.length) * BigInt(rule.denominator.split('.')[0] ?? '1');
    const sen = rule.rounding === 'DOWN' ? units / divisor : (units + divisor / 2n) / divisor;
    const digits = sen.toString().padStart(3, '0');

    return `${product.startsWith('-') && sen > 0n ? '-' : ''}${digits.slice(0, -2)}.${digits.slice(-2)}`;
}

function percent(rate: string): string {
    return `${rate.includes('.') ? rate.replace(/0+$/, '').replace(/\.$/, '') : rate}%`;
}

/**
 * The draft's paper with the lines, the validity and the totals as entered: the server's paper for everything else —
 * the issuer, the recipient, the reference to the project —, never a number (DC-2).
 */
export function livePaper(paper: Paper, lines: PreviewLine[], taxes: TaxRule[], validUntil: string | null, date: string): Paper {
    const values = lines.map(lineValue);
    const subtotal = add('0', ...values.map((value) => value ?? '0'));
    const taxTotals = taxes.map((rule) => ({ rule, amount: add('0', ...values.map((value) => (value === null ? '0' : taxOf(value, rule)))) }));
    const total = add(subtotal, ...taxTotals.map((tax) => tax.amount));
    const references = (Array.isArray(paper.references) ? (paper.references as { label: string; value: string }[]) : []).filter((reference) => reference.label !== 'Berlaku sampai');
    const shownLines = lines.map((line, index) => ({
        no: index + 1,
        description: line.description,
        quantity: line.quantity === null ? null : quantity(line.quantity, '').trim(),
        unit: line.unit,
        unit_price: line.price === null ? null : money(round(line.price, 2)),
        amount: values[index] === null || values[index] === undefined ? null : money(values[index]),
        note: null,
    }));

    return {
        ...paper,
        date: dateLong(date),
        references: validUntil === null ? references : [...references, { label: 'Berlaku sampai', value: dateLong(validUntil) }],
        lines: shownLines,
        has_lines: shownLines.length > 0,
        priced: shownLines.some((line) => line.amount !== null),
        totals: [
            { label: 'Subtotal', value: money(round(subtotal, 2)), grand: false },
            ...(values.some((value) => value !== null) ? taxTotals : []).map((tax) => ({ label: `${tax.rule.type} ${percent(tax.rule.rate)}`, value: money(round(tax.amount, 2)), grand: false })),
            { label: 'Total', value: money(round(total, 2)), grand: true },
        ],
        has_totals: true,
    };
}
