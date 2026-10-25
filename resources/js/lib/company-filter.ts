import { router, usePage } from '@inertiajs/react';

import type { AccountHints } from './navigation';

// The working filter "Tampilkan: Semua perusahaan" (INFORMATION_ARCHITECTURE §7; PT-04; INT-007; TASK-016): the
// whitelisted address key `perusahaan` — the public identifiers of the companies shown —, set by a visit that keeps
// the page's state, so reload and Back keep it and nothing stores it (DP-02; D-IA-02). The server intersects it with
// the account's scope (`CompanyView`); the control appears only for an account with two or more companies.

export const COMPANY_KEY = 'perusahaan';

export function useCompanyFilter(): { companies: AccountHints['companies']; selected: string[]; shown: boolean; change: (codes: string[]) => void } {
    const page = usePage<{ auth: AccountHints }>();
    const companies = page.props.auth.companies;
    const url = new URL(page.url, window.location.origin);
    const ids = (url.searchParams.get(COMPANY_KEY) ?? '').split(',').filter((id) => id !== '');
    const selected = companies.filter((company) => ids.includes(company.id)).map((company) => company.code);

    return {
        companies,
        selected,
        shown: companies.length >= 2,
        change: (codes) => {
            const next = new URL(url);
            const chosen = companies.filter((company) => codes.includes(company.code)).map((company) => company.id);

            if (chosen.length === 0 || chosen.length === companies.length) {
                next.searchParams.delete(COMPANY_KEY);
            } else {
                next.searchParams.set(COMPANY_KEY, chosen.join(','));
            }

            router.get(next.pathname + next.search, {}, { preserveState: true, preserveScroll: true });
        },
    };
}
