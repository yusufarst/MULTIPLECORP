// ROUTE-282's exact lookup from a page (INT-006; IP-15; QB-06): read-only, with the scanner's own limiter; null when the answer is lost.
export type ScanMatch = { kind: 'product' | 'serial' | 'lot'; product: string; name: string; sku?: string | null; serial?: string; in_stock?: boolean; lot?: string };
export type Scan = { kind: 'match' | 'unknown' | 'ambiguous'; code: string; matches: ScanMatch[]; register: boolean };

/** The lookup's outcome with the scanner limiter's refusal kept apart (WS-12; MSG-20) and a request that can be withdrawn. */
export type ScanOutcome = { kind: 'answer'; scan: Scan } | { kind: 'limited'; message: string } | { kind: 'failed' };

export async function lookUpOutcome(code: string, signal?: AbortSignal): Promise<ScanOutcome> {
    try {
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        const response = await fetch('/gudang/pindai', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(token === undefined ? {} : { 'X-CSRF-TOKEN': token }) },
            body: JSON.stringify({ kode: code }),
            signal,
        });

        if (response.status === 429) {
            const body = (await response.json()) as { message?: string };

            return { kind: 'limited', message: body.message ?? '' };
        }

        return response.ok ? { kind: 'answer', scan: (await response.json()) as Scan } : { kind: 'failed' };
    } catch {
        return { kind: 'failed' };
    }
}

export async function lookUp(code: string): Promise<Scan | null> {
    const outcome = await lookUpOutcome(code);

    return outcome.kind === 'answer' ? outcome.scan : null;
}
