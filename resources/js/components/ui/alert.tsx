import type { LucideIcon } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';

import { Icon } from '@/components/ui/icon';
import { cn } from '@/lib/utils';

// The reference's notice anatomy, PT-14's one face for every inline message (DESIGN_SYSTEM §9.4, §12.2): white,
// a 1 px border, the tone's icon, the label at 600, the text in ink and at most one action — never a filled
// colour block (§12). The tone colours only the icon.
const ICON_TONE = {
    neutral: 'text-text-tertiary',
    info: 'text-info-dot',
    success: 'text-success-fg',
    warning: 'text-warning-fg',
    danger: 'text-danger-icon',
} as const;

export type AlertTone = keyof typeof ICON_TONE;

export function Alert({
    tone = 'neutral',
    icon,
    title,
    action,
    className,
    children,
    ...props
}: Omit<ComponentProps<'div'>, 'title'> & { tone?: AlertTone; icon: LucideIcon; title: ReactNode; action?: ReactNode }) {
    return (
        <div data-slot="alert" data-tone={tone} className={cn('flex min-w-0 items-start gap-3 rounded-md border border-border bg-surface p-3', className)} {...props}>
            <Icon icon={icon} size="md" className={cn('mt-px', ICON_TONE[tone])} />
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="text-body font-semibold text-text-primary">{title}</p>
                {children !== undefined && <div className="text-body text-text-primary">{children}</div>}
                {action !== undefined && <div className="mt-2 flex">{action}</div>}
            </div>
        </div>
    );
}
