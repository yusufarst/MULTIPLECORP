// The local stack's sample page of TASK-022 (routes/fixtures.php): an upload through the real pipeline, each stored
// file downloaded by ROUTE-226 and an image's thumbnail by ROUTE-227, and an issued synthetic document's rendition
// state polled by ROUTE-285 — so the browser suite proves INT-013, INT-014, INT-227 and INT-285 before the pages that
// will carry them exist. Not shipped in a production build (TL-13).
import { router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useRef, useState } from 'react';

import { FileThumbnail } from '@/components/file-thumbnail';
import { UploadField, type UploadState } from '@/components/notices';
import { PageHeader } from '@/components/page-header';
import { RenditionStatus } from '@/components/rendition-status';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import AppShell from '@/layouts/AppShell';
import { uploadFile } from '@/lib/file-upload';
import { useTranslation } from '@/lib/i18n';

type StoredFile = { id: string; name: string | null; image: boolean };

export default function FilesFixture({ files, rendition }: { files: StoredFile[]; rendition: string | null }) {
    const { t } = useTranslation();
    const [state, setState] = useState<UploadState>({ phase: 'idle' });
    const abort = useRef<AbortController | null>(null);
    const last = useRef<File | null>(null);

    const send = (file: File) => {
        last.current = file;
        abort.current = new AbortController();
        setState({ phase: 'uploading', progress: 0 });
        void uploadFile('/__fixtures/berkas', file, 'berkas', (progress) => {
            setState({ phase: 'uploading', progress });
        }, abort.current.signal).then((answer) => {
            if (answer.kind === 'stored') {
                setState({ phase: 'done', name: answer.name });
                router.reload({ only: ['files'] });
            } else if (answer.kind === 'refused') {
                setState({ phase: 'error', message: answer.message });
            } else if (answer.kind === 'cancelled') {
                setState({ phase: 'idle' });
            } else {
                setState({ phase: 'error', message: t('messages.msg_07') });
            }
        });
    };

    return (
        <AppShell title={t('glossary.gl_122')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('glossary.gl_122')} />
                <UploadField
                    label={t('glossary.gl_122')}
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx"
                    limits={t('files.limits')}
                    state={state}
                    onChoose={send}
                    onCancel={() => abort.current?.abort()}
                    onRemove={() => {
                        setState({ phase: 'idle' });
                    }}
                    onRetry={() => {
                        if (last.current !== null) {
                            send(last.current);
                        }
                    }}
                />
                <ul className="flex min-w-0 flex-col gap-2">
                    {files.map((file) => (
                        <li key={file.id} className="flex min-w-0 items-center gap-3">
                            {file.image && <FileThumbnail fileId={file.id} alt={file.name ?? ''} />}
                            <span className="min-w-0 truncate text-body text-text-primary">{file.name}</span>
                            <Button variant="outline" size="sm" className="ml-auto" render={<a href={`/berkas/${file.id}/unduh`} />}>
                                <Icon icon={Download} size="sm" />
                                {t('actions.download')}
                            </Button>
                        </li>
                    ))}
                </ul>
                {rendition !== null && <RenditionStatus versionId={rendition} documentName={t('documents.doc_01')} number="—" />}
            </div>
        </AppShell>
    );
}
