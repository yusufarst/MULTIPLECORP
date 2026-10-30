// The invoice's live paper (PG-042; DESIGN_SYSTEM §14.3; INT-012): the draft's paper from the page's props with what the composer holds now — its date, lines, and the tax proposed from the
// rate in force, or the tax document's own amount where the actor stated it. Totals are labelled *perkiraan* until saved; nothing is written, and the paper stays Indonesian (LOCALIZATION §3.4).
import { type Paper } from '@/features/doc/paper';
import { add, multiply, quotient, round } from '@/lib/decimal';
import { dateLong, money, percent, quantity } from '@/lib/format';

export type InvoiceTax = { code: string; rate: string; numerator: string; denominator: string; rounding: string; evidence_required: boolean };
export type InvoiceState = 'DRAFT' | 'VOIDED' | 'ISSUED_NOT_BILLED' | 'BILLED_OUTSTANDING' | 'PAST_DUE_DATE' | 'OUTSTANDING_ZERO' | 'DISPUTE_HOLD' | 'WRITTEN_OFF';
export type PaperLine = { description: string; quantity: string | null; unit: string | null; price: string | null; value: string | null };

/** The proposal the server also makes: `base × rate% × numerator ÷ denominator`, by the configured mode. */
export function proposedTax(base: string, tax: InvoiceTax): string {
    return quotient(multiply(multiply(base, tax.rate), tax.numerator), multiply(tax.denominator, '100'), 2, tax.rounding === 'DOWN' ? 'DOWN' : 'HALF_UP');
}

export function liveInvoice(paper: Paper, lines: PaperLine[], taxes: { code: string; rate: string; amount: string }[], date: string): Paper {
    const sales = add('0', ...lines.map((line) => line.value ?? '0'));
    const taxTotal = add('0', ...taxes.map((tax) => tax.amount));
    const shown = lines.map((line, index) => ({
        no: index + 1,
        description: line.description,
        quantity: line.quantity === null ? null : quantity(line.quantity, '').trim(),
        unit: line.unit ?? '',
        unit_price: line.price === null ? null : money(round(line.price, 2)),
        amount: line.value === null ? null : money(round(line.value, 2)),
        note: null,
    }));

    return {
        ...paper,
        date: dateLong(date),
        lines: shown,
        has_lines: shown.length > 0,
        priced: shown.length > 0,
        provisional: true,
        totals: [
            { label: 'Subtotal', value: money(round(sales, 2)), grand: false },
            ...taxes.map((tax) => ({ label: `${tax.code} ${percent(tax.rate)}`, value: money(round(tax.amount, 2)), grand: false })),
            { label: 'Total', value: money(round(add(sales, taxTotal), 2)), grand: true },
        ],
        has_totals: true,
    };
}
