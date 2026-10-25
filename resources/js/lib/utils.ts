import { clsx, type ClassValue } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

// The design system's own theme keys (DESIGN_SYSTEM §5; resources/css/tokens.css): tailwind-merge must know
// that `text-body` is a size and `text-text-primary` a colour, or merging one would drop the other.
const twMerge = extendTailwindMerge({
    extend: {
        theme: {
            text: [
                'display',
                'title',
                'figure-xl',
                'figure-lg',
                'heading',
                'heading-md',
                'heading-lg',
                'heading-sm',
                'lead',
                'body-lg',
                'body',
                'label',
                'caption',
                'micro',
                'pill',
                'pill-sm',
                'm-title',
                'm-heading',
                'm-row',
                'm-body',
                'm-meta',
                'm-figure',
                'm-total',
                'touch',
            ],
            radius: ['xs', 'sm', 'md', 'lg', 'xl', 'sheet', 'full'],
            shadow: ['xs', 'card', 'card-hover', 'pop', 'raised', 'menu', 'toast', 'modal', 'drawer', 'footer', 'cta'],
            breakpoint: ['md', 'lg', 'xl', 'composer'],
        },
    },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}
