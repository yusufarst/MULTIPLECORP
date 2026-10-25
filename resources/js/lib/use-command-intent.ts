import { useCallback, useReducer } from 'react';

import { idle, isFrozen, type IntentEvent, type IntentState, type NotAnOutcome, type Outcome, reduceIntent } from './command-intent';

// The hook a command form keeps (IP-02): `trigger` mints the identifier at the user's action and returns it with
// the payload to send, or null when the trigger is dropped; the form reports each answer back.
export function useCommandIntent<P>(same: (a: P, b: P) => boolean = (a, b) => JSON.stringify(a) === JSON.stringify(b)) {
    const [state, dispatch] = useReducer((current: IntentState<P>, event: IntentEvent<P>) => reduceIntent(current, event), undefined, () => idle<P>());

    const trigger = useCallback(
        (payload: P): { commandId: string; payload: P } | null => {
            if (isFrozen(state)) {
                return null;
            }

            const next = reduceIntent(state, { type: 'trigger', payload, mint: () => crypto.randomUUID(), same });
            dispatch({ type: 'trigger', payload, mint: () => next.commandId ?? crypto.randomUUID(), same });

            return next.commandId === null ? null : { commandId: next.commandId, payload: next.payload };
        },
        [state, same],
    );

    return {
        state,
        frozen: isFrozen(state),
        trigger,
        outcome: (outcome: Outcome) => {
            dispatch({ type: 'outcome', outcome });
        },
        notAnOutcome: (answer: NotAnOutcome) => {
            dispatch({ type: 'not_an_outcome', answer });
        },
        failed: () => {
            dispatch({ type: 'failed' });
        },
        retry: (): { commandId: string; payload: P } | null => {
            if (state.phase !== 'unknown') {
                return null;
            }

            dispatch({ type: 'retry' });

            return { commandId: state.commandId, payload: state.payload };
        },
        leave: () => {
            dispatch({ type: 'leave' });
        },
    };
}
