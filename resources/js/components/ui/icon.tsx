import type { LucideIcon, LucideProps } from 'lucide-react';

import { cn } from '@/lib/utils';

// Lucide drawn at the reference's strokes (DESIGN_SYSTEM §5.3, §8; GR-18): the stroke is given in rendered
// px — 1.25 at 12–14 px boxes, 1.5 at 16–20, 1.75 at 22–24, 2 at 28 and up — whatever the box. An icon is
// never the only cue (§15): it is hidden from assistive technology unless it carries a `label`.
const SIZES = { xs: 12, status: 14, sm: 16, row: 18, md: 20, nav: 22, lg: 24, card: 28 } as const;

export type IconSize = keyof typeof SIZES;

function stroke(px: number): number {
    if (px <= 14) {
        return 1.25;
    }

    if (px <= 20) {
        return 1.5;
    }

    return px >= 28 ? 2 : 1.75;
}

export function Icon({ icon: Glyph, size = 'sm', label, className, ...props }: Omit<LucideProps, 'size' | 'ref'> & { icon: LucideIcon; size?: IconSize; label?: string }) {
    const px = SIZES[size];

    return (
        <Glyph
            data-slot="icon"
            size={px}
            strokeWidth={stroke(px)}
            nonScalingStroke
            aria-hidden={label === undefined}
            aria-label={label}
            role={label === undefined ? undefined : 'img'}
            className={cn('shrink-0', className)}
            {...props}
        />
    );
}
