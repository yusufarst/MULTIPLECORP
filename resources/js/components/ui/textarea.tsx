import type { ComponentProps } from 'react';

import { fieldBoxClass } from '@/components/ui/input';
import { cn } from '@/lib/utils';

// A multi-line field — a reason (MSG-40), a note — on the field box, growing vertically only.
export function Textarea({ className, ...props }: ComponentProps<'textarea'>) {
    return <textarea data-slot="textarea" className={cn(fieldBoxClass, 'h-auto min-h-control-xl resize-y py-2 leading-(--text-body--line-height)', className)} {...props} />;
}
