import { useCallback, useEffect, useRef, useState } from 'react';

import { BACKGROUND_HEADER } from './session-core';

// ROUTE-285's poll (INT-285; ADMIN_FLOW IP-12): the state of a deferred group — a rendition here; an export or an
// import batch with their Tasks — read as a background request, which never extends the session (RB-05), no faster
// than once a minute while the work is owed, and on the user's own refresh. It stops once the state is settled.

export type PolledStatus = { state: string; attempt: number; attempts: number; exhausted: boolean; file: string | null };

const MINUTE = 60_000;

function settled(status: PolledStatus | null): boolean {
    return status !== null && (status.state === 'READY' || status.exhausted);
}

export function useStatusPoll(kind: string, id: string, interval: number = MINUTE) {
    const [status, setStatus] = useState<PolledStatus | null>(null);
    const [failed, setFailed] = useState(false);
    const latest = useRef<PolledStatus | null>(null);

    const read = useCallback(async () => {
        try {
            const response = await fetch(`/status/${encodeURIComponent(kind)}/${encodeURIComponent(id)}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', [BACKGROUND_HEADER]: '1' },
            });

            if (!response.ok) {
                setFailed(true);

                return;
            }

            const answer = (await response.json()) as PolledStatus;
            latest.current = answer;
            setStatus(answer);
            setFailed(false);
        } catch {
            setFailed(true);
        }
    }, [kind, id]);

    useEffect(() => {
        // The first read as soon as the page has settled, then each interval while the work is owed.
        const first = window.setTimeout(() => {
            void read();
        }, 0);
        const timer = window.setInterval(() => {
            if (!settled(latest.current)) {
                void read();
            }
        }, Math.max(interval, MINUTE));

        return () => {
            window.clearTimeout(first);
            window.clearInterval(timer);
        };
    }, [read, interval]);

    return { status, failed, refresh: read };
}
