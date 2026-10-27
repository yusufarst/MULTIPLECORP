import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';

import { type Answer } from '@/components/outcome-message';
import { type Outcome } from '@/lib/command-intent';
import { useTranslation } from '@/lib/i18n';
import { useCommandIntent } from '@/lib/use-command-intent';

type Given = { kind: Outcome | 'FAILED'; cause: string | null; text: string; state?: Record<string, unknown> };

// One command form's sending and answering (IP-01; IP-02; IP-04; TASK-024): one `command_id` minted at the user's
// action for one intent, sent with the payload; the server's answer read from the next page's flash data and shown in
// place; a validation answer at the fields (IP-14); the answer lost on the way leaving the outcome unknown, so
// "Coba Lagi" sends the same identifier and payload again — never a second command (CI-01; HO-02). The step-up of
// IP-10 is the shell's: it answers 423, asks the password and sends the same request again (AU-15).
export type CommandForm<P> = {
    answer: Answer | null;
    errors: Record<string, string>;
    state: Record<string, unknown>;
    pending: boolean;
    unknown: boolean;
    frozen: boolean;
    clear: () => void;
    submit: (payload: P, done?: () => void) => void;
    retry: () => void;
};

export function useCommand<P extends Record<string, FormDataConvertible>>(url: () => string, method: 'post' | 'put'): CommandForm<P> {
    const { t } = useTranslation();
    const intent = useCommandIntent<P>();
    const [answer, setAnswer] = useState<Answer | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [state, setState] = useState<Record<string, unknown>>({});
    const finished = useRef<(() => void) | undefined>(undefined);
    const unknown = (): void => {
        intent.failed();
        setAnswer({ kind: 'FAILED', cause: 'unknown', text: t('messages.msg_07') });
    };

    const send = (sent: { commandId: string; payload: P } | null, done?: () => void) => {
        if (sent === null) return;
        setErrors({});
        router[method](url(), { command_id: sent.commandId, ...sent.payload }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const given = (page.flash as { answer?: Given }).answer;

                if (given === undefined) {
                    unknown();

                    return;
                }

                setState(given.state ?? {});

                if (given.kind === 'FAILED') {
                    intent.failed();
                    setAnswer({ kind: 'FAILED', cause: 'not_processed', text: given.text });

                    return;
                }

                intent.outcome(given.kind);
                setAnswer(
                    given.kind === 'COMMITTED'
                        ? { kind: 'COMMITTED', text: given.text }
                        : given.kind === 'CONFLICT'
                          ? { kind: 'CONFLICT', cause: given.cause === 'stale' ? 'stale' : 'recorded', text: given.text }
                          : given.kind === 'PENDING'
                            ? { kind: 'PENDING', text: given.text }
                            : { kind: 'REJECTED', text: given.text },
                );

                if (given.kind === 'COMMITTED') {
                    done?.();
                }
            },
            onError: (bag) => {
                intent.notAnOutcome('VALIDATION');
                setErrors(bag);
            },
            onNetworkError: () => {
                unknown();

                return false;
            },
        });
    };

    return {
        answer,
        errors,
        state,
        pending: intent.state.phase === 'in_flight',
        unknown: intent.state.phase === 'unknown',
        frozen: intent.frozen,
        clear: () => {
            setAnswer(null);
            setErrors({});
        },
        /** Sends the payload as a new intent — or, while an earlier one's outcome is unknown, retries it unchanged (IP-02). */
        submit: (payload: P, done?: () => void) => {
            if (intent.state.phase === 'unknown') {
                send(intent.retry(), finished.current);

                return;
            }

            finished.current = done;
            send(intent.trigger(payload), done);
        },
        retry: () => {
            send(intent.retry(), finished.current);
        },
    };
}
