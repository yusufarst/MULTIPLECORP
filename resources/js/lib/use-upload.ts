import { useRef, useState } from 'react';

import { type UploadState } from '@/components/notices';
import { uploadFile } from '@/lib/file-upload';
import { useTranslation } from '@/lib/i18n';

/** ROUTE-228's upload of one file, its state for IP-16's field: outside any command, cancellable, retried with the same file (FL-01). */
export function useUpload(fields: () => Record<string, string>) {
    const { t } = useTranslation();
    const [state, setState] = useState<UploadState>({ phase: 'idle' });
    const [file, setFile] = useState<string | null>(null);
    const abort = useRef<AbortController | null>(null);
    const last = useRef<File | null>(null);

    const choose = (chosen: File) => {
        last.current = chosen;
        abort.current = new AbortController();
        setFile(null);
        setState({ phase: 'uploading', progress: 0 });
        void uploadFile('/berkas', chosen, 'file', (progress) => {
            setState({ phase: 'uploading', progress });
        }, abort.current.signal, fields()).then((answer) => {
            if (answer.kind === 'stored') {
                setFile(answer.id);
                setState({ phase: 'done', name: answer.name });
            } else if (answer.kind === 'refused') {
                setState({ phase: 'error', message: answer.message });
            } else if (answer.kind === 'cancelled') {
                setState({ phase: 'idle' });
            } else {
                setState({ phase: 'error', message: t('messages.msg_07') });
            }
        });
    };

    return {
        state,
        file,
        choose,
        cancel: () => abort.current?.abort(),
        reset: () => {
            setFile(null);
            setState({ phase: 'idle' });
        },
        retry: () => {
            if (last.current !== null) {
                choose(last.current);
            }
        },
    };
}
