import { useRef, useState } from 'react';

import { idle, IntentTracker, isFrozen, type IntentState, type NotAnOutcome, type Outcome } from './command-intent';

// The hook a command form keeps (IP-02): `trigger` mints the identifier at the user's action and returns it with
// the payload to send, or null when the trigger is dropped; the form reports each answer back. The intent lives in
// a tracker the handlers read synchronously, not in the last render, so two triggers or two retries in the same
// moment can never yield a second identifier or a second send (CI-01); the render follows the tracker.
export function useCommandIntent<P>(same: (a: P, b: P) => boolean = (a, b) => JSON.stringify(a) === JSON.stringify(b)) {
    const [state, setState] = useState<IntentState<P>>(() => idle<P>());
    const tracker = useRef<IntentTracker<P> | null>(null);

    const intent = (): IntentTracker<P> => {
        tracker.current ??= new IntentTracker<P>(same, () => crypto.randomUUID(), setState);

        return tracker.current;
    };

    return {
        state,
        frozen: isFrozen(state),
        trigger: (payload: P) => intent().trigger(payload),
        outcome: (outcome: Outcome) => {
            intent().apply({ type: 'outcome', outcome });
        },
        notAnOutcome: (answer: NotAnOutcome) => {
            intent().apply({ type: 'not_an_outcome', answer });
        },
        failed: () => {
            intent().apply({ type: 'failed' });
        },
        retry: () => intent().retry(),
        leave: () => {
            intent().apply({ type: 'leave' });
        },
    };
}
