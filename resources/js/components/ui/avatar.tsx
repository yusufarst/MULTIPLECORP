import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

// The account's initial in the account menu only (DESIGN_SYSTEM §10; no avatars elsewhere, SLOP-45): a circle on
// the avatar grey with the initial at 600. The name it stands for is always beside it or its accessible name.
export function Avatar({ initial, className, ...props }: ComponentProps<'span'> & { initial: string }) {
    return (
        <span
            data-slot="avatar"
            aria-hidden="true"
            className={cn('inline-flex size-disc-md shrink-0 items-center justify-center rounded-full bg-avatar text-body-lg font-semibold text-text-strong select-none', className)}
            {...props}
        >
            {initial}
        </span>
    );
}
