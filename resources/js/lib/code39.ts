// The label's barcode symbol (TASK-027; ROUTE-147; DESIGN_SYSTEM §17; DEP-03): Code 39, which the office's USB scanners
// read as a keyboard wedge without configuration, drawn in the browser — "Cetak Label" writes nothing. Each character is
// nine elements, bar and space alternating from a bar, three of them wide; characters are separated by one narrow
// space and the code framed by the start and stop character `*`. Only the characters an internal code and an
// upper-cased manufacturer code use are encoded: digits, capital letters, hyphen, dot and space.

const PATTERNS: Readonly<Record<string, string>> = {
    '0': '000110100', '1': '100100001', '2': '001100001', '3': '101100000', '4': '000110001',
    '5': '100110000', '6': '001110000', '7': '000100101', '8': '100100100', '9': '001100100',
    A: '100001001', B: '001001001', C: '101001000', D: '000011001', E: '100011000', F: '001011000',
    G: '000001101', H: '100001100', I: '001001100', J: '000011100', K: '100000011', L: '001000011',
    M: '101000010', N: '000010011', O: '100010010', P: '001010010', Q: '000000111', R: '100000110',
    S: '001000110', T: '000010110', U: '110000001', V: '011000001', W: '111000000', X: '010010001',
    Y: '110010000', Z: '011010000', '-': '010000101', '.': '110000100', ' ': '011000100', '*': '010010100',
};

/** A wide element's width in narrow units. */
export const WIDE = 3;

/** Whether the code can be drawn: only the characters Code 39 encodes here. */
export function encodable(code: string): boolean {
    return code.length > 0 && Array.from(code).every((character) => character !== '*' && PATTERNS[character] !== undefined);
}

/** The bars of the code framed by `*`, each as its start and width in narrow units; the total width beside them. */
export function bars(code: string): { bars: { x: number; width: number }[]; width: number } {
    if (!encodable(code)) throw new Error('Code 39 encodes digits, capital letters, hyphen, dot and space only.');

    const drawn: { x: number; width: number }[] = [];
    let x = 0;

    Array.from(`*${code}*`).forEach((character, index) => {
        if (index > 0) x += 1;

        Array.from(PATTERNS[character] ?? '').forEach((element, position) => {
            const width = element === '1' ? WIDE : 1;

            if (position % 2 === 0) drawn.push({ x, width });
            x += width;
        });
    });

    return { bars: drawn, width: x };
}
