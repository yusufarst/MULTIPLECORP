// IP-02's one `command_id` per intent (ADMIN_FLOW §5; CONCURRENCY_IDEMPOTENCY CI-01, HO-02, HO-03, HO-07), as a
// pure reducer the presentation keeps per form: the identifier is minted when the user triggers the
// submission and bound to the exact payload sent; a trigger while an intent is in flight or unknown is
// dropped; COMMITTED, REJECTED and CONFLICT end the intent; an answer that is not an outcome keeps the
// identifier while the payload is unchanged; FAILED, a lost request or no answer leave it open with its inputs
// frozen, every retry sending the same identifier and payload. The mechanism is the server's (TASK-013).

export type Outcome = 'COMMITTED' | 'REJECTED' | 'CONFLICT' | 'PENDING';
export type NotAnOutcome = 'VALIDATION' | 'DENIED' | 'RATE_LIMITED' | 'REAUTH';

export type IntentState<P> =
    | { phase: 'idle'; commandId: null; payload: null; retry: false }
    | { phase: 'in_flight'; commandId: string; payload: P; retry: boolean }
    | { phase: 'unknown'; commandId: string; payload: P; retry: true }
    | { phase: 'answered'; commandId: string; payload: P; retry: false; answer: NotAnOutcome }
    | { phase: 'ended'; commandId: null; payload: null; retry: false; outcome: Outcome };

export type IntentEvent<P> =
    | { type: 'trigger'; payload: P; mint: () => string; same: (a: P, b: P) => boolean }
    | { type: 'outcome'; outcome: Outcome }
    | { type: 'not_an_outcome'; answer: NotAnOutcome }
    | { type: 'failed' }
    | { type: 'retry' }
    | { type: 'leave' };

export function idle<P>(): IntentState<P> {
    return { phase: 'idle', commandId: null, payload: null, retry: false };
}

export function reduceIntent<P>(state: IntentState<P>, event: IntentEvent<P>): IntentState<P> {
    switch (event.type) {
        case 'trigger': {
            // A second click, Enter, KB-09, KB-10 or the automatic retry while an intent is open: dropped.
            if (state.phase === 'in_flight' || state.phase === 'unknown') {
                return state;
            }

            // A first attempt answered but not decided: the identifier kept while the payload is unchanged.
            if (state.phase === 'answered' && event.same(state.payload, event.payload)) {
                return { phase: 'in_flight', commandId: state.commandId, payload: state.payload, retry: false };
            }

            return { phase: 'in_flight', commandId: event.mint(), payload: event.payload, retry: false };
        }
        case 'outcome':
            if (state.phase !== 'in_flight') {
                return state;
            }

            // PENDING follows a committed action: the intent ends as done (IP-12).
            return { phase: 'ended', commandId: null, payload: null, retry: false, outcome: event.outcome };
        case 'not_an_outcome':
            if (state.phase !== 'in_flight') {
                return state;
            }

            // To a retry of an open intent anything but an outcome leaves it open: an earlier attempt may have
            // committed (IP-02 "Answered, but not an outcome").
            return state.retry
                ? { phase: 'unknown', commandId: state.commandId, payload: state.payload, retry: true }
                : { phase: 'answered', commandId: state.commandId, payload: state.payload, retry: false, answer: event.answer };
        case 'failed':
            return state.phase === 'in_flight' ? { phase: 'unknown', commandId: state.commandId, payload: state.payload, retry: true } : state;
        case 'retry':
            return state.phase === 'unknown' ? { phase: 'in_flight', commandId: state.commandId, payload: state.payload, retry: true } : state;
        case 'leave':
            return idle();
    }
}

/** Whether the form's inputs are frozen: while the intent is in flight or its outcome unknown (D-UX-02). */
export function isFrozen<P>(state: IntentState<P>): boolean {
    return state.phase === 'in_flight' || state.phase === 'unknown';
}

/**
 * The intent as the form's own handlers see it: every transition is applied to the current state at once, before
 * React renders, so a second trigger or retry in the same moment — a double click, Enter beside a click, KB-10 —
 * sees the intent already in flight and gets null instead of a second identifier (IP-02; CI-01).
 */
export class IntentTracker<P> {
    private current: IntentState<P> = idle<P>();
    private readonly same: (a: P, b: P) => boolean;
    private readonly mint: () => string;
    private readonly changed: (state: IntentState<P>) => void;

    constructor(same: (a: P, b: P) => boolean, mint: () => string, changed: (state: IntentState<P>) => void = () => undefined) {
        this.same = same;
        this.mint = mint;
        this.changed = changed;
    }

    get state(): IntentState<P> {
        return this.current;
    }

    /** The identifier and payload to send, or null when the trigger is dropped. */
    trigger(payload: P): { commandId: string; payload: P } | null {
        const before = this.current;
        const next = this.apply({ type: 'trigger', payload, mint: this.mint, same: this.same });

        return next === before || next.phase !== 'in_flight' ? null : { commandId: next.commandId, payload: next.payload };
    }

    /** The same identifier and payload again after FAILED or a lost answer, or null when no retry is open. */
    retry(): { commandId: string; payload: P } | null {
        if (this.current.phase !== 'unknown') {
            return null;
        }

        const next = this.apply({ type: 'retry' });

        return next.phase === 'in_flight' ? { commandId: next.commandId, payload: next.payload } : null;
    }

    /** Applies an answer or any other event to the current state at once; the trigger and retry go through above. */
    apply(event: IntentEvent<P>): IntentState<P> {
        const before = this.current;
        this.current = reduceIntent(before, event);

        if (this.current !== before) {
            this.changed(this.current);
        }

        return this.current;
    }
}
