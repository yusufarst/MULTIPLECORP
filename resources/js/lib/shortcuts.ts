import { useEffect, useRef } from 'react';

import { SCAN_TARGET } from './scanner';

// The keyboard shortcuts of DESIGN_SYSTEM §13.1: always with a modifier — never a single printable key, since a
// scanner types printable characters (WCAG 2.1.4) —, all inactive while the scan field has focus, and none sends
// a financial or stock command by itself (KB-10 only opens the confirmation).
export type Chord = { key: string; ctrl?: boolean; alt?: boolean; shift?: boolean };

export function matches(event: KeyboardEvent, chord: Chord): boolean {
    return (
        event.key.toLowerCase() === chord.key.toLowerCase() &&
        (event.ctrlKey || event.metaKey) === (chord.ctrl ?? false) &&
        event.altKey === (chord.alt ?? false) &&
        event.shiftKey === (chord.shift ?? false)
    );
}

export function scanFieldFocused(): boolean {
    return document.activeElement?.closest(`[${SCAN_TARGET}="scan-field"]`) != null;
}

/** Binds a chord while the component is mounted, optionally only inside `scope`. */
export function useShortcut(chord: Chord, handler: (event: KeyboardEvent) => void, scope?: { current: HTMLElement | null }, enabled = true): void {
    const latest = useRef(handler);

    useEffect(() => {
        latest.current = handler;
    });

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const listener = (event: KeyboardEvent) => {
            if (!matches(event, chord) || scanFieldFocused()) {
                return;
            }

            if (scope !== undefined && (scope.current === null || !scope.current.contains(document.activeElement))) {
                return;
            }

            event.preventDefault();
            latest.current(event);
        };

        document.addEventListener('keydown', listener);

        return () => {
            document.removeEventListener('keydown', listener);
        };
    }, [chord.key, chord.ctrl, chord.alt, chord.shift, scope, enabled]); // eslint-disable-line react-hooks/exhaustive-deps
}
