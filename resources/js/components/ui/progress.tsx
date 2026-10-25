import { Progress as ProgressPrimitive } from '@base-ui/react/progress';

import { cn } from '@/lib/utils';

// A process's progress bar only — an upload, an import (DESIGN_SYSTEM §10; SLOP-44): 10 px, the full radius,
// the progress fill on its track; the fill is 1.87:1 on the track, so its value is always text beside it.
export function Progress({ className, value, valueText, label, ...props }: ProgressPrimitive.Root.Props & { valueText: string; label: string }) {
    return (
        <ProgressPrimitive.Root data-slot="progress" aria-label={label} value={value} className={cn('flex min-w-0 items-center gap-3', className)} {...props}>
            <ProgressPrimitive.Track className="h-progress min-w-0 flex-1 overflow-hidden rounded-full bg-chart-track">
                <ProgressPrimitive.Indicator className="h-full rounded-full bg-progress transition-[width] duration-(--mc-dur-base) motion-reduce:transition-none" />
            </ProgressPrimitive.Track>
            <span className="shrink-0 text-caption text-text-secondary tabular-nums">{valueText}</span>
        </ProgressPrimitive.Root>
    );
}
