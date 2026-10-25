// The keyboard-wedge scanner (ADMIN_FLOW IP-15; DESIGN_SYSTEM §13.2): a burst of printable characters arriving
// faster than typing and ending with Enter is one scan. The detector is pure — it sees keys and their times —
// so the page decides what a scan means where it lands: an exact lookup in a field that accepts scans, discarded
// with its Enter anywhere else, never pressing a button or sending a command.
export const BURST_GAP_MS = 35;
export const BURST_MIN_LENGTH = 4;

export type Burst = { code: string; started: number };

export class BurstDetector {
    private buffer = '';
    private last = Number.NEGATIVE_INFINITY;
    private started = 0;

    /** Feeds one key; returns the scan when an Enter closes a burst, otherwise null. */
    feed(key: string, at: number): Burst | null {
        const continues = at - this.last <= BURST_GAP_MS;

        if (key === 'Enter') {
            const burst = continues && this.buffer.length >= BURST_MIN_LENGTH ? { code: this.buffer, started: this.started } : null;
            this.reset();

            return burst;
        }

        if (key.length !== 1) {
            this.reset();

            return null;
        }

        if (!continues) {
            this.buffer = '';
            this.started = at;
        }

        this.buffer += key;
        this.last = at;

        return null;
    }

    /** True when a key at `at` would continue the keys so far rather than start afresh. */
    continues(at: number): boolean {
        return this.buffer !== '' && at - this.last <= BURST_GAP_MS;
    }

    reset(): void {
        this.buffer = '';
        this.last = Number.NEGATIVE_INFINITY;
    }
}

/** The element accepts scans: the scan field (PT-20), *Cari* and a line's product picker (PT-27). */
export const SCAN_TARGET = 'data-scan-target';

export function acceptsScans(element: Element | null): boolean {
    return element?.closest(`[${SCAN_TARGET}]`) !== null && element !== null;
}
