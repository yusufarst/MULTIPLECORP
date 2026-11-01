// NAV-019: PG-019's "Kembali" returns to PG-018 at the address it came from, its filters and tab kept; this tab's memory only, the plain address without it (NV-05).
const KEY = 'stock.list.address';
const BASE = '/gudang/stok';

export function rememberStockList(address: string): void {
    try {
        window.sessionStorage.setItem(KEY, address);
    } catch {
        // No storage in this window: "Kembali" opens the plain list.
    }
}

export function stockListAddress(): string {
    try {
        const address = window.sessionStorage.getItem(KEY);

        return address !== null && (address === BASE || address.startsWith(`${BASE}?`)) ? address : BASE;
    } catch {
        return BASE;
    }
}
