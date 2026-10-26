import { http, router, usePage } from '@inertiajs/react';
import { type ReactNode, useEffect, useRef, useState } from 'react';

import { IdleWarning, ReauthDialog, StepUpDialog, UnsentInputDialog } from '@/components/session-dialogs';
import { useTranslation } from '@/lib/i18n';
import type { AccountHints } from '@/lib/navigation';

import {
    BACKGROUND_HEADER,
    DEADLINE_HEADER,
    IDLE_MINUTES,
    IDLE_WARNING_MINUTES,
    type Interruption,
    interruption,
    isBackground,
    minutesUntil,
    type Replay,
    replayOf,
    sameAccount,
    sessionOver,
    type VisitLike,
} from './session-core';
import { receiveDeadline, sessionDeadline } from './session-deadline';

// The shell's session guard (ADMIN_FLOW IP-10, IP-11; UXH-11; INT-010; TASK-016): a 401 or 419 before the absolute
// limit clears the history at once and opens the re-authentication over an opaque layer, and a 423 the step-up —
// the page stays mounted, so its unsent input, its `lock_version` and a pending `command_id` stay in memory and
// nowhere else —. Once signed in, the new session's account is read before anything is uncovered or resent: the
// covered page's account gets the interrupted visit sent again unchanged, so a command replays rather than runs
// twice (HO-02); any other account gets the full document load (UXH-02). The absolute limit, "Keluar" from the
// dialog and a logout end with a full document load of sign-in, history cleared (H7-12). Background visits are
// marked (RB-05), and the idle warning counts from the last visit the user started (AU-07; MSG-33).

export type Credentials = { email: string; password: string };

/** A sign-in's or confirmation's answer: accepted, refused (MSG-19), or throttled with MSG-20 (AU-05; WS-12). */
export type GatewayAnswer =
    | { outcome: 'ok' }
    | { outcome: 'failed' }
    | { outcome: 'limited'; message: string | null; minutes: number }
    // TASK-018: the password accepted with the code step next; an enrolment-only session; an attempt that expired.
    | { outcome: 'two_factor' }
    | { outcome: 'enrolment'; location: string }
    | { outcome: 'expired' };

/** The sign-in behind the in-place dialog: ROUTE-101 and its second factor, TASK-017's. */
export type ReauthGateway = {
    signIn: (credentials: Credentials) => Promise<GatewayAnswer>;
    confirmPassword: (password: string) => Promise<GatewayAnswer>;
    /** AU-16's code step after the password, a TOTP code or a recovery code (ROUTE-104, ROUTE-105). */
    verifyCode?: (code: string, recovery: boolean) => Promise<GatewayAnswer>;
};

/** A JSON POST of the dialog, its answer read in place. */
export async function postAnswer(url: string, body: unknown): Promise<GatewayAnswer> {
    try {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });

        if (response.status === 429) {
            const answer = (await response.json().catch(() => ({}))) as { message?: unknown };
            const retry = Number(response.headers.get('Retry-After') ?? '60');

            return { outcome: 'limited', message: typeof answer.message === 'string' ? answer.message : null, minutes: Math.max(1, Math.ceil((Number.isNaN(retry) ? 60 : retry) / 60)) };
        }

        const reply = (await response.json().catch(() => ({}))) as { two_factor?: unknown; enrolment_only?: unknown; location?: unknown; expired?: unknown };

        if (!response.ok) {
            return { outcome: reply.expired === true ? 'expired' : 'failed' };
        }

        if (reply.two_factor === true) {
            return { outcome: 'two_factor' };
        }

        return reply.enrolment_only === true && typeof reply.location === 'string' ? { outcome: 'enrolment', location: reply.location } : { outcome: 'ok' };
    } catch {
        return { outcome: 'failed' };
    }
}

/** The application's gateway: ROUTE-101's sign-in and AU-15's password confirmation, answered by TASK-017. */
export const applicationGateway: ReauthGateway = {
    signIn: (credentials) => postAnswer('/masuk', credentials),
    confirmPassword: (password) => postAnswer('/konfirmasi-kata-sandi', { password }),
    verifyCode: (code, recovery) => postAnswer(recovery ? '/masuk/verifikasi/pemulihan' : '/masuk/verifikasi', recovery ? { recovery_code: code } : { code }),
};

/** The new session's account hints for the open address — a background partial read of `auth` that sends
    nothing of the page and changes nothing on screen. */
async function sessionAccount(page: { version: string | null; component: string }): Promise<AccountHints | null> {
    try {
        const response = await fetch(window.location.href, {
            credentials: 'same-origin',
            headers: {
                Accept: 'text/html, application/xhtml+xml',
                'X-Inertia': 'true',
                'X-Inertia-Partial-Component': page.component,
                'X-Inertia-Partial-Data': 'auth',
                'X-Requested-With': 'XMLHttpRequest',
                [BACKGROUND_HEADER]: '1',
                ...(page.version === null ? {} : { 'X-Inertia-Version': page.version }),
            },
        });
        const answer = response.headers.get('X-Inertia') === null ? null : ((await response.json()) as { props?: { auth?: AccountHints } });

        return answer?.props?.auth ?? null;
    } catch {
        return null;
    }
}

/** The full document load of sign-in, nothing of the page kept in memory or history (IP-11; H7-12). */
function leaveToSignIn(): void {
    router.clearHistory();
    window.location.assign('/masuk');
}

export function SessionGuard({ gateway = applicationGateway, children }: { gateway?: ReauthGateway; children: ReactNode }) {
    const { t, tc } = useTranslation();
    const page = usePage<{ auth: AccountHints }>();
    const account = page.props.auth;
    const [open, setOpen] = useState<Interruption | null>(null);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [leaving, setLeaving] = useState(false);
    const [codeStep, setCodeStep] = useState(false);
    const [enrolment, setEnrolment] = useState<string | null>(null);
    const [idleOpen, setIdleOpen] = useState(false);
    const [idleMinutes, setIdleMinutes] = useState(IDLE_WARNING_MINUTES);
    const current = useRef<VisitLike | null>(null);
    const replay = useRef<Replay | null>(null);
    const covered = useRef<AccountHints | null>(null);
    const lastActivity = useRef(0);
    const accountRef = useRef(account);
    const pageRef = useRef({ version: page.version, component: page.component });

    useEffect(() => {
        accountRef.current = account;
        pageRef.current = { version: page.version, component: page.component };
    });

    useEffect(() => {
        lastActivity.current = Date.now();
    }, []);

    useEffect(() => {
        const stops = [
            router.on('before', (event) => {
                const visit = event.detail.visit as unknown as VisitLike;

                if (isBackground(visit)) {
                    visit.headers[BACKGROUND_HEADER] = '1';
                }
            }),
            router.on('start', (event) => {
                const visit = event.detail.visit as unknown as VisitLike;

                if (!isBackground(visit)) {
                    current.current = visit;
                    lastActivity.current = Date.now();
                }
            }),
            // A visit that has finished is never sent again: a later 401 — the idle check's — replays nothing.
            router.on('finish', (event) => {
                if (current.current !== null && (event.detail.visit as unknown as VisitLike).id === current.current.id) {
                    current.current = null;
                }
            }),
            router.on('httpException', (event) => {
                const kind = interruption(event.detail.response.status);

                if (kind === null) {
                    return;
                }

                if (kind === 'reauth') {
                    if (sessionOver(sessionDeadline(), Date.now())) {
                        leaveToSignIn();

                        return false;
                    }

                    router.clearHistory();
                }

                replay.current = current.current === null ? null : replayOf(current.current);
                covered.current = accountRef.current;
                setError(null);
                setOpen(kind);

                return false;
            }),
            http.onResponse((response) => {
                const deadline = response.headers[DEADLINE_HEADER];

                if (typeof deadline === 'string') {
                    receiveDeadline(deadline);
                }

                return response;
            }),
        ];

        return () => {
            stops.forEach((stop) => {
                stop();
            });
        };
    }, []);

    // IP-11: the idle warning five minutes before the limit, counted from the last visit the user started; the
    // absolute limit, which nothing extends, ends the session when it passes.
    useEffect(() => {
        const timer = window.setInterval(() => {
            if (sessionOver(sessionDeadline(), Date.now())) {
                leaveToSignIn();

                return;
            }

            const left = minutesUntil(lastActivity.current + IDLE_MINUTES * 60_000, Date.now());

            if (left <= 0) {
                setIdleOpen(false);
                // A background check that extends nothing: another tab may have kept the session alive (RV-06).
                router.reload({ async: true, only: ['auth'], onSuccess: () => (lastActivity.current = Date.now()) });
            } else if (left <= IDLE_WARNING_MINUTES) {
                setIdleMinutes(left);
                setIdleOpen(true);
            }
        }, 30_000);

        return () => {
            window.clearInterval(timer);
        };
    }, []);

    const resend = () => {
        const visit = replay.current;
        replay.current = null;
        setOpen(null);

        if (visit === null) {
            router.reload();
        } else {
            router.visit(visit.url, { ...visit.options, ...visit.callbacks } as Parameters<typeof router.visit>[1]);
        }
    };

    const failure = (answer: GatewayAnswer): string =>
        answer.outcome === 'limited' ? (answer.message ?? tc('messages.msg_20', answer.minutes)) : t('messages.msg_19');

    // After the password, or after the code: the same account resumes; an enrolment-only session cannot (AU-18).
    const signedInAs = async (answer: GatewayAnswer, codeAnswer: boolean) => {
        if (answer.outcome === 'two_factor') {
            setPending(false);
            setError(null);
            setCodeStep(true);

            return;
        }

        if (answer.outcome === 'enrolment') {
            setPending(false);
            setEnrolment(answer.location);

            return;
        }

        if (answer.outcome === 'expired') {
            setPending(false);
            setCodeStep(false);
            setError(t('auth.code_mismatch'));

            return;
        }

        if (answer.outcome !== 'ok') {
            setPending(false);
            setError(answer.outcome === 'failed' && codeAnswer ? t('auth.code_mismatch') : failure(answer));

            return;
        }

        setCodeStep(false);
        // UXH-02: the covered page's account, or the full document load before anything is uncovered or resent.
        const signedIn = await sessionAccount(pageRef.current);

        if (signedIn === null || covered.current === null || !sameAccount(covered.current, signedIn)) {
            router.clearHistory();
            window.location.reload();

            return;
        }

        setPending(false);
        router.clearHistory();
        resend();
    };

    const signIn = async (credentials: Credentials) => {
        setPending(true);
        await signedInAs(await gateway.signIn(credentials), false);
    };

    const verifyCode = async (code: string, recovery: boolean) => {
        const verify = gateway.verifyCode ?? applicationGateway.verifyCode;

        if (verify === undefined) {
            return;
        }

        setPending(true);
        await signedInAs(await verify(code, recovery), true);
    };

    const confirm = async (password: string) => {
        setPending(true);
        const answer = await gateway.confirmPassword(password);
        setPending(false);

        if (answer.outcome === 'ok') {
            resend();
        } else {
            setError(failure(answer));
        }
    };

    return (
        <>
            {children}
            <ReauthDialog
                open={open === 'reauth'}
                email=""
                error={error}
                pending={pending}
                codeStep={codeStep}
                onSubmit={(credentials) => {
                    void signIn(credentials);
                }}
                onCode={(code, recovery) => {
                    void verifyCode(code, recovery);
                }}
                onSignOut={() => {
                    setLeaving(true);
                }}
            />
            <UnsentInputDialog
                open={enrolment !== null}
                kind="leave"
                onStay={() => {
                    setEnrolment(null);
                }}
                onLeave={() => {
                    router.clearHistory();
                    window.location.assign(enrolment ?? '/verifikasi-dua-langkah');
                }}
            />
            <UnsentInputDialog
                open={leaving}
                kind="logout"
                onStay={() => {
                    setLeaving(false);
                }}
                onLeave={leaveToSignIn}
            />
            <StepUpDialog
                open={open === 'step_up'}
                account={account.name ?? ''}
                error={error}
                pending={pending}
                onConfirm={(password) => {
                    void confirm(password);
                }}
                onCancel={() => {
                    replay.current = null;
                    setOpen(null);
                }}
            />
            <IdleWarning
                open={idleOpen && open === null}
                minutes={idleMinutes}
                onStay={() => {
                    setIdleOpen(false);
                    // One user-initiated request, which extends the session (IP-11): a foreground visit, since the
                    // router's reload is always async and so a background request that extends nothing (RB-05).
                    router.visit(window.location.href, { only: ['auth'], preserveState: true, preserveScroll: true, replace: true });
                }}
            />
        </>
    );
}
