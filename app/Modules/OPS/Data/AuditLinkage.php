<?php

namespace App\Modules\OPS\Data;

use App\Modules\OPS\Enums\AuditRelation;
use InvalidArgumentException;

/**
 * The linkage of an audit event (BR-XC-01; BR-CR-01; SECURITY LG-01): the records the change affects and
 * the original a correction revises, voids, reverses, returns or supersedes, each by its entity type and
 * public id, and the correction case it belongs to. An append-only fact's correction is a linked row, so
 * the event references the original rather than copying it (DATABASE §23). Stored in the event's metadata.
 */
final class AuditLinkage
{
    /** @var list<array{relation: string, entity_type: string, public_id: string}> */
    private array $links = [];

    private ?string $correctionCase = null;

    public static function make(): self
    {
        return new self;
    }

    public function link(AuditRelation $relation, string $entityType, string $publicId): self
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $entityType) !== 1 || ! self::isPublicId($publicId)) {
            throw new InvalidArgumentException('A linked record is named by its table name and its public id.');
        }

        $link = ['relation' => $relation->value, 'entity_type' => $entityType, 'public_id' => strtolower($publicId)];

        if (! in_array($link, $this->links, true)) {
            $this->links[] = $link;
        }

        return $this;
    }

    public function correctionCase(string $publicId): self
    {
        if (! self::isPublicId($publicId)) {
            throw new InvalidArgumentException('A correction case is named by its public id.');
        }

        $this->correctionCase = strtolower($publicId);

        return $this;
    }

    /**
     * The metadata entries: `links` and `correction_case_public_id`, each only when present.
     *
     * @return array{links?: list<array{relation: string, entity_type: string, public_id: string}>, correction_case_public_id?: string}
     */
    public function toArray(): array
    {
        $linkage = [];

        if ($this->links !== []) {
            $linkage['links'] = $this->links;
        }

        if ($this->correctionCase !== null) {
            $linkage['correction_case_public_id'] = $this->correctionCase;
        }

        return $linkage;
    }

    private static function isPublicId(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
