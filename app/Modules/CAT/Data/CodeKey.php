<?php

namespace App\Modules\CAT\Data;

/**
 * A barcode's or SKU's key as the catalogue compares it (DATABASE §4.4 `code_key`; BR-INV-09; C-01): every white space
 * removed and letters upper-cased, leading zeros kept — the same normalization the generated column applies, so a code
 * however typed or scanned resolves to at most one active product.
 */
final class CodeKey
{
    public static function of(string $code): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', $code));
    }

    /** A code as printed: its surrounding space trimmed and inner runs of white space one space. */
    public static function printed(string $code): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $code));
    }
}
