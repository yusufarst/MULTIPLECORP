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
 * FUL's `DocumentSubjectProvider` for DOC-08, the BAST (WF-FUL-02; WF-FUL-04; WF-DOC-01; TASK-047; TASK-045): a recorded
 * service handover, or a recorded delivery, as its subject, drafted through ROUTE-221 and issued on PG-036 (TASK-029). Its
 * precondition is a handover or an accepted delivery with a standing line — never a status or a print (BR-DOC-04;
 * GAP-053); its paper the record's confirmed facts, nothing typed again. Generating it moves no stock, cash or obligation
 * (L-19). A type and mode have one supplier, so the delivery's arc of the type lives here.
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
        if (isset($records['delivery_id'])) {
            return DeliverySubjects::find($viewer, $typeCode, $mode, $records['delivery_id']);
        }

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
        if (isset($subject->records['delivery_id'])) {
            return DeliverySubjects::lockOf($subject);
        }

        $project = DB::table('service_handovers')->where('id', self::handover($subject))->value('project_id');

        return (new LockSet)->add('projects', ['id' => is_numeric($project) ? (int) $project : 0]);
    }

    /** A handover recorded with a line still standing: one wholly reversed is no fact a BAST could render (CM-27). */
    public function precondition(CommandContext $context, DocumentSubject $subject): void
    {
        if (isset($subject->records['delivery_id'])) {
            DeliverySubjects::ensure($subject, false);

            return;
        }

        if ($this->facts($subject)->lines === []) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_handover']);
        }
    }

    public function facts(DocumentSubject $subject): DocumentFacts
    {
        if (isset($subject->records['delivery_id'])) {
            return DeliverySubjects::factsOf($subject, false);
        }

        return HandoverFacts::of([self::handover($subject)])[self::handover($subject)] ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }

    private static function handover(DocumentSubject $subject): int
    {
        return $subject->records['service_handover_id'] ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }
}
