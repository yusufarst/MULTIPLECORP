<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Data\FileFamilies;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Queries\EvidenceMatches;
use App\Modules\IAM\Data\Authority;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandLog;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Command\TransactionClass;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * The steps NX-07's registration and versioning share (SF-EVIDENCE; FL-01; FL-10; BD-14; BD-15; DP-13; IP-05):
 * FU-10's registration keys taken at LK-28 on the request's session before its transaction — in a short step of their
 * own under TX-01's lock wait — and released when it has ended; the transaction's check that it runs on that session
 * (SQ-16); an upload referenced only by the account that made it, for its purpose and company, never yet registered
 * (DIR-089 (5)); the FL-10 matches split into those the actor may view, a warning needing `warnings.override` with its
 * reason that names them (IP-05), and the others, which change nothing in the answer and reach the Owner as
 * EVIDENCE_DUPLICATE_UNDISCLOSED once the registration has committed (LG-02; GAP-034); and a version's rows.
 */
final class EvidenceRegistry
{
    private ?int $sessionPid = null;

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly CommandLog $log,
        private readonly EvidenceMatches $matches,
        private readonly AuditRecorder $audit,
        private readonly SecurityEventRecorder $events,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public function request(string $commandId, string $type, int $actorUserId, ?int $companyId, ?string $companyPublicId, array $payload): CommandRequest
    {
        return new CommandRequest($commandId, $type, $actorUserId, $companyId, 'evidence.upload', Fingerprint::of($type, $companyPublicId, $payload));
    }

    /**
     * Runs the attempt holding FU-10's keys (LK-28), in (namespace, key) order, released once its transaction ended.
     *
     * @param  list<array{int, int}>  $keys
     * @param  Closure(): Outcome  $work
     */
    public function withKeys(array $keys, Closure $work): Outcome
    {
        sort($keys);
        $connection = DB::connection();
        $taken = [];

        try {
            $connection->transaction(function () use ($connection, $keys, &$taken): void {
                $connection->statement("set local lock_timeout = '".TransactionClass::Interactive->lockTimeout()."'");

                foreach ($keys as $key) {
                    $connection->select('select pg_advisory_lock(?::integer, ?::integer)', $key);
                    $taken[] = $key;
                }
            });
            $this->sessionPid = self::pid();

            return $work();
        } catch (QueryException $error) {
            if ($error->getCode() === '55P03') {
                return Outcome::failed('TX-01 registration keys');
            }

            throw $error;
        } finally {
            $this->sessionPid = null;

            foreach ($taken as $key) {
                try {
                    $connection->select('select pg_advisory_unlock(?::integer, ?::integer)', $key);
                } catch (Throwable) {
                    // A lost session released its keys with it (FU-10).
                }
            }
        }
    }

    /**
     * @param  Closure(CommandContext): CommandResult  $action
     */
    public function run(CommandRequest $request, Closure $action): Outcome
    {
        return $this->envelope->run($request, $action);
    }

    /**
     * CI-05 before any check of the request: an identifier already recorded answers its recorded outcome — or the
     * conflict of other content — through the envelope's replay, so a replay is never refused by a check its own first
     * execution changed, such as its upload now being a version.
     */
    public function replay(CommandRequest $request): ?Outcome
    {
        if ($this->log->probe($request->commandId) === null) {
            return null;
        }

        return $this->envelope->run($request, fn (): never => throw new LogicException('A recorded command replays without running (CI-05).'));
    }

    /** FU-10: the attempt's transaction runs on the session that holds the keys, or it ends (SQ-16). */
    public function confirmSession(): void
    {
        if ($this->sessionPid === null || self::pid() !== $this->sessionPid) {
            throw new RuntimeException('The registration keys are held by another session (FU-10; SQ-16).');
        }
    }

    /** PJ-08 under the re-read authority: the actor still handles the type in the company — `null` for the pool. */
    public function ensureHandles(CommandContext $context, string $type, ?int $companyId): Authority
    {
        $authority = $context->authority();

        if ($authority === null || ! EvidenceTypes::mayHandle($authority, $type, $companyId)) {
            throw new AuthorizationException;
        }

        return $authority;
    }

    /**
     * DIR-089 (5): an upload of this actor, for evidence, of the company — no company for pool evidence —, not yet
     * any evidence's version; anything else answers as unknown.
     */
    public function ownUpload(int $actorUserId, string $publicId, ?int $companyId): FileObject
    {
        $valid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $publicId) === 1;
        $file = $valid ? FileObject::query()->where('public_id', strtolower($publicId))->where('uploaded_by', $actorUserId)
            ->where('purpose', 'UPLOAD')->whereRaw('company_id is not distinct from ?', [$companyId])
            ->whereRaw('not exists (select from evidence_document_versions v where v.file_object_id = file_objects.id)')
            ->first() : null;

        return $file ?? throw (new ModelNotFoundException)->setModel(FileObject::class, [$publicId]);
    }

    /**
     * The FL-10 matches of an upload, split under DP-13 into those the actor may view and the others.
     *
     * @return array{visible: list<array{basis: string, kind: string, record_id: int, public_id: string, company_id: int|null, type: string}>, hidden: list<array{basis: string, kind: string, record_id: int, public_id: string, company_id: int|null, type: string}>, reproduction: bool}
     */
    public function matches(Authority $actor, FileObject $file, string $issuer, string $type, ?string $reference, string $date, ?string $amount, ?int $companyId, ?int $evidenceId = null): array
    {
        $visible = [];
        $hidden = [];
        $reproduction = false;

        foreach ($this->matches->of($file->sha256, $file->id, $issuer, $type, $reference, $date, $amount, $companyId, $evidenceId) as $match) {
            $reproduction = $reproduction || $match['kind'] === 'rendition';

            if ($match['kind'] === 'rendition') {
                $capability = FileFamilies::document($match['type']);
                $sees = $capability !== null && $match['company_id'] !== null && $actor->allows($capability, $match['company_id']);
            } else {
                $sees = EvidenceTypes::mayView($actor, $match['type'], $match['company_id']);
            }

            if ($sees) {
                $visible[] = $match;
            } else {
                $hidden[] = $match;
            }
        }

        return ['visible' => $visible, 'hidden' => $hidden, 'reproduction' => $reproduction];
    }

    /**
     * IP-05 under the re-read authority: the visible matches refuse the first submission with the matches; the
     * second, by an actor holding `warnings.override`, names each of them and its reason, else it is refused again
     * with the current list. Answers whether the registration goes on as an override.
     *
     * @param  list<array{public_id: string}>  $visible
     * @param  list<string>  $acknowledged
     */
    public function warn(Authority $authority, ?int $companyId, array $visible, array $acknowledged, ?string $reason): bool
    {
        if ($visible === []) {
            return false;
        }

        $ids = array_values(array_unique(array_column($visible, 'public_id')));
        sort($ids);
        $override = $companyId === null ? $authority->has('warnings.override') : $authority->allows('warnings.override', $companyId);

        if (! $override || array_diff($ids, $acknowledged) !== [] || $reason === null || trim($reason) === '') {
            throw Refused::rejected('FL-10', ['matches' => $ids, 'override' => $override, 'labels' => $this->labels($visible)]);
        }

        return true;
    }

    /**
     * What IP-05's block shows of each visible match — its type, reference, date and branch, or a rendition's number —,
     * read for the matches the actor may view only (DP-13).
     *
     * @param  list<array{public_id: string}>  $visible
     * @return list<array{id: string, kind: string, type: string, reference: string|null, date: string|null, branch: string|null}>
     */
    private function labels(array $visible): array
    {
        $ids = array_column($visible, 'public_id');
        $evidence = DB::table('evidence_documents as e')->leftJoin('companies as c', 'c.id', '=', 'e.company_id')->whereIn('e.public_id', $ids)
            ->get(['e.public_id', 'e.evidence_type', 'e.issuer_reference', 'e.document_date', 'c.public_id as company']);
        $renditions = DB::table('document_versions as v')->join('companies as c', 'c.id', '=', 'v.company_id')->whereIn('v.public_id', $ids)
            ->get(['v.public_id', 'v.document_type_code', 'v.formatted_number', 'v.business_date', 'c.public_id as company']);
        $labels = [];

        foreach ($evidence as $row) {
            $labels[] = ['id' => self::text($row->public_id), 'kind' => 'evidence', 'type' => self::text($row->evidence_type), 'reference' => is_string($row->issuer_reference) ? $row->issuer_reference : null,
                'date' => substr(self::text($row->document_date), 0, 10), 'branch' => is_string($row->company) ? $row->company : 'bersama'];
        }

        foreach ($renditions as $row) {
            $labels[] = ['id' => self::text($row->public_id), 'kind' => 'rendition', 'type' => self::text($row->document_type_code), 'reference' => is_string($row->formatted_number) ? $row->formatted_number : null,
                'date' => substr(self::text($row->business_date), 0, 10), 'branch' => null];
        }

        usort($labels, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        return $labels;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * An override is ADM_PLUS under its acknowledged action, for QS-21 and the Owner's review (AZ-10; QS-14).
     *
     * @param  list<array{basis: string, public_id: string}>  $visible
     */
    public function recordOverride(CommandContext $context, EvidenceDocument $evidence, array $visible, string $reason): void
    {
        $this->audit->record(
            action: 'DOC.EvidenceDuplicateOverridden',
            authorityClass: AuthorityClass::AdmPlus,
            actorUserId: $context->request->actorUserId,
            companyId: $evidence->company_id,
            entityType: 'evidence_document',
            entityId: $evidence->id,
            entityPublicId: $evidence->public_id,
            reason: trim($reason),
            commandId: $context->request->commandId,
            metadata: ['matches' => array_values(array_unique(array_column($visible, 'public_id'))), 'bases' => array_values(array_unique(array_column($visible, 'basis')))],
        );
    }

    /**
     * The matches the actor may not view, each EVIDENCE_DUPLICATE_UNDISCLOSED once the registration has committed and
     * after the response, the same answer whether or not one is written (FL-10; LG-02; DP-13).
     *
     * @param  list<array{basis: string, kind: string, public_id: string, company_id: int|null}>  $hidden
     */
    public function announceHidden(CommandContext $context, EvidenceDocument $evidence, array $hidden): void
    {
        if ($hidden === []) {
            return;
        }

        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $context->afterCommit(fn () => defer(function () use ($hidden, $actor, $commandId, $evidence): void {
            foreach ($hidden as $match) {
                $this->events->recordUndisclosed(
                    SecurityEventKind::EvidenceDuplicateUndisclosed,
                    userId: $actor,
                    commandId: $commandId,
                    targetEntityType: 'evidence_document',
                    targetPublicId: $evidence->public_id,
                    relatedEntityType: $match['kind'] === 'rendition' ? 'document_version' : 'evidence_document',
                    relatedPublicId: $match['public_id'],
                    companyId: $evidence->company_id,
                    relatedCompanyId: $match['company_id'],
                    reasonCode: $match['basis'],
                );
            }
        })->always());
    }

    /** A version's row — the next number of its evidence —, its file the actor's own upload. */
    public function addVersion(CommandContext $context, EvidenceDocument $evidence, FileObject $file, ?string $reason, CarbonImmutable $now): EvidenceDocumentVersion
    {
        $last = EvidenceDocumentVersion::query()->where('evidence_document_id', $evidence->id)->max('version_no');
        $number = (is_numeric($last) ? (int) $last : 0) + 1;
        $version = new EvidenceDocumentVersion;
        $version->forceFill([
            'evidence_document_id' => $evidence->id,
            'version_no' => $number,
            'file_object_id' => $file->id,
            'reason' => $reason === null || trim($reason) === '' ? null : trim($reason),
            'uploaded_by' => $context->request->actorUserId,
            'command_id' => $context->request->commandId,
            'command_line_no' => $number,
            'recorded_at' => $now,
        ])->save();

        return $version;
    }

    private static function pid(): int
    {
        $pid = DB::scalar('select pg_backend_pid()');

        return is_numeric($pid) ? (int) $pid : 0;
    }
}
