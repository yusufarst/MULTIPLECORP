import { Clock } from 'lucide-react';
import { useEffect, useState, useSyncExternalStore } from 'react';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Icon } from '@/components/ui/icon';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import * as format from '@/lib/format';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { deadlineNoticeDue } from '@/lib/session-core';
import { sessionDeadline, subscribeDeadline } from '@/lib/session-deadline';

// The shell's global states (INFORMATION_ARCHITECTURE §6.1, §8.8): the notice strip, present only while a notice
// is due — the absolute session limit, MSG-34, from fifteen minutes before it, the tab's current session's —,
// and the *Pintasan* help of KB-02, which lists the keys of DESIGN_SYSTEM §13.1 in the user's terms.

export function NoticeStrip() {
    const { t } = useTranslation();
    const deadline = useSyncExternalStore(subscribeDeadline, sessionDeadline);
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => {
            setNow(Date.now());
        }, 30_000);

        return () => {
            window.clearInterval(timer);
        };
    }, []);

    if (deadline === null || !deadlineNoticeDue(deadline, now)) {
        return null;
    }

    return (
        <div role="status" aria-label={t('shell.notice.label')} className="flex items-center gap-2 border-b border-border-subtle bg-tint-peach px-m-gutter py-2 text-body text-tint-peach-ink md:px-gutter">
            <Icon icon={Clock} size="sm" />
            {t('messages.msg_34', { jam: format.time(new Date(deadline).toISOString()) })}
        </div>
    );
}

const SHORTCUTS: { keys: string[]; action: TranslationKey }[] = [
    { keys: ['Ctrl', 'K'], action: 'shortcuts.kb_01' },
    { keys: ['Ctrl', '/'], action: 'shortcuts.kb_02' },
    { keys: ['Ctrl', 'B'], action: 'shortcuts.kb_03' },
    { keys: ['Esc'], action: 'shortcuts.kb_04' },
    { keys: ['Enter'], action: 'shortcuts.kb_05' },
    { keys: ['Alt', 'N'], action: 'shortcuts.kb_06' },
    { keys: ['Alt', 'Delete'], action: 'shortcuts.kb_07' },
    { keys: ['Alt', '↑', '↓'], action: 'shortcuts.kb_08' },
    { keys: ['Ctrl', 'S'], action: 'shortcuts.kb_09' },
    { keys: ['Ctrl', 'Enter'], action: 'shortcuts.kb_10' },
    { keys: ['↑', '↓', 'Enter'], action: 'shortcuts.kb_11' },
];

export function ShortcutsHelp({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent phone="full" closeLabel={t('actions.close')} className="md:w-sheet-sm">
                <DialogHeader>
                    <DialogTitle>{t('glossary.gl_185')}</DialogTitle>
                </DialogHeader>
                <dl className="flex flex-col divide-y divide-border-subtle">
                    {SHORTCUTS.map((shortcut) => (
                        <div key={shortcut.action} className="flex items-center justify-between gap-4 py-2">
                            <dt className="text-body text-text-primary">{t(shortcut.action)}</dt>
                            <dd className="shrink-0">
                                <KbdGroup>
                                    {shortcut.keys.map((key) => (
                                        <Kbd key={key}>{key}</Kbd>
                                    ))}
                                </KbdGroup>
                            </dd>
                        </div>
                    ))}
                </dl>
            </DialogContent>
        </Dialog>
    );
}
