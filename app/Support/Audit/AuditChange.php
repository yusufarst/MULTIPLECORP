<?php

namespace App\Support\Audit;

use App\Support\Redaction;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Stringable;
use UnitEnum;

/**
 * The before/after of an audit event (BR-XC-01; DATABASE §23; SECURITY LG-01, LG-03): a created row has an
 * after only, an update keeps only the keys whose value changed, on both sides. Values are stored as JSON
 * scalars — an instant in ISO 8601 UTC, an enum as its value, a decimal as the string it already is —,
 * and a sensitive key keeps its place with its value redacted, so the change shows without the secret.
 */
final class AuditChange
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function __construct(public readonly ?array $before, public readonly ?array $after) {}

    /** A change with no before/after, such as a refusal's audit row (DATABASE §23 TECH-021). */
    public static function none(): self
    {
        return new self(null, null);
    }

    /**
     * A created row: an append-only fact is its own after.
     *
     * @param  array<string, mixed>  $after
     */
    public static function created(array $after): self
    {
        return new self(null, self::normalize($after));
    }

    /**
     * An update of a state or master row: the changed keys only, a key present on one side alone counted
     * as changed from or to null.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function updated(array $before, array $after): self
    {
        $changedBefore = [];
        $changedAfter = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            // A sensitive value is compared as given; only its mark is kept.
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if (! Redaction::isSensitiveKey($key)) {
                $old = self::value($old);
                $new = self::value($new);
            }

            if ($old !== $new) {
                $changedBefore[$key] = $old;
                $changedAfter[$key] = $new;
            }
        }

        return new self(self::normalize($changedBefore), self::normalize($changedAfter));
    }

    /** True when an update changed nothing. */
    public function isEmpty(): bool
    {
        return $this->before === [] && $this->after === [];
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private static function normalize(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (! is_string($key) || $key === '') {
                throw new InvalidArgumentException('An audited value is named by a non-empty string key.');
            }

            $normalized[$key] = Redaction::isSensitiveKey($key) ? Redaction::MARK : self::value($value);
        }

        ksort($normalized);

        return $normalized;
    }

    private static function value(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value), is_string($value) => $value,
            $value instanceof DateTimeInterface => DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.up'),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof Stringable => (string) $value,
            is_array($value) => array_is_list($value) ? array_map(self::value(...), $value) : self::normalize($value),
            default => throw new InvalidArgumentException(sprintf('An audited value is a scalar, an instant, an enum or an array, not %s; money and quantities are decimal strings.', get_debug_type($value))),
        };
    }
}
