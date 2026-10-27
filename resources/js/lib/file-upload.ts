// The client of an upload field (INT-013; ADMIN_FLOW IP-16; SECURITY FL-01): the file streamed outside any command,
// its progress reported and its request cancellable ("Batalkan"); the server answers the stored file's id for the
// command that follows, or MSG-31 for a file outside its purpose's types or size, or WS-12's limit. An upload is
// the user's own request, never a background one, and carries no `command_id` (RB-03). ROUTE-228's purpose and company
// travel as fields beside the file (TASK-030).

export type UploadAnswer = { kind: 'stored'; id: string; name: string } | { kind: 'refused'; message: string } | { kind: 'failed' } | { kind: 'cancelled' };

export function uploadFile(
    url: string,
    file: File,
    field: string,
    onProgress: (percent: number) => void,
    signal: AbortSignal,
    fields: Record<string, string> = {},
): Promise<UploadAnswer> {
    return new Promise((resolve) => {
        const request = new XMLHttpRequest();
        const body = new FormData();
        for (const [name, value] of Object.entries(fields)) {
            body.append(name, value);
        }
        body.append(field, file);

        request.open('POST', url);
        request.withCredentials = true;
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.upload.onprogress = (event) => {
            if (event.lengthComputable && event.total > 0) {
                onProgress((event.loaded / event.total) * 100);
            }
        };
        request.onload = () => {
            const answer = parse(request.responseText);

            if (request.status >= 200 && request.status < 300 && typeof answer.id === 'string') {
                resolve({ kind: 'stored', id: answer.id, name: typeof answer.name === 'string' ? answer.name : file.name });
            } else if ([413, 422, 429].includes(request.status) && typeof answer.message === 'string') {
                resolve({ kind: 'refused', message: answer.message });
            } else {
                resolve({ kind: 'failed' });
            }
        };
        request.onerror = () => {
            resolve({ kind: 'failed' });
        };
        request.onabort = () => {
            resolve({ kind: 'cancelled' });
        };
        signal.addEventListener('abort', () => {
            request.abort();
        });
        request.send(body);
    });
}

function parse(text: string): { id?: unknown; name?: unknown; message?: unknown } {
    try {
        const value: unknown = JSON.parse(text);

        return typeof value === 'object' && value !== null ? value : {};
    } catch {
        return {};
    }
}
