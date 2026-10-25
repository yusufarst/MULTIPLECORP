// The string functions of LOCALIZATION §3.1 and §3.3, free of the framework so that TL-03 runs them under Node
// (tests/Frontend): Laravel's `:name` placeholders and the counted forms of `trans_choice`.

export type Replacements = Record<string, string | number>;

/**
 * Laravel's `:name` replacement (§3.1): `:name`, `:Name` capitalized and `:NAME` upper-cased, the longest names
 * first so that one never replaces part of another. Values are data and are never translated (§3.4).
 */
export function replace(text: string, replacements: Replacements = {}): string {
    return Object.entries(replacements)
        .sort(([a], [b]) => b.length - a.length)
        .reduce((result, [name, value]) => {
            const data = String(value);

            return result
                .replaceAll(`:${name.toUpperCase()}`, data.toUpperCase())
                .replaceAll(`:${name.charAt(0).toUpperCase()}${name.slice(1)}`, data.charAt(0).toUpperCase() + data.slice(1))
                .replaceAll(`:${name}`, data);
        }, text);
}

/** A counted string's form (§3.3): Indonesian has one; English's two, one and other, are separated by `|`. */
export function choose(text: string, count: number): string {
    const forms = text.split('|');

    return forms.length === 1 ? text : ((count === 1 ? forms[0] : forms[1]) ?? text);
}
