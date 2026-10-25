import '../css/app.css';

import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

import { loadLocale } from './lib/i18n';

const pages = import.meta.glob<{ default: ResolvedComponent }>('./Pages/**/*.tsx');

void createInertiaApp({
    // The page's language strings are loaded before it renders, once per language (LOCALIZATION §3.8).
    resolve: async (name, page) => {
        await loadLocale(page?.props.locale);

        return (await resolvePageComponent(`./Pages/${name}.tsx`, pages)).default;
    },
    // The per-request nonce of the CSP (SECURITY WS-08), for the styles Inertia injects at runtime.
    nonce: document.querySelector<HTMLMetaElement>('meta[property="csp-nonce"]')?.nonce,
    strictMode: true,
});
