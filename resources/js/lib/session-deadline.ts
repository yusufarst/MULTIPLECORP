// The absolute-session deadline the open document knows (ADMIN_FLOW IP-11; AU-08; UXH-11; TASK-016): read first
// from the document's `session-deadline` meta tag and then from each response's header, kept for the tab rather
// than for one page, so a page mounted after a new sign-in reads the new session's limit and never the first
// document's. A non-sensitive timestamp, outside the page data (WS-07).

let deadline: number | null = null;
let read = false;
const listeners = new Set<() => void>();

function parse(value: string | undefined | null): number | null {
    const instant = value === undefined || value === null ? Number.NaN : Date.parse(value);

    return Number.isNaN(instant) ? null : instant;
}

/** The deadline as an epoch instant, or null for a guest's document. */
export function sessionDeadline(): number | null {
    if (!read) {
        read = true;
        deadline = parse(document.querySelector<HTMLMetaElement>('meta[name="session-deadline"]')?.content);
    }

    return deadline;
}

/** A response's header: the deadline of the session that answered it. */
export function receiveDeadline(value: string): void {
    const instant = parse(value);

    if (instant === null || (read && instant === deadline)) {
        return;
    }

    read = true;
    deadline = instant;
    listeners.forEach((listener) => {
        listener();
    });
}

export function subscribeDeadline(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}
