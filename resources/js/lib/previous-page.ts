import { router } from '@inertiajs/react';

import { type AccountHints, BUILT, isOwner } from './navigation';

// The application page this tab showed before the current one (NAVIGATION_CONTRACTS NV-01, NAV-005; TASK-016):
// kept in memory from the router's own navigations, so PG-005's "Kembali" returns to the previous page of the
// application and never through browser history alone, which may lead to an unrelated origin; none — a first
// document — falls back to the landing page.

let current: string | null = null;
let previous: string | null = null;

export function trackPages(): void {
    router.on('navigate', (event) => {
        const url = event.detail.page.url;

        if (url !== current) {
            previous = current;
            current = url;
        }
    });
}

export function previousPage(): string | null {
    return previous;
}

/** The account's landing page: Beranda for the Owner once built, else *Pekerjaan Saya*, else the root (NV-01). */
export function landingPage(account: AccountHints): string {
    return isOwner(account) && BUILT.has('PG-006') ? '/beranda' : BUILT.has('PG-007') ? '/pekerjaan' : '/';
}

/** Where a page's "Kembali" returns: the application page shown before it, else the landing page (NV-01). A visit to
    the same address — a form's answer, a language switch — keeps the earlier one. */
export function returnTarget(account: AccountHints): string {
    return previous ?? landingPage(account);
}
