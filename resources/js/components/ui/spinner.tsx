import { LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Icon, type IconSize } from '@/components/ui/icon';
import { cn } from '@/lib/utils';

// PT-19's spinner (DESIGN_SYSTEM §9.4, §12.3): shown only after 300 ms, so a fast answer never flashes; one
// turn per 900 ms (`--mc-dur-spin`), still under reduced motion only as a crossfade (§8). No skeleton.
export const SPINNER_DELAY_MS = 300;

export function useDelayed(active: boolean, delay = SPINNER_DELAY_MS): boolean {
    const [shown, setShown] = useState(false);

    useEffect(() => {
        if (!active) {
            return;
        }

        const timer = window.setTimeout(() => {
            setShown(true);
        }, delay);

        return () => {
            window.clearTimeout(timer);
            setShown(false);
        };
    }, [active, delay]);

    return active && shown;
}

export function Spinner({ label, size = 'sm', delay = SPINNER_DELAY_MS, className }: { label: string; size?: IconSize; delay?: number; className?: string }) {
    const shown = useDelayed(true, delay);

    if (!shown) {
        return null;
    }

    return (
        <span data-slot="spinner" role="status" className={cn('inline-flex items-center', className)}>
            <Icon icon={LoaderCircle} size={size} className="animate-spin [animation-duration:var(--mc-dur-spin)] motion-reduce:animate-none" />
            <span className="sr-only">{label}</span>
        </span>
    );
}
