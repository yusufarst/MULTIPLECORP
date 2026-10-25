// The string functions of LOCALIZATION §3.1 and §3.3, free of the framework so that TL-03 runs them under Node
// (tests/Frontend): Laravel's `:name` placeholders and the counted forms of `trans_choice`.

export type Replacements = Record<string, string | number>;

const escape = (name: string): string => name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const capitalized = (text: string): string => text.charAt(0).toUpperCase() + text.slice(1);

/**
 * Laravel's `:name` replacement (§3.1), as its `strtr` does it: `:name`, `:Name` capitalized and `:NAME`
 * upper-cased, matched once in the original template, the longest name first, so one never replaces part of
 * another. A value is data, inserted literally — never translated (§3.4), never read again for a placeholder
 * or for replacement syntax such as `$&`.
 */
export function replace(text: string, replacements: Replacements = {}): string {
    const values = new Map<string, string>();

    for (const [name, value] of Object.entries(replacements)) {
        const data = String(value);

        values.set(`:${capitalized(name)}`, capitalized(data));
        values.set(`:${name.toUpperCase()}`, data.toUpperCase());
        values.set(`:${name}`, data);
    }

    if (values.size === 0) {
        return text;
    }

    const pattern = new RegExp([...values.keys()].sort((a, b) => b.length - a.length).map(escape).join('|'), 'g');

    return text.replace(pattern, (placeholder) => values.get(placeholder) ?? placeholder);
}

/** A counted string's form (§3.3): Indonesian has one; English's two, one and other, are separated by `|`. */
export function choose(text: string, count: number): string {
    const forms = text.split('|');

    return forms.length === 1 ? text : ((count === 1 ? forms[0] : forms[1]) ?? text);
}
