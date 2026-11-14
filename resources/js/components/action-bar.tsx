import { type ReactNode, useEffect, useRef, useState } from 'react';

import { type Answer, isAssertive, OutcomeMessage } from '@/components/outcome-message';
import { cn } from '@/lib/utils';

// The sticky action region of a command form or task step (DESIGN_SYSTEM §10 "Sticky action bar"; PT-09; D-DS-06):
// one region with the form's buttons — "Batal" before the one primary action (GF-15) —, fixed at the bottom on
// the phone, sticky at the bottom of a long form on the desktop. The command's answer appears directly above its
// buttons inside it, the region growing up to a third of the viewport and scrolling inside beyond that, so the
// answer is in view whenever the buttons are. Its two live regions exist from the page's load (§15). On the phone it
// stands above the shell's bottom bar, "one region above the bottom bar" (§10), never under it — on a focused task the
// bottom bar hides and the region takes the foot (TASK-067 C; WCAG 2.5.8). The page's scroll padding follows what it
// covers of the viewport's foot so nothing focused hides under it (WCAG 2.4.11; GR-06).
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
    // Fixed on the phone, the region is out of the page's flow: a spacer of its height keeps the page's end reachable above it.
    const [floating, setFloating] = useState(0);

    useEffect(() => {
        const element = ref.current;

        if (element === null || inline) {
            return;
        }

        const root = document.documentElement;
        // Fixed on the phone, the region and the bottom bar beneath it cover the viewport's foot from its top edge down.
        const cover = () => {
            const fixed = getComputedStyle(element).position === 'fixed';
            const covered = fixed ? window.innerHeight - element.getBoundingClientRect().top : element.offsetHeight;
            root.style.scrollPaddingBottom = `${String(Math.max(element.offsetHeight, Math.round(covered)))}px`;
            setFloating(fixed ? element.offsetHeight : 0);
        };
        const observer = new ResizeObserver(cover);
        observer.observe(element);
        window.addEventListener('resize', cover);

        return () => {
            observer.disconnect();
            window.removeEventListener('resize', cover);
            root.style.removeProperty('scroll-padding-bottom');
        };
    }, [inline]);

    const polite = answer !== null && answer !== undefined && !isAssertive(answer);
    const assertive = answer !== null && answer !== undefined && isAssertive(answer);

    return (
        <>
            {!inline && floating > 0 && <div aria-hidden="true" data-slot="action-bar-spacer" className="shrink-0" style={{ height: floating }} />}
            <div
                ref={ref}
                role="region"
                aria-label={label}
                data-print="hide"
                className={cn(
                    inline
                        ? 'mt-auto flex flex-col gap-3 pt-2 md:mt-2'
                        : 'fixed inset-x-0 bottom-0 z-(--z-bar) flex max-h-(--action-region-max) flex-col gap-3 overflow-y-auto border-t border-border-subtle bg-surface px-m-gutter pt-3 pb-[calc(var(--space-3)+var(--safe-bottom))] shadow-footer',
                    !inline && 'max-md:group-data-[bottom-bar]/shell:bottom-[calc(var(--m-bottom-nav-h)+var(--safe-bottom))] max-md:group-data-[bottom-bar]/shell:pb-3 md:sticky md:bottom-0 md:px-gutter',
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
        </>
    );
}
