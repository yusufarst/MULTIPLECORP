// ROUTE-282's exact lookup from a page (INT-006; IP-15; QB-06): read-only, with the scanner's own limiter; null when the answer is lost.
export type ScanMatch = { kind: 'product' | 'serial' | 'lot'; product: string; name: string; sku?: string | null; serial?: string; in_stock?: boolean; lot?: string };
export type Scan = { kind: 'match' | 'unknown' | 'ambiguous'; code: string; matches: ScanMatch[]; register: boolean };

export async function lookUp(code: string): Promise<Scan | null> {
    try {
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        const response = await fetch('/gudang/pindai', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(token === undefined ? {} : { 'X-CSRF-TOKEN': token }) },
            body: JSON.stringify({ kode: code }),
        });

        return response.ok ? (await response.json()) as Scan : null;
    } catch {
        return null;
    }
}
