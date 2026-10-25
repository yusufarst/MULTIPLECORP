// The language switch (LOCALIZATION §2, §3.7; INT-001, INT-002): one per surface, both languages always shown,
// the current one announced as pressed. Before sign-in it sets the whitelisted address key `bahasa` and
// renders the same page again through a visit that keeps the page's state — typed input included —, never a
// full reload and nothing stored (INT-001). After sign-in the account menu passes its own `onSelect`, which
// sends the account's choice through TASK-023's command (INT-002). TASK-015 restyles it with the design system.
import { router } from '@inertiajs/react';

import { LOCALES, type Locale, useTranslation } from '@/lib/i18n';

const LABEL_KEYS = { id: 'pages.pg_001.language.id', en: 'pages.pg_001.language.en' } as const;

/** INT-001: the same page in the chosen language, its state kept; the address key carried, nothing stored. */
export function switchAddressLanguage(locale: Locale): void {
    const url = new URL(window.location.href);
    url.searchParams.set('bahasa', locale);

    router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, replace: true });
}

export default function LanguageSwitch({ onSelect = switchAddressLanguage }: { onSelect?: (locale: Locale) => void }) {
    const { locale, t } = useTranslation();

    return (
        <div role="group" aria-label={t('glossary.gl_198')} className="inline-flex items-center gap-1">
            {LOCALES.map((option, index) => (
                <span key={option} className="inline-flex items-center gap-1">
                    {index > 0 && <span aria-hidden="true">|</span>}
                    <button
                        type="button"
                        lang={option}
                        aria-pressed={option === locale}
                        onClick={() => {
                            if (option !== locale) {
                                onSelect(option);
                            }
                        }}
                        className="min-h-11 min-w-11 rounded px-2 font-medium aria-pressed:underline"
                    >
                        {t(LABEL_KEYS[option])}
                    </button>
                </span>
            ))}
        </div>
    );
}
