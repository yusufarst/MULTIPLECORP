<?php

namespace Tests\Support;

use App\Modules\DOC\Actions\IssueDocument;
use App\Modules\DOC\Actions\SaveDocumentDraft;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Models\Document;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\Documents\SyntheticSubjects;

/** The document engine's commands as the integration races run them, each answering its outcome as a word (TASK-029). */
final class DocumentRaces
{
    /** A project's draft of a type, prepared through ROUTE-221's action; the document. */
    public static function prepare(int $actorId, int $companyId, int $projectId, string $type = 'DOC-09'): Document
    {
        SyntheticSubjects::register();
        $outcome = app(SaveDocumentDraft::class)->prepare((string) Str::uuid(), $actorId, new DocumentSubject($type, 'STANDARD', $companyId, ['project_id' => $projectId]));

        return Document::query()->findOrFail($outcome->resultId);
    }

    public static function issue(int $actorId, int $documentId, ?string $commandId = null): string
    {
        SyntheticSubjects::register();
        $document = Document::query()->findOrFail($documentId);
        $lockVersion = (int) DB::table('document_versions')->where('document_id', $documentId)->where('state', 'DRAFT')->value('lock_version');

        return self::word(app(IssueDocument::class)->run($commandId ?? (string) Str::uuid(), $actorId, $document, $lockVersion, BusinessDate::today(), true, null));
    }

    /** The issue committed only once the other session waits on the document it holds. */
    public static function holdThenIssue(int $actorId, int $documentId): string
    {
        return DB::transaction(function () use ($actorId, $documentId): string {
            $word = self::issue($actorId, $documentId);
            Barrier::await('the first issue is written');
            Sessions::awaitBlocked();

            return $word;
        });
    }

    private static function word(Outcome $outcome): string
    {
        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : $outcome->code.' '.json_encode($outcome->state)));
    }
}
