// The language switch (LOCALIZATION §2, §3.7; INT-001, INT-002): one per surface, both languages always shown,
// the current one announced as pressed. Before sign-in it sets the whitelisted address key `bahasa` and
// renders the same page again through a visit that keeps the page's state — typed input included —, never a
// full reload and nothing stored (INT-001). After sign-in the account menu passes its own `onSelect`, which
// sends the account's choice through TASK-023's command (INT-002). Drawn as the segmented control "ID | EN"
// (DESIGN_SYSTEM §10 "Segmented control"), the current language raised with its olive mark (GR-02); its two targets
// keep TASK-014's 44 px at every width and pointer, as the one control of the pages before sign-in.
import { router } from '@inertiajs/react';

import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
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
        <ToggleGroup
            aria-label={t('glossary.gl_198')}
            value={[locale]}
            onValueChange={(next: string[]) => {
                const option = next.find((value): value is Locale => value !== locale && LOCALES.includes(value as Locale));

                if (option !== undefined) {
                    onSelect(option);
                }
            }}
        >
            {LOCALES.map((option) => (
                <ToggleGroupItem key={option} value={option} lang={option} className="h-touch min-w-touch">
                    {t(LABEL_KEYS[option])}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
