// The local stack's shell fixture (routes/fixtures.php; TASK-016): the application shell with a persona's hints and
// every entry's page counted as built, and a command form whose session the test ends — so the browser suite
// proves the navigation paths on desktop and phone, the re-authentication in place keeping the unsent input and
// the pending `command_id` (IP-11; HO-02), and the step-up (IP-10), against a stub of the sign-in. Not shipped in a
// production build (TL-13).
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import AppShell from '@/layouts/AppShell';
import { useCommandIntent } from '@/lib/use-command-intent';
import { useTranslation } from '@/lib/i18n';
import { postAnswer, type ReauthGateway } from '@/lib/session-guard';

const BUILT = new Set([
    'PG-006', 'PG-007', 'PG-008', 'PG-010', 'PG-011', 'PG-013', 'PG-015', 'PG-017', 'PG-018', 'PG-019', 'PG-021', 'PG-035', 'PG-038',
    'PG-041', 'PG-043', 'PG-046', 'PG-051', 'PG-054', 'PG-055', 'PG-056', 'PG-059', 'PG-063', 'PG-065', 'ROUTE-108',
]);

type Outcome = { command_id: string; replayed: boolean; executed: number } | null;

export default function ShellFixture({ run, outcome }: { run: string; outcome: Outcome }) {
    const { t } = useTranslation();
    const page = usePage();
    const query = new URL(page.url, window.location.origin).search;
    const [note, setNote] = useState('');
    const intent = useCommandIntent<{ note: string }>();

    const gateway: ReauthGateway = {
        signIn: (credentials) => postAnswer(`/__fixtures/shell/masuk${query}`, credentials),
        confirmPassword: (password) => postAnswer(`/__fixtures/shell/konfirmasi${query}`, { password }),
    };

    const post = (path: 'perintah' | 'akses', sent: { commandId: string; payload: { note: string } }) => {
        router.post(`/__fixtures/shell/${path}${query}`, { command_id: sent.commandId, ...sent.payload }, {
            preserveState: true,
            onSuccess: () => {
                intent.outcome('COMMITTED');
            },
            // The answer lost on the way: the outcome is unknown, and "Coba Lagi" sends the same identifier (HO-02).
            onNetworkError: () => {
                intent.failed();

                return false;
            },
        });
    };
    const send = (path: 'perintah' | 'akses') => {
        const sent = intent.trigger({ note });

        if (sent !== null) {
            post(path, sent);
        }
    };

    return (
        <AppShell title={t('pages.pg_041.title')} built={BUILT} gateway={gateway} back={{ href: '/keuangan/invoice', label: t('shell.back') }}>
            <PageHeader title={t('pages.pg_041.title')} />
            <div data-run={run} className="flex max-w-page-form flex-col gap-4">
                <Field>
                    <FieldLabel htmlFor="catatan">{t('glossary.gl_166')}</FieldLabel>
                    <Input
                        id="catatan"
                        value={note}
                        onChange={(event) => {
                            setNote(event.target.value);
                        }}
                    />
                </Field>
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="primary"
                        size="lg"
                        onClick={() => {
                            send('perintah');
                        }}
                    >
                        {t('actions.record_payment')}
                    </Button>
                    <Button
                        variant="outline"
                        size="lg"
                        onClick={() => {
                            send('akses');
                        }}
                    >
                        {t('actions.create_credential_link')}
                    </Button>
                    <Button
                        variant="ghost"
                        size="lg"
                        onClick={() => {
                            void fetch(`/__fixtures/shell/kedaluwarsa${query}`, { method: 'POST' });
                        }}
                    >
                        {t('outcome.session_ended')}
                    </Button>
                </div>
                {intent.state.phase === 'unknown' && (
                    <div role="alert" className="flex flex-wrap items-center gap-2 text-body text-text-primary">
                        {t('messages.msg_07')}
                        <Button
                            variant="outline"
                            size="lg"
                            onClick={() => {
                                const again = intent.retry();

                                if (again !== null) {
                                    post('perintah', again);
                                }
                            }}
                        >
                            {t('actions.try_again')}
                        </Button>
                    </div>
                )}
                {outcome !== null && (
                    <p role="status" data-command-id={outcome.command_id} data-replayed={String(outcome.replayed)} data-executed={outcome.executed} className="text-body text-text-primary">
                        {t('outcome.committed')}
                    </p>
                )}
                <p data-pending-command={intent.state.commandId ?? ''} className="sr-only" />
            </div>
        </AppShell>
    );
}
