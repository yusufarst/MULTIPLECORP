<?php

namespace App\Modules\FUL\Services;

use App\Modules\DOC\Contracts\DocumentSubjectProvider;
use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\IAM\Data\Authority;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * FUL's `DocumentSubjectProvider` for DOC-08, the BAST (WF-FUL-04; WF-DOC-01; TASK-047): a recorded service handover as
 * its subject, drafted and issued in place on PG-033 through ROUTE-221 and ROUTE-222 (TASK-029). Its precondition is a
 * handover with a standing line — never a status or a print (BR-DOC-04; GAP-053); its paper the handover's confirmed
 * facts, nothing typed again. Generating it moves no stock, cash or obligation (L-19). The delivery's arc of the type is
 * TASK-045's, which extends this one provider: a type and mode have one supplier.
 */
final class ServiceHandoverSubjects implements DocumentSubjectProvider
{
    public const BAST = 'DOC-08';

    public function types(): array
    {
        return [self::BAST => ['STANDARD']];
    }

    public function subject(Authority $viewer, string $typeCode, string $mode, array $records): ?DocumentSubject
    {
        $publicId = $records['service_handover_id'] ?? null;
        $handover = is_string($publicId) && preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1
            ? DB::table('service_handovers')->where('public_id', strtolower($publicId))->first(['id', 'company_id']) : null;

        if ($handover === null || ! $viewer->allows('projects.view', (int) Row::text($handover, 'company_id'))) {
            return null;
        }

        return new DocumentSubject($typeCode, $mode, (int) Row::text($handover, 'company_id'), ['service_handover_id' => (int) Row::text($handover, 'id')]);
    }

    public function locks(DocumentSubject $subject): LockSet
    {
        $project = DB::table('service_handovers')->where('id', self::handover($subject))->value('project_id');

        return (new LockSet)->add('projects', ['id' => is_numeric($project) ? (int) $project : 0]);
    }

    /** A handover recorded with a line still standing: one wholly reversed is no fact a BAST could render (CM-27). */
    public function precondition(CommandContext $context, DocumentSubject $subject): void
    {
        if ($this->facts($subject)->lines === []) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_handover']);
        }
    }

    public function facts(DocumentSubject $subject): DocumentFacts
    {
        return HandoverFacts::of([self::handover($subject)])[self::handover($subject)] ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }

    private static function handover(DocumentSubject $subject): int
    {
        return $subject->records['service_handover_id'] ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }
}
