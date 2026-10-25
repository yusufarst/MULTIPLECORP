<?php

namespace App\Support\Search;

use InvalidArgumentException;

/**
 * A search term as PERFORMANCE §5 reads it (WS-03; PF-19–PF-23): at most 100 characters; a name is searched by
 * trigrams only when it holds three consecutive letters or digits, otherwise only by the exact and prefix
 * lookups; LIKE's wildcards and escape are taken literally. The term itself is never logged, stored or put in
 * an address (DP-02).
 */
final readonly class Term
{
    public const MAX_LENGTH = 100;

    public const TRIGRAM_RUN = 3;

    public string $text;

    public function __construct(string $text)
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '' || mb_strlen($text) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('A search term holds 1 to 100 characters (WS-03).');
        }

        $this->text = $text;
    }

    /** PF-20: at least three consecutive letters or digits, so the term yields a trigram. */
    public function hasTrigram(): bool
    {
        return preg_match('/[\p{L}\p{N}]{'.self::TRIGRAM_RUN.',}/u', $this->text) === 1;
    }

    /** A term that reads as a code rather than a name: one word that holds a digit (IP-17; MSG-25). */
    public function looksLikeCode(): bool
    {
        return ! str_contains($this->text, ' ') && preg_match('/\p{N}/u', $this->text) === 1;
    }

    public function lower(): string
    {
        return mb_strtolower($this->text);
    }

    /** The lower-cased term with LIKE's `%`, `_` and `\` escaped, for `like ? escape '\'`. */
    public function likeLiteral(): string
    {
        return addcslashes($this->lower(), '\\%_');
    }

    /** A code key as the catalogue keeps it: whitespace removed (DATABASE §4.4 `code_key`). */
    public function compact(): string
    {
        return (string) preg_replace('/\s+/u', '', $this->text);
    }
}
