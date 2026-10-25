import '@fontsource-variable/inter/opsz.css';
import '../css/app.css';

import { CSPProvider } from '@base-ui/react/csp-provider';
import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

import { ToastProvider } from './components/ui/toast';
import { TooltipProvider } from './components/ui/tooltip';
import { loadLocale } from './lib/i18n';

// TL-13: the component gallery under Pages/Fixtures is local-only — its route exists in the local environment
// alone (routes/fixtures.php) and its pages enter the bundle only when VITE_FIXTURE_PAGES is set for a local build.
const pages =
    import.meta.env.VITE_FIXTURE_PAGES === 'true'
        ? import.meta.glob<{ default: ResolvedComponent }>('./Pages/**/*.tsx')
        : import.meta.glob<{ default: ResolvedComponent }>(['./Pages/**/*.tsx', '!./Pages/Fixtures/**']);

void createInertiaApp({
    // The page's language strings are loaded before it renders, once per language (LOCALIZATION §3.8).
    resolve: async (name, page) => {
        await loadLocale(page?.props.locale);

        return (await resolvePageComponent(`./Pages/${name}.tsx`, pages)).default;
    },
    // The per-request nonce of the CSP (SECURITY WS-08), for the styles Inertia injects at runtime.
    nonce: document.querySelector<HTMLMetaElement>('meta[property="csp-nonce"]')?.nonce,
    strictMode: true,
    // HO-37: Base UI injects no <style> element — the rules it would inject live in app.css — so the CSP needs no
    // 'unsafe-inline' style. The tooltip delay group and the toast queue span pages; the toast viewport is the
    // shell's (TASK-016), which has the page's strings for its close control.
    withApp: (app) => (
        <CSPProvider disableStyleElements>
            <TooltipProvider>
                <ToastProvider>{app}</ToastProvider>
            </TooltipProvider>
        </CSPProvider>
    ),
});
