<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Models\DocumentRendition;
use App\Support\Command\LockPosition;
use App\Support\Command\LockSet;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The rendition attempts of JB-01 (CONCURRENCY_IDEMPOTENCY §15; SF-RENDER; ARCHITECTURE §11), each step one
 * TX-04 transaction under the document's lock (LK-30): the claim, the failure and the finish. Rendering
 * happens between them, outside every transaction, from the issued payload alone. Nothing here touches the
 * version, its number, its period or a counter (BR-DOC-03; NM-07): a retry adds an attempt and never reissues,
 * and one READY attempt per version is a key (C-12), so two claimants can never yield a second document.
 */
final class Renditions
{
    /** JB-02's waits before a FAILED attempt is followed, by attempt number, in minutes. */
    public const BACKOFF_MINUTES = [1 => 1, 2 => 5, 3 => 15, 4 => 60];

    public function __construct(private readonly FileStore $store) {}

    public static function attempts(): int
    {
        return config()->integer('files.renditions.attempts', 5);
    }

    /**
     * Claims the attempt a job was dispatched for, or the one that follows it: a PENDING attempt becomes
     * RENDERING; a FAILED attempt that is due is followed by the next, inserted as RENDERING; a RENDERING one —
     * a dead worker's — is closed as FAILED and followed the same way. An attempt that is READY, no longer the
     * version's latest, or the last allowed ends the job with nothing claimed.
     */
    public function claim(int $renditionId): ?DocumentRendition
    {
        return DB::transaction(function (ConnectionInterface $connection) use ($renditionId): ?DocumentRendition {
            $attempt = DocumentRendition::query()->find($renditionId);

            if ($attempt === null || ! $this->lockDocument($connection, $attempt->document_version_id, [])) {
                return null;
            }

            $attempt->refresh();
            $latest = self::int(DocumentRendition::query()->where('document_version_id', $attempt->document_version_id)->max('attempt_no'));

            if ($attempt->attempt_no !== $latest || $attempt->state === 'READY') {
                return null;
            }

            $now = CarbonImmutable::now();

            if ($attempt->state === 'PENDING') {
                $attempt->forceFill(['state' => 'RENDERING', 'updated_at' => $now])->save();

                return $attempt;
            }

            if ($attempt->state === 'RENDERING') {
                $attempt->forceFill(['state' => 'FAILED', 'error_summary' => 'Abandoned by its worker; taken over.', 'updated_at' => $now])->save();
                Log::warning('A rendition attempt abandoned by its worker was taken over (JB-01).', ['rendition' => $attempt->id]);
            } elseif (! self::isDue($attempt, $now)) {
                return null;
            }

            if ($attempt->attempt_no >= self::attempts()) {
                self::alertExhausted($attempt);

                return null;
            }

            $next = new DocumentRendition;
            $next->forceFill([
                'document_version_id' => $attempt->document_version_id,
                'attempt_no' => $attempt->attempt_no + 1,
                'state' => 'RENDERING',
                'updated_at' => $now,
            ])->save();

            return $next;
        });
    }

    /** Closes a claimed attempt as FAILED; FAILED stays visible (QS-08), and after the last the operator is told. */
    public function fail(DocumentRendition $attempt, string $summary): void
    {
        DB::transaction(function (ConnectionInterface $connection) use ($attempt, $summary): void {
            $this->lockDocument($connection, $attempt->document_version_id, []);
            $failed = DocumentRendition::query()->whereKey($attempt->id)->where('state', 'RENDERING')
                ->update(['state' => 'FAILED', 'error_summary' => mb_substr($summary, 0, 500), 'updated_at' => CarbonImmutable::now()]);

            Log::error('A rendition attempt failed (JB-01).', ['rendition' => $attempt->id, 'attempt' => $attempt->attempt_no, 'summary' => $summary]);

            if ($failed === 1 && $attempt->attempt_no >= self::attempts()) {
                self::alertExhausted($attempt);
            }
        });
    }

    /**
     * Finishes a claimed attempt: the rendered file registered and the attempt made READY in one TX-04 step,
     * under LK-01 for the subject's project and the projects of the requirements linked to the version, then
     * LK-30 (§5). The update is conditional, so a superseded attempt cannot pass: it writes nothing, and its bytes
     * are removed. Answers whether the attempt became READY.
     */
    public function finish(DocumentRendition $attempt, string $pdf, string $rendererVersion): bool
    {
        $companyId = self::int(DB::table('document_versions')->where('id', $attempt->document_version_id)->value('company_id'));
        $key = $this->store->newKey();
        $this->store->write($key, $pdf);
        $projects = $this->projectsOf($attempt->document_version_id);

        for ($round = 0; $round < 5; $round++) {
            try {
                $done = $this->finishStep($attempt, $pdf, $rendererVersion, $companyId, $key, $projects);
            } catch (RenditionSuperseded) {
                $this->store->disk()->delete($key);

                return false;
            } catch (Throwable $failure) {
                // Nothing references the bytes of a step that did not commit (ER-06).
                $this->store->disk()->delete($key);

                throw $failure;
            }

            if ($done) {
                return true;
            }
        }

        $this->store->disk()->delete($key);

        return false;
    }

    /**
     * @param  list<int>  $projects  grown by a restart (§5)
     */
    private function finishStep(DocumentRendition $attempt, string $pdf, string $rendererVersion, int $companyId, string $key, array &$projects): bool
    {
        return DB::transaction(function (ConnectionInterface $connection) use ($attempt, $pdf, $rendererVersion, $companyId, $key, &$projects): bool {
            $this->lockDocument($connection, $attempt->document_version_id, $projects);
            $required = $this->projectsOf($attempt->document_version_id);

            if (array_diff($required, $projects) !== []) {
                // A project joined the set meanwhile: restart with the union, before anything is written (§5).
                $projects = array_values(array_unique([...$projects, ...$required]));

                return false;
            }

            $file = $this->store->register($key, [
                'purpose' => FilePurpose::Rendition->value,
                'mime_type' => 'application/pdf',
                'sha256' => hash('sha256', $pdf),
                'size_bytes' => strlen($pdf),
                'company_id' => $companyId,
                'uploaded_by' => null,
                'original_filename' => null,
                'sensitivity' => FilePurpose::Rendition->sensitivity(),
            ]);

            $ready = DocumentRendition::query()->whereKey($attempt->id)->where('state', 'RENDERING')->update([
                'state' => 'READY',
                'file_object_id' => $file->id,
                'renderer_version' => $rendererVersion,
                'error_summary' => null,
                'updated_at' => CarbonImmutable::now(),
            ]);

            if ($ready !== 1) {
                // Superseded: the whole step rolls back, the file's row with it.
                throw new RenditionSuperseded;
            }

            return true;
        }, 3);
    }

    /**
     * The projects whose lock a finished rendition takes (LK-01; §5's document row): the subject's — directly or
     * through the subject record — and those of the administrative requirements linked to the version.
     *
     * @return list<int>
     */
    public function projectsOf(int $versionId): array
    {
        $rows = DB::select(<<<'SQL'
            select distinct project_id from (
                select d.project_id from documents d join document_versions v on v.document_id = d.id where v.id = :v1
                union all select p.project_id from document_versions v join documents d on d.id = v.document_id join purchases p on p.id = d.purchase_id where v.id = :v2
                union all select m.project_id from document_versions v join documents d on d.id = v.document_id join stock_movements m on m.id in (d.dispatch_movement_id, d.receiving_movement_id) where v.id = :v3
                union all select c.project_id from document_versions v join documents d on d.id = v.document_id join dropship_confirmations c on c.id = d.dropship_confirmation_id where v.id = :v4
                union all select i.project_id from document_versions v join documents d on d.id = v.document_id join invoices i on i.id = d.invoice_id where v.id = :v5
                union all select x.project_id from document_versions v join documents d on d.id = v.document_id join deliveries x on x.id = d.delivery_id where v.id = :v6
                union all select h.project_id from document_versions v join documents d on d.id = v.document_id join service_handovers h on h.id = d.service_handover_id where v.id = :v7
                union all select pd.project_id from document_versions v join documents d on d.id = v.document_id join documents pd on pd.id = d.parent_document_id where v.id = :v8
                union all select r.project_id from admin_requirement_links l join admin_requirements r on r.id = l.admin_requirement_id where l.document_version_id = :v9 and l.unlinked_at is null
            ) s where project_id is not null order by project_id
            SQL, array_fill_keys(['v1', 'v2', 'v3', 'v4', 'v5', 'v6', 'v7', 'v8', 'v9'], $versionId));

        $projects = [];

        foreach ($rows as $row) {
            $projects[] = self::int(is_object($row) ? ($row->project_id ?? null) : null);
        }

        return $projects;
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /** Whether the version's latest attempt is owed to the sweeper now (JB-02). */
    public static function isDue(DocumentRendition $attempt, CarbonImmutable $now): bool
    {
        $age = $attempt->recorded_at->diffInSeconds($now);

        return match ($attempt->state) {
            'PENDING' => $age >= 120,
            'RENDERING' => $age >= 600,
            'FAILED' => $attempt->attempt_no < self::attempts() && $age >= 60 * (self::BACKOFF_MINUTES[$attempt->attempt_no] ?? 60),
            default => false,
        };
    }

    /**
     * Locks the projects, then the document, its version and its attempts in canonical order (LK-01, LK-30).
     *
     * @param  list<int>  $projects
     */
    private function lockDocument(ConnectionInterface $connection, int $versionId, array $projects): bool
    {
        $documentId = $connection->table('document_versions')->where('id', $versionId)->value('document_id');

        if ($documentId === null) {
            return false;
        }

        $set = (new LockSet)->add('documents', ['id' => self::int($documentId)])->add('document_versions', ['id' => $versionId]);

        foreach ($projects as $project) {
            $set->add('projects', ['id' => $project]);
        }

        $set->acquire($connection, new LockPosition);
        $connection->table('document_renditions')->where('document_version_id', $versionId)->orderBy('id')->lock('for no key update')->pluck('id');

        return true;
    }

    private static function alertExhausted(DocumentRendition $attempt): void
    {
        // HO-25: the operator is alerted; MSG-08's exhausted variant tells the user so (IP-12).
        Log::critical('A document rendition failed its last attempt; the operator is alerted (JB-01; HO-25).', ['rendition' => $attempt->id, 'document_version' => $attempt->document_version_id]);
    }
}
