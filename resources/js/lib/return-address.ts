// IP-16's return address for evidence registered from a form (TASK-096 A; NV-04): the page's own keys only, each once as the
// return list requires, and the keys the page needs back. Any other key — PG-017's lines' products, one per line, or a key
// from elsewhere — could grow it past the return list's bound (2,048; EvidenceController) or fail its key rule, and a refused
// address answers on PG-038 with the form lost. Pure, so the address is held to its size by a unit test.
export function returnAddress(path: string, search: string, keep: readonly string[], add: Readonly<Record<string, string>>): string {
    const kept = Object.fromEntries([...new URLSearchParams(search)].filter(([key]) => keep.includes(key)));
    const query = new URLSearchParams({ ...kept, ...add }).toString();

    return query === '' ? path : `${path}?${query}`;
}
