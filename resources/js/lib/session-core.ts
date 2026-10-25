// The session rules of the open page, pure (ADMIN_FLOW IP-10, IP-11; UXH-11; RB-05; RV-06; TASK-016): which
// answer opens which dialog, which visit is background, and how the interrupted visit is sent again — the same
// address, method, data and headers, so a command's `command_id` and payload travel unchanged and the server's
// idempotency answers a second attempt with the first one's outcome (CI-01–CI-09; HO-02).

/** The marker of a background request (RB-05); the server decides, a non-GET never is (`BackgroundRequest`). */
export const BACKGROUND_HEADER = 'X-Background-Request';

/** The absolute deadline the server sends outside the page data (AU-08; `SessionDeadline`). */
export const DEADLINE_HEADER = 'x-session-deadline';

/** AU-07's idle limit and the warning before it (IP-11; MSG-33). */
export const IDLE_MINUTES = 60;
export const IDLE_WARNING_MINUTES = 5;

/** MSG-34's notices before the absolute limit: fifteen and five minutes (IP-11). */
export const DEADLINE_NOTICES_MINUTES = [15, 5] as const;

export type Interruption = 'reauth' | 'step_up';

/** 401 and 419 — the session ended or its token expired — sign in again in place; 423 asks for the step-up. */
export function interruption(status: number): Interruption | null {
    if (status === 401 || status === 419) {
        return 'reauth';
    }

    return status === 423 ? 'step_up' : null;
}

export type VisitLike = {
    /** The router's identifier of the visit, the same in its start and finish events. */
    id?: string;
    url: URL | string;
    method: string;
    data: unknown;
    headers: Record<string, string>;
    async?: boolean;
    prefetch?: boolean;
    only?: string[];
    except?: string[];
    preserveScroll?: unknown;
    preserveState?: unknown;
    replace?: boolean;
};

/** A poll, a deferred or visibility-triggered prop and a prefetch are background — a GET only (RB-05). */
export function isBackground(visit: VisitLike): boolean {
    return visit.method.toLowerCase() === 'get' && (visit.async === true || visit.prefetch === true);
}

export type Replay = {
    url: string;
    options: { method: string; data: unknown; headers: Record<string, string>; only?: string[]; except?: string[]; preserveScroll: true; preserveState: true; replace?: boolean };
    /** The visit's own callbacks — its `onSuccess` reports the outcome to the form's intent. */
    callbacks: Record<string, (...args: never[]) => unknown>;
};

/** The interrupted visit again, unchanged — same data, so the same `command_id` (HO-02). */
export function replayOf(visit: VisitLike): Replay {
    return {
        url: typeof visit.url === 'string' ? visit.url : visit.url.toString(),
        options: {
            method: visit.method,
            data: visit.data,
            headers: Object.fromEntries(Object.entries(visit.headers).filter(([name]) => name !== BACKGROUND_HEADER)),
            ...(visit.only !== undefined && visit.only.length > 0 ? { only: visit.only } : {}),
            ...(visit.except !== undefined && visit.except.length > 0 ? { except: visit.except } : {}),
            preserveScroll: true,
            preserveState: true,
            ...(visit.replace === undefined ? {} : { replace: visit.replace }),
        },
        callbacks: Object.fromEntries(
            Object.entries(visit).filter((entry): entry is [string, (...args: never[]) => unknown] => /^on[A-Z]/.test(entry[0]) && typeof entry[1] === 'function'),
        ),
    };
}

/** The minutes left before an instant, rounded up, never below zero. */
export function minutesUntil(deadline: number, now: number): number {
    return Math.max(0, Math.ceil((deadline - now) / 60_000));
}

/** MSG-34 is due from fifteen minutes before the absolute limit until it passes (IP-11). */
export function deadlineNoticeDue(deadline: number | null, now: number): boolean {
    return deadline !== null && now < deadline && deadline - now <= DEADLINE_NOTICES_MINUTES[0] * 60_000;
}

/** The absolute limit has passed: the session ends with the full document load of sign-in, never in place. */
export function sessionOver(deadline: number | null, now: number): boolean {
    return deadline !== null && now >= deadline;
}

/** The account the re-authenticated session belongs to must be the covered page's (IP-11): same name,
    capabilities and companies, or the page is loaded again from the server. */
export function sameAccount(before: unknown, after: unknown): boolean {
    return JSON.stringify(before) === JSON.stringify(after);
}
