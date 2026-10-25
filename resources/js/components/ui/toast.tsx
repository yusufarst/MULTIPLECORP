import { Toast as ToastPrimitive } from '@base-ui/react/toast';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { cn } from '@/lib/utils';

// PT-15's toast on Base UI's Toast (DESIGN_SYSTEM §9.4, §10; GR-15): only a quiet confirmation of an event that
// also leaves a visible trace on the page — never REJECTED, CONFLICT, FAILED, an unknown outcome, a warning or
// anything that needs a decision —; bottom right on the desktop, bottom above the navigation on the phone; a
// white card with a 3 px type edge, at most three, gone after 4.5 s unless focused or hovered; announced
// politely, or assertively for a `high` priority (WCAG 4.1.3).
export const TOAST_TIMEOUT_MS = 4500;
export const TOAST_LIMIT = 3;

export const useToastManager = ToastPrimitive.useToastManager;

export function ToastProvider({ children }: { children?: ReactNode }) {
    return (
        <ToastPrimitive.Provider timeout={TOAST_TIMEOUT_MS} limit={TOAST_LIMIT}>
            {children}
        </ToastPrimitive.Provider>
    );
}

const EDGE: Record<string, string> = {
    success: 'before:bg-success-dot',
    info: 'before:bg-info-dot',
    neutral: 'before:bg-neutral-dot',
};

export function ToastViewport({ closeLabel }: { closeLabel: string }) {
    const { toasts } = ToastPrimitive.useToastManager();

    return (
        <ToastPrimitive.Portal>
            <ToastPrimitive.Viewport
                data-slot="toast-viewport"
                className="fixed inset-x-m-gutter bottom-[calc(var(--m-bottom-nav-h)+var(--safe-bottom)+var(--space-3))] z-(--z-toast) flex flex-col gap-2 outline-none md:inset-x-auto md:right-6 md:bottom-6 md:w-96"
            >
                {toasts.map((toast) => (
                    <ToastPrimitive.Root
                        key={toast.id}
                        toast={toast}
                        data-motion
                        className={cn(
                            'relative flex items-start gap-3 overflow-hidden rounded-lg border border-border-subtle bg-surface p-3 pl-4 text-body text-text-primary shadow-toast transition-[opacity,translate] duration-(--mc-dur-base) ease-enter before:absolute before:inset-y-0 before:left-0 before:w-0.75 data-starting-style:translate-y-2 data-starting-style:opacity-0 data-ending-style:opacity-0 data-limited:hidden',
                            EDGE[toast.type ?? 'neutral'] ?? EDGE.neutral,
                        )}
                    >
                        <ToastPrimitive.Content className="flex min-w-0 flex-1 flex-col gap-0.5">
                            <ToastPrimitive.Title className="text-body font-semibold" />
                            <ToastPrimitive.Description className="text-body text-text-secondary" />
                        </ToastPrimitive.Content>
                        <ToastPrimitive.Close render={<Button variant="ghost" size="icon-xs" aria-label={closeLabel} />}>
                            <Icon icon={X} size="xs" />
                        </ToastPrimitive.Close>
                    </ToastPrimitive.Root>
                ))}
            </ToastPrimitive.Viewport>
        </ToastPrimitive.Portal>
    );
}
