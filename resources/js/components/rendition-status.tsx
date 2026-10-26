import { Download, RefreshCw } from 'lucide-react';

import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { useTranslation } from '@/lib/i18n';
import { useStatusPoll } from '@/lib/use-status-poll';

/** ADMIN_FLOW IP-12's rendition group (JB-01, JB-02; QS-08): the committed issue is always shown as done — the number
    never changes (NM-07) —, and only the PDF has a state: *PDF sedang dibuat* while PENDING or RENDERING, polled as a
    background request no faster than once a minute and refreshable; *PDF siap* with "Unduh" for the family's viewers;
    *PDF gagal dibuat* with MSG-08 and its attempt — the system tries again by itself —, or MSG-08's exhausted variant
    once the operator is told. There is no "terbitkan ulang". */
export function RenditionStatus({ versionId, documentName, number }: { versionId: string; documentName: string; number: string }) {
    const { t } = useTranslation();
    const { status, refresh } = useStatusPoll('rendisi', versionId);
    const state = status?.state === 'READY' || status?.state === 'FAILED' || status?.state === 'RENDERING' ? status.state : 'PENDING';

    return (
        <div data-slot="rendition-status" className="flex min-w-0 flex-col gap-2">
            <div className="flex flex-wrap items-center gap-3">
                <StatusBadge family="rendition" state={state} />
                {state === 'READY' && status?.file !== null && status?.file !== undefined && (
                    <Button variant="outline" size="sm" render={<a href={`/berkas/${status.file}/unduh`} />}>
                        <Icon icon={Download} size="sm" />
                        {t('actions.download')}
                    </Button>
                )}
                {state !== 'READY' && !(status?.exhausted ?? false) && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            void refresh();
                        }}
                    >
                        <Icon icon={RefreshCw} size="sm" />
                        {t('states.refresh')}
                    </Button>
                )}
            </div>
            {state === 'FAILED' && status !== null && (
                <p role="status" className="text-body text-text-secondary">
                    {status.exhausted
                        ? t('messages.msg_08_exhausted', { jenis: documentName, nomor: number })
                        : t('messages.msg_08', { jenis: documentName, nomor: number, n: status.attempt })}
                </p>
            )}
        </div>
    );
}
