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
 * FUL's `DocumentSubjectProvider` for DOC-10, the *Berita Acara Pemeriksaan Barang* (WF-FUL-02; WF-DOC-01; TASK-045): a
 * recorded delivery with the client's inspection facts as its subject, drafted through ROUTE-221 from PG-031 and issued
 * on PG-036 (TASK-029; `DIR-081` OB-02). Its precondition is a delivery with a standing line whose inspection was
 * recorded — never a status or a print (BR-DOC-04; GAP-053). DOC-08's delivery arc, which `ServiceHandoverSubjects`
 * supplies, shares the subject and facts here.
 */
final class DeliverySubjects implements DocumentSubjectProvider
{
    public const INSPECTION = 'DOC-10';

    public function types(): array
    {
        return [self::INSPECTION => ['STANDARD']];
    }

    public function subject(Authority $viewer, string $typeCode, string $mode, array $records): ?DocumentSubject
    {
        $subject = self::find($viewer, $typeCode, $mode, $records['delivery_id'] ?? null);

        return $subject === null || DeliveryFacts::inspection(self::delivery($subject)) === null ? null : $subject;
    }

    public function locks(DocumentSubject $subject): LockSet
    {
        return self::lockOf($subject);
    }

    public function precondition(CommandContext $context, DocumentSubject $subject): void
    {
        self::ensure($subject, true);
    }

    public function facts(DocumentSubject $subject): DocumentFacts
    {
        return self::factsOf($subject, true);
    }

    /** A delivery by its public id, in a company where the viewer sees projects (PJ-15). */
    public static function find(Authority $viewer, string $typeCode, string $mode, mixed $publicId): ?DocumentSubject
    {
        $delivery = is_string($publicId) && preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1
            ? DB::table('deliveries')->where('public_id', strtolower($publicId))->first(['id', 'company_id']) : null;

        if ($delivery === null || ! $viewer->allows('projects.view', (int) Row::text($delivery, 'company_id'))) {
            return null;
        }

        return new DocumentSubject($typeCode, $mode, (int) Row::text($delivery, 'company_id'), ['delivery_id' => (int) Row::text($delivery, 'id')]);
    }

    public static function lockOf(DocumentSubject $subject): LockSet
    {
        $project = DB::table('deliveries')->where('id', self::delivery($subject))->value('project_id');

        return (new LockSet)->add('projects', ['id' => is_numeric($project) ? (int) $project : 0]);
    }

    /** A delivery with a line still standing, and for DOC-10 its inspection recorded: no fact otherwise that the paper could render. */
    public static function ensure(DocumentSubject $subject, bool $inspection): void
    {
        if ($inspection && DeliveryFacts::inspection(self::delivery($subject)) === null) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_inspection']);
        }

        if (self::factsOf($subject, $inspection)->lines === []) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_delivery']);
        }
    }

    public static function factsOf(DocumentSubject $subject, bool $inspection): DocumentFacts
    {
        return DeliveryFacts::of(self::delivery($subject), $inspection) ?? throw Refused::rejected('WF-DOC-01', ['reason' => $inspection ? 'no_inspection' : 'no_subject']);
    }

    private static function delivery(DocumentSubject $subject): int
    {
        return $subject->records['delivery_id'] ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }
}
