<?php

namespace App\Modules\IAM\Recovery;

use Carbon\CarbonImmutable;

/** One business audit event of an account, as AU-25's reading of the trail uses it. */
final class AuditEntry
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly int $id,
        public readonly string $action,
        public readonly ?int $actorUserId,
        public readonly CarbonImmutable $occurredAt,
        public readonly array $before,
        public readonly array $after,
        public readonly array $metadata,
    ) {}

    public static function fromRow(object $row): self
    {
        $value = fn (string $key): mixed => property_exists($row, $key) ? $row->{$key} : null;
        $actor = $value('actor_user_id');

        return new self(
            id: is_numeric($value('id')) ? (int) $value('id') : 0,
            action: is_string($value('action')) ? $value('action') : '',
            actorUserId: is_numeric($actor) ? (int) $actor : null,
            occurredAt: CarbonImmutable::parse(is_string($value('occurred_at')) ? $value('occurred_at') : 'now'),
            before: self::decode($value('before')),
            after: self::decode($value('after')),
            metadata: self::decode($value('metadata')),
        );
    }

    /** @return array<string, mixed> */
    public static function decode(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;
        $values = [];

        foreach (is_array($decoded) ? $decoded : [] as $key => $item) {
            $values[(string) $key] = $item;
        }

        return $values;
    }
}
