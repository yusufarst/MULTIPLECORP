<?php

namespace App\Modules\OPS\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\OPS\Services\Corrections;
use App\Support\Audit\AuditRelation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A record's corrections as every record page shows them (BR-CR-02; ADMIN_FLOW §11; criterion 4): "Mengoreksi" — the
 * originals this record corrects — and "Dikoreksi oleh" — the records that correct it —, each with the relation, the
 * reason, the actor and the time the correction's audit event keeps (BR-CR-01). The calling page has already
 * authorized the record; each linked record is named by its type and public identifier only, opened by its own page,
 * and an event of a company outside the viewer's scope is not read (PJ-11).
 */
final class CorrectionTrail
{
    /**
     * @return array{corrects: list<array<string, mixed>>, corrected_by: list<array<string, mixed>>}
     */
    public function of(Authority $viewer, string $entityType, string $publicId): array
    {
        $publicId = strtolower($publicId);
        $relations = array_map(fn (AuditRelation $relation): string => $relation->value, Corrections::CORRECTING);
        $correctedBy = [];

        $named = DB::table('audit_events as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
            ->whereRaw("a.metadata->'links' @> ?::jsonb", [json_encode([['entity_type' => $entityType, 'public_id' => $publicId]], JSON_THROW_ON_ERROR)])
            // PJ-11: another company's correction is invisible; a pool event, of no company, stays.
            ->when(! $viewer->scope->all, fn (Builder $query) => $query->where(fn (Builder $scoped) => $scoped->whereIn('a.company_id', $viewer->scope->companyIds)->orWhereNull('a.company_id')))
            ->orderBy('a.occurred_at')->orderBy('a.id')
            ->get(['a.entity_type', 'a.entity_public_id', 'a.reason', 'a.occurred_at', 'a.metadata', 'u.name']);

        foreach ($named as $event) {
            foreach (self::links($event->metadata ?? null) as $link) {
                if ($link['entity_type'] === $entityType && $link['public_id'] === $publicId && in_array($link['relation'], $relations, true)) {
                    $correctedBy[] = self::entry($event, $link['relation'], self::text($event->entity_type ?? null), self::text($event->entity_public_id ?? null));
                }
            }
        }

        $corrects = [];
        $own = DB::table('audit_events as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
            ->where('a.entity_type', $entityType)->where('a.entity_public_id', $publicId)->whereRaw("a.metadata->'links' is not null")
            ->orderBy('a.occurred_at')->orderBy('a.id')
            ->get(['a.reason', 'a.occurred_at', 'a.metadata', 'u.name']);

        foreach ($own as $event) {
            foreach (self::links($event->metadata ?? null) as $link) {
                if (in_array($link['relation'], $relations, true)) {
                    $corrects[] = self::entry($event, $link['relation'], $link['entity_type'], $link['public_id']);
                }
            }
        }

        return ['corrects' => $corrects, 'corrected_by' => $correctedBy];
    }

    /** @return list<array{relation: string, entity_type: string, public_id: string}> */
    private static function links(mixed $metadata): array
    {
        $decoded = is_string($metadata) ? json_decode($metadata, true) : null;
        $links = [];

        foreach (is_array($decoded) && is_array($decoded['links'] ?? null) ? $decoded['links'] : [] as $link) {
            if (is_array($link) && is_string($link['relation'] ?? null) && is_string($link['entity_type'] ?? null) && is_string($link['public_id'] ?? null)) {
                $links[] = ['relation' => $link['relation'], 'entity_type' => $link['entity_type'], 'public_id' => $link['public_id']];
            }
        }

        return $links;
    }

    /** @return array<string, mixed> */
    private static function entry(object $event, string $relation, string $type, string $publicId): array
    {
        return [
            'relation' => $relation,
            'type' => $type,
            'id' => $publicId,
            'reason' => is_string($event->reason ?? null) ? $event->reason : null,
            'by' => is_string($event->name ?? null) ? $event->name : null,
            'at' => self::text($event->occurred_at ?? null),
        ];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
