import type { SearchResult } from './navigation';

// The client of ROUTE-281 `Reporting Search` (INT-005, INT-281): the term in a JSON request body — never in the
// address, a page's props or history (DP-02; H7-10) —, the answer read in place. A 429 carries MSG-20 from WS-12's
// limiter; a 401 or 419 means the session ended, which the shell's guard answers on the next visit.

export type SearchAnswer = { exact: SearchResult[]; groups: { type: string; rows: SearchResult[] }[]; notice: 'cut' | 'short' | 'unknown_code' | null };

export type SearchOutcome = { kind: 'answer'; answer: SearchAnswer } | { kind: 'limited'; message: string } | { kind: 'failed' };

export async function search(term: string, types: string[] = [], signal?: AbortSignal): Promise<SearchOutcome> {
    try {
        const response = await fetch('/cari', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ q: term, ...(types.length > 0 ? { jenis: types } : {}) }),
            signal,
        });

        if (response.status === 429) {
            const body = (await response.json()) as { message?: string };

            return { kind: 'limited', message: body.message ?? '' };
        }

        return response.ok ? { kind: 'answer', answer: (await response.json()) as SearchAnswer } : { kind: 'failed' };
    } catch {
        return { kind: 'failed' };
    }
}
