import { type ReactNode, useEffect, useRef } from 'react';

import { type Answer, isAssertive, OutcomeMessage } from '@/components/outcome-message';
import { cn } from '@/lib/utils';

// The sticky action region of a command form or task step (DESIGN_SYSTEM §10 "Sticky action bar"; PT-09; D-DS-06):
// one region with the form's buttons — "Batal" before the one primary action (GF-15) —, fixed at the bottom on
// the phone, sticky at the bottom of a long form on the desktop. The command's answer appears directly above its
// buttons inside it, the region growing up to a third of the viewport and scrolling inside beyond that, so the
// answer is in view whenever the buttons are. Its two live regions exist from the page's load (§15). The page's
// scroll padding follows its height so nothing focused hides under it (WCAG 2.4.11; GR-06).
export function ActionBar({
    answer,
    answerAction,
    label,
    inline = false,
    children,
    className,
}: {
    answer?: Answer | null;
    answerAction?: ReactNode;
    label: string;
    /** Inside a dialog or sheet: the same region at the overlay's foot, neither fixed nor steering the page's scroll padding. */
    inline?: boolean;
    children: ReactNode;
    className?: string;
}) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const element = ref.current;

        if (element === null || inline) {
            return;
        }

        const root = document.documentElement;
        const observer = new ResizeObserver(() => {
            root.style.scrollPaddingBottom = `${String(element.offsetHeight)}px`;
        });
        observer.observe(element);

        return () => {
            observer.disconnect();
            root.style.removeProperty('scroll-padding-bottom');
        };
    }, [inline]);

    const polite = answer !== null && answer !== undefined && !isAssertive(answer);
    const assertive = answer !== null && answer !== undefined && isAssertive(answer);

    return (
        <div
            ref={ref}
            role="region"
            aria-label={label}
            data-print="hide"
            className={cn(
                inline
                    ? 'mt-auto flex flex-col gap-3 pt-2 md:mt-2'
                    : 'fixed inset-x-0 bottom-0 z-(--z-bar) flex max-h-(--action-region-max) flex-col gap-3 overflow-y-auto border-t border-border-subtle bg-surface px-m-gutter pt-3 pb-[calc(var(--space-3)+var(--safe-bottom))] shadow-footer',
                !inline && 'md:sticky md:bottom-0 md:px-gutter',
                className,
            )}
        >
            <div aria-live="polite" aria-atomic="true">
                {polite && <OutcomeMessage answer={answer} action={answerAction} />}
            </div>
            <div aria-live="assertive" aria-atomic="true">
                {assertive && <OutcomeMessage answer={answer} action={answerAction} />}
            </div>
            <div className="flex flex-col-reverse gap-2 *:w-full md:flex-row md:items-center md:justify-end md:*:w-auto">{children}</div>
        </div>
    );
}
