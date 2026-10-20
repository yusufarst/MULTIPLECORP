import '../css/app.css';

import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const pages = import.meta.glob<{ default: ResolvedComponent }>('./Pages/**/*.tsx');

createInertiaApp({
    resolve: async (name) => (await resolvePageComponent(`./Pages/${name}.tsx`, pages)).default,
    // The per-request nonce of the CSP (SECURITY WS-08), for the styles Inertia injects at runtime.
    nonce: document.querySelector<HTMLMetaElement>('meta[property="csp-nonce"]')?.nonce,
    strictMode: true,
});
