// The BAST's live paper (DOC-08; DESIGN_SYSTEM §14.3; INT-012): the draft's paper from the page's props, the recipient,
// date and lines filled in from the form or a recorded handover, marked "DRAF — belum terbit" by its template. Nothing is
// written, and the paper stays Indonesian in either interface language (LOCALIZATION §3.4).
import { type Paper } from '@/features/doc/paper';
import { dateLong, quantity } from '@/lib/format';

export type BastLine = { description: string; quantity: string | null; unit: string; note: string | null };

export function liveBast(paper: Paper, recipient: string, date: string, lines: BastLine[]): Paper {
    const references = Array.isArray(paper.references) ? (paper.references as { label: string; value: string }[]) : [];
    const shown = lines.map((line, index) => ({
        no: index + 1,
        description: line.description,
        quantity: line.quantity === null ? null : quantity(line.quantity, '').trim(),
        unit: line.unit,
        unit_price: null,
        amount: null,
        note: line.note,
    }));

    return {
        ...paper,
        date: dateLong(date),
        recipient: { ...(paper.recipient as Record<string, unknown>), name: recipient.trim() === '' ? '—' : recipient },
        references: [...references, { label: 'Tanggal Serah Terima', value: dateLong(date) }],
        lines: shown,
        has_lines: shown.length > 0,
        priced: false,
    };
}
