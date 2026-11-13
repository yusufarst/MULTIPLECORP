// The session's account as the open document knows it (ADMIN_FLOW §6; UXH-02; TASK-093): an opaque key — a keyed
// digest of the account, no business data —, read first from the document's `session-account` meta tag and then from
// each response's header, outside the page data like the deadline (WS-07). The in-place re-authentication resumes the
// covered page only when the new session's key equals the one the page was covered with.

let account: string | null = null;
let read = false;

/** The key of the session that answered last, or null for a guest's document. */
export function sessionAccountKey(): string | null {
    if (!read) {
        read = true;
        const content = document.querySelector<HTMLMetaElement>('meta[name="session-account"]')?.content;
        account = content === undefined || content === '' ? null : content;
    }

    return account;
}

/** A response's header: the key of the session that answered it. */
export function receiveAccountKey(value: string): void {
    if (value === '') return;

    read = true;
    account = value;
}
