import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// A field's label (DESIGN_SYSTEM PT-09; the reference's field): above its field with an 8 px gap, 13 px at
// 400 in the secondary ink, sentence case (§16).
export function Label({ className, ...props }: ComponentProps<'label'>) {
    return (
        <label
            data-slot="label"
            className={cn('inline-flex items-center gap-1 text-(length:--text-field-label-size) leading-(--text-label--line-height) font-regular text-text-secondary', className)}
            {...props}
        />
    );
}
