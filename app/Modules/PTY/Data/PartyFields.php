<?php

namespace App\Modules\PTY\Data;

/**
 * The fields of the party masters (DATABASE §4.3) and the one normalization of a name (TASK-026): the surrounding
 * space trimmed and every run of white space inside made one space, so "Unit  Kurikulum " and "Unit Kurikulum" are
 * one name to the unit's unique key on its lower-cased name, to the search-first list and to the duplicate warning.
 * Case is kept as typed; the comparisons lower-case it.
 */
final class PartyFields
{
    /** The editable fields of each client row and of a supplier, in the forms' order. */
    public const ORGANIZATION = ['name', 'npwp'];

    public const UNIT = ['name'];

    public const ADDRESS = ['label', 'address_lines', 'city'];

    public const CONTACT = ['name', 'position', 'phone', 'email'];

    public const SUPPLIER = ['name', 'npwp', 'address_lines', 'city', 'phone', 'email'];

    /** The table of each client row kind. */
    public const TABLES = ['organization' => 'client_organizations', 'unit' => 'client_units', 'address' => 'client_addresses', 'contact' => 'client_contacts'];

    public static function name(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /** A name as the unique key and the duplicate warning compare it. */
    public static function key(string $name): string
    {
        return mb_strtolower(self::name($name));
    }

    /**
     * An optional field: its text trimmed, or null when nothing remains; a name field normalized.
     *
     * @param  list<string>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, string|null>
     */
    public static function of(array $fields, array $input): array
    {
        $values = [];

        foreach ($fields as $field) {
            $value = $input[$field] ?? null;
            $text = is_string($value) ? ($field === 'name' ? self::name($value) : trim($value)) : '';
            $values[$field] = $text === '' ? null : $text;
        }

        return $values;
    }
}
