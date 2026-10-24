<?php

namespace App\Modules\OPS\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;
use LogicException;

/**
 * A page of a log read in keyset order (`occurred_at`, `id`) descending, newest first (DATABASE §20 IX-13),
 * with the opaque cursor of the next page. The cursor carries the stored instant at its full microsecond
 * precision, which the framework's cursor paginator would round to the second through the model's cast.
 *
 * @template TModel of Model
 */
final class KeysetPage
{
    /**
     * @param  Collection<int, TModel>  $items
     */
    private function __construct(public readonly Collection $items, public readonly ?string $nextCursor) {}

    /**
     * @template TPageModel of Model
     *
     * @param  Builder<TPageModel>  $query
     * @return self<TPageModel>
     */
    public static function of(Builder $query, int $perPage, ?string $cursor): self
    {
        $perPage = max(1, min($perPage, 100));

        if ($cursor !== null) {
            [$occurredAt, $id] = self::decode($cursor);
            $query->whereRaw('(occurred_at, id) < (cast(? as timestamptz), ?)', [$occurredAt, $id]);
        }

        $rows = $query->orderByDesc('occurred_at')->orderByDesc('id')->limit($perPage + 1)->get();
        $next = null;

        if ($rows->count() > $perPage) {
            $rows = $rows->take($perPage);
            $last = $rows->last();
            $occurredAt = $last?->getRawOriginal('occurred_at');
            $id = $last?->getKey();

            if (! is_string($occurredAt) || ! is_int($id)) {
                throw new LogicException('A log row has a stored instant and an integer id.');
            }

            $next = self::encode($occurredAt, $id);
        }

        return new self($rows->values(), $next);
    }

    private static function encode(string $occurredAt, int $id): string
    {
        return rtrim(strtr(base64_encode(json_encode([$occurredAt, $id], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /** @return array{string, int} */
    private static function decode(string $cursor): array
    {
        try {
            $decoded = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true, 2, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = null;
        }

        if (! is_array($decoded) || ! isset($decoded[0], $decoded[1]) || ! is_string($decoded[0]) || ! is_int($decoded[1])
            || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d{1,6})?[+-]\d{2}(:\d{2})?$/', $decoded[0]) !== 1) {
            throw new InvalidArgumentException('The page cursor is not valid.');
        }

        return [$decoded[0], $decoded[1]];
    }
}
