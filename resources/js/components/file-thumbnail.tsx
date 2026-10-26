import { ImageIcon } from 'lucide-react';
import { useState } from 'react';

import { Icon } from '@/components/ui/icon';

/** PT-22's thumbnail (PF-31, PF-32): ROUTE-227's 40 px derivative, loaded lazily below the fold as a background
    request by its route; while JB-04 has not made it — or when it is not the viewer's — the neutral placeholder. */
export function FileThumbnail({ fileId, alt }: { fileId: string; alt: string }) {
    const [missing, setMissing] = useState(false);

    if (missing) {
        return (
            <span data-slot="thumbnail" aria-hidden="true" className="inline-flex size-10 shrink-0 items-center justify-center rounded-sm bg-surface-sunken text-text-tertiary">
                <Icon icon={ImageIcon} size="sm" />
            </span>
        );
    }

    return (
        <img
            data-slot="thumbnail"
            src={`/berkas/${fileId}/gambar-kecil`}
            alt={alt}
            width={40}
            height={40}
            loading="lazy"
            decoding="async"
            className="size-10 shrink-0 rounded-sm object-cover"
            onError={() => {
                setMissing(true);
            }}
        />
    );
}
