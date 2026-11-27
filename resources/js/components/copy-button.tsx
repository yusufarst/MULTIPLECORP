import { Check, Copy } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { useTranslation } from '@/lib/i18n';

// APPR-042's pilot (CR-002 K-5): copying a text to the clipboard on the person's own click — nothing logged, stored
// or sent (DIR-134 E5). The answer is both seen and heard: the icon turns to a check and one status line says
// "Tersalin"; where the browser refuses, the text is selected for Ctrl C and the line says so. The button stays
// enabled, so a retry is one more click (Modern Web Guidance, copy-button).
export function CopyButton({ label, text, source }: { label: string; text: () => string; source?: () => Element | null }) {
    const { t } = useTranslation();
    const [answer, setAnswer] = useState<'copied' | 'failed' | null>(null);
    const [repeat, setRepeat] = useState(false);
    const timer = useRef<number | undefined>(undefined);

    useEffect(
        () => () => {
            window.clearTimeout(timer.current);
        },
        [],
    );

    const report = (next: 'copied' | 'failed') => {
        window.clearTimeout(timer.current);
        setAnswer(next);
        // A screen reader skips a message equal to the last one: a no-break space makes each answer new.
        setRepeat((value) => !value);
        timer.current = window.setTimeout(
            () => {
                setAnswer(null);
            },
            next === 'copied' ? 2500 : 8000,
        );
    };

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(text());
            report('copied');
        } catch {
            const element = source?.();

            if (element !== null && element !== undefined) {
                window.getSelection()?.selectAllChildren(element);
            }

            report('failed');
        }
    };

    const message = answer === 'copied' ? t('pages.pg_004.copied') : answer === 'failed' ? t('pages.pg_004.copy_failed') : '';

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
            <Button
                variant="outline"
                size="md"
                onClick={() => {
                    void copy();
                }}
            >
                <Icon icon={answer === 'copied' ? Check : Copy} size="sm" />
                {label}
            </Button>
            <span role="status" className="text-caption text-text-secondary">
                {message === '' ? '' : `${message}${repeat ? ' ' : ''}`}
            </span>
        </div>
    );
}
