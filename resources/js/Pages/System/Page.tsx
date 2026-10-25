// PG-005 *Halaman Sistem* (ROUTE-005; NAV-005; ADMIN_FLOW IP-21; TASK-016): not found — identical for an unknown,
// an out-of-scope and an Owner-only address (AZ-11) —, forbidden for a record in scope without the capability
// (MSG-10), or an error with its reference code and no technical detail (WS-10); nothing of the record. Inside the
// shell for a signed-in account; "Kembali" returns to the application's previous page, else to the landing page.
import { Head, router, usePage } from '@inertiajs/react';

import { SystemPage } from '@/components/region-state';
import { Button } from '@/components/ui/button';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';
import { type AccountHints, BUILT, isOwner } from '@/lib/navigation';
import { previousPage } from '@/lib/previous-page';
import { documentTitle } from '@/lib/title';

type Kind = 'not_found' | 'forbidden' | 'error';

const MESSAGES = { not_found: 'messages.msg_09', forbidden: 'messages.msg_10', error: 'pages.pg_005.error' } as const;

function back(account: AccountHints): void {
    const previous = previousPage();

    if (previous !== null) {
        router.visit(previous);

        return;
    }

    const landing = isOwner(account) && BUILT.has('PG-006') ? '/beranda' : BUILT.has('PG-007') ? '/pekerjaan' : '/';
    window.location.assign(landing);
}

export default function SystemPageView({ kind, reference }: { kind: Kind; reference: string }) {
    const { t } = useTranslation();
    const { auth } = usePage<{ auth: AccountHints }>().props;
    const body = (
        <SystemPage
            kind={kind}
            message={t(MESSAGES[kind])}
            reference={reference}
            back={
                <Button
                    variant="outline"
                    size="lg"
                    onClick={() => {
                        back(auth);
                    }}
                >
                    {t('shell.back')}
                </Button>
            }
        />
    );

    if (auth.name === null) {
        return (
            <main id="konten" className="min-h-dvh bg-canvas px-m-gutter md:px-gutter">
                <Head title={documentTitle(t('pages.pg_005.title'))} />
                {body}
            </main>
        );
    }

    return <AppShell title={t('pages.pg_005.title')}>{body}</AppShell>;
}
