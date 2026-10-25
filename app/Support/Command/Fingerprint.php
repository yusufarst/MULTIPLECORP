<?php

namespace App\Support\Command;

use BackedEnum;
use DateTimeInterface;
use InvalidArgumentException;
use Normalizer;

/**
 * The payload fingerprint of CONCURRENCY_IDEMPOTENCY CI-02: `v1:` and the SHA-256, in hex, of the canonical JSON
 * of the command type, the company's and targets' public identifiers as submitted and the validated, typed
 * payload — `business_date` and whether it was only prefilled, versions, reasons and file identifiers included.
 * Canonical: UTF-8, keys sorted, no insignificant white space, strings in NFC, decimals as the plain strings
 * they already are, dates in ISO form, arrays in payload order, nulls explicit. The `command_id`, the CSRF
 * token and presentation-only fields are left out by the caller; the payload itself is never stored.
 */
final class Fingerprint
{
    public const VERSION = 'v1';

    /**
     * @param  array<string, mixed>  $payload  the validated, typed payload
     */
    public static function of(string $commandType, ?string $companyPublicId, array $payload): string
    {
        $canonical = self::canonical(['command_type' => $commandType, 'company' => $companyPublicId, 'payload' => $payload]);

        return self::VERSION.':'.hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function canonical(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value) => $value,
            is_string($value) => Normalizer::normalize($value, Normalizer::FORM_C),
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            is_array($value) && array_is_list($value) => array_map(self::canonical(...), $value),
            is_array($value) => self::sorted($value),
            default => throw new InvalidArgumentException(sprintf('A fingerprinted value is a scalar, a date, an enum or an array, not %s; decimals are strings (CI-02).', get_debug_type($value))),
        };
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private static function sorted(array $value): array
    {
        $sorted = [];

        foreach ($value as $key => $item) {
            $sorted[(string) $key] = self::canonical($item);
        }

        ksort($sorted, SORT_STRING);

        return $sorted;
    }
}
