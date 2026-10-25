// How strings reach the interface (LOCALIZATION §3.8; TASK-014): the locale arrives with every response as
// WS-07's shared prop, and its language's strings as one static bundle per language from the application's own
// origin, loaded once and before the page renders — never per key, never as a shared prop. A component writes
// no copy: it names a key of the catalogue, whose type the compiler checks, so no user input becomes a key.
import { usePage } from '@inertiajs/react';

import type catalogue from '../../../lang/id.json';

import { choose, replace, type Replacements } from './i18n-core';

export type Locale = 'id' | 'en';
export type TranslationKey = keyof typeof catalogue;
type Strings = Record<TranslationKey, string>;

export const LOCALES: readonly Locale[] = ['id', 'en'];
export const DEFAULT_LOCALE: Locale = 'id';

const bundles = import.meta.glob<{ default: Strings }>('../../../lang/*.json');
const loaded = new Map<Locale, Strings>();

export function isLocale(value: unknown): value is Locale {
    return value === 'id' || value === 'en';
}

/** Loads a language's strings once, before the page that needs them renders; an unknown value is Indonesian. */
export async function loadLocale(value: unknown): Promise<Locale> {
    const locale = isLocale(value) ? value : DEFAULT_LOCALE;
    const bundle = bundles[`../../../lang/${locale}.json`];

    if (!loaded.has(locale) && bundle !== undefined) {
        loaded.set(locale, (await bundle()).default);
    }

    document.documentElement.lang = locale;

    return locale;
}

export function translator(strings: Strings) {
    return {
        t: (key: TranslationKey, replacements?: Replacements): string => replace(strings[key], replacements),
        tc: (key: TranslationKey, count: number, replacements?: Replacements): string =>
            replace(choose(strings[key], count), { count, ...replacements }),
    };
}

/** The page's language and its strings; loaded by the resolver before the page renders (app.tsx). */
export function useTranslation() {
    const { locale: value } = usePage<{ locale: Locale }>().props;
    const locale = isLocale(value) ? value : DEFAULT_LOCALE;
    const strings = loaded.get(locale);

    if (strings === undefined) {
        throw new Error(`The strings of ${locale} are loaded before the page renders (LOCALIZATION §3.8).`);
    }

    return { locale, ...translator(strings) };
}
