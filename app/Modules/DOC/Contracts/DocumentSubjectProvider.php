<?php

namespace App\Modules\DOC\Contracts;

use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\IAM\Data\Authority;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;

/**
 * The inversion contract of ARCHITECTURE §3 through which an issuing module supplies the record a layout renders
 * (WF-DOC-01's table; TASK-029): the subject its records name, the rows it locks, its record-bound precondition and
 * the facts the paper prints — the record's verified data, never typed again (O-01; O-03) —, so DOC never reads a
 * higher tier's tables. Each issuing Task registers its provider under `TAG` for the types it supplies; a type
 * without a provider has no draft and no issue.
 */
interface DocumentSubjectProvider
{
    public const TAG = 'doc.subject-providers';

    /**
     * The document types it supplies, each with the modes it supplies them in.
     *
     * @return array<string, list<string>>
     */
    public function types(): array;

    /**
     * The subject its records name for the type, from the records' public ids keyed by the arc's columns, or null
     * when no such record exists or the viewer may not see it (AZ-11).
     *
     * @param  array<string, string>  $records
     */
    public function subject(Authority $viewer, string $typeCode, string $mode, array $records): ?DocumentSubject;

    /** The rows it locks for the subject — LK-01's projects, LK-21's purchase, LK-25's invoice — joined to DOC's set. */
    public function locks(DocumentSubject $subject): LockSet;

    /**
     * The record-bound precondition of WF-DOC-01's table, read under the locks.
     *
     * @throws Refused when the record does not allow the issue
     */
    public function precondition(CommandContext $context, DocumentSubject $subject): void;

    /** The facts the layout prints, read from the record — under the issue's locks, or for a draft's preview. */
    public function facts(DocumentSubject $subject): DocumentFacts;
}
