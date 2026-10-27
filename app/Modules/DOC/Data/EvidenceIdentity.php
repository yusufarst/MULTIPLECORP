<?php

namespace App\Modules\DOC\Data;

use Normalizer;

/**
 * The evidence identity of L-45 and SF-EVIDENCE — issuer, type, the issuer's reference and document date per company
 * —, its normalization defined once (DIR-089 (2)): `identity_key` is `issuer|TYPE|reference|date`, and the type-free
 * identity index of DATABASE §4.10 reads its first and third parts, so the index and the key cannot normalize apart.
 * An issuer keeps its letters and digits, case-folded, every other run of characters one space; a reference keeps its
 * characters case-folded without spaces, the separator `|` dropped; a document without a reference has an empty one.
 * FU-10's registration keys are derived here too: 7101 a content hash, 7102 a type-free identity — issuer, reference
 * and date whatever the company, or company, issuer and date for a document without a reference —, the second
 * integer the low 32 bits of the key's SHA-256.
 */
final class EvidenceIdentity
{
    public const CONTENT_NAMESPACE = 7101;

    public const IDENTITY_NAMESPACE = 7102;

    public static function issuer(string $issuer): string
    {
        $folded = mb_strtolower(self::compatible($issuer));

        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $folded));
    }

    public static function reference(?string $reference): string
    {
        if ($reference === null) {
            return '';
        }

        return (string) preg_replace('/[\s|]+/u', '', mb_strtolower(self::compatible($reference)));
    }

    /** `identity_key`: the normalized issuer, the type, the normalized reference and the date (L-45). */
    public static function key(string $issuer, string $type, ?string $reference, string $date): string
    {
        return implode('|', [self::issuer($issuer), $type, self::reference($reference), $date]);
    }

    /**
     * FU-10's registration keys of one registration, in (namespace, key) order: its content hash, and its type-free
     * identity — whatever the company where a reference exists, the company's otherwise.
     *
     * @return list<array{int, int}>
     */
    public static function registrationKeys(string $sha256, string $issuer, ?string $reference, string $date, ?int $companyId): array
    {
        $normalized = self::reference($reference);
        $identity = $normalized !== ''
            ? implode('|', [self::issuer($issuer), $normalized, $date])
            : implode('|', [$companyId === null ? 'pool' : (string) $companyId, self::issuer($issuer), $date]);
        $keys = [[self::CONTENT_NAMESPACE, self::low32($sha256)], [self::IDENTITY_NAMESPACE, self::low32(hash('sha256', $identity))]];
        sort($keys);

        return $keys;
    }

    /**
     * The content key alone, for a new version, whose identity is its evidence's (NX-07).
     *
     * @return array{int, int}
     */
    public static function contentKey(string $sha256): array
    {
        return [self::CONTENT_NAMESPACE, self::low32($sha256)];
    }

    /** The low 32 bits of a SHA-256, as the signed integer `pg_advisory_lock` takes. */
    private static function low32(string $hex): int
    {
        $value = (int) hexdec(substr($hex, -8));

        return $value >= 0x80000000 ? $value - 0x100000000 : $value;
    }

    private static function compatible(string $text): string
    {
        $normalized = class_exists(Normalizer::class) ? Normalizer::normalize($text, Normalizer::FORM_KC) : $text;

        return is_string($normalized) ? $normalized : $text;
    }
}
