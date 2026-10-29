<?php

namespace Tests\Support\Documents;

use App\Modules\DOC\Contracts\DocumentSubjectProvider;
use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\IAM\Data\Authority;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use LogicException;

/**
 * A provider that supplies no type: it stands in for an issuing module's provider in the document engine's tests, whose
 * synthetic subject issues that type instead ({@see SyntheticSubjects}) — one record renders one way (ARCHITECTURE §3).
 */
final class SilentSubjects implements DocumentSubjectProvider
{
    public function types(): array
    {
        return [];
    }

    public function subject(Authority $viewer, string $typeCode, string $mode, array $records): ?DocumentSubject
    {
        return null;
    }

    public function locks(DocumentSubject $subject): LockSet
    {
        throw new LogicException('A silent provider supplies no subject.');
    }

    public function precondition(CommandContext $context, DocumentSubject $subject): void
    {
        throw new LogicException('A silent provider supplies no subject.');
    }

    public function facts(DocumentSubject $subject): DocumentFacts
    {
        throw new LogicException('A silent provider supplies no subject.');
    }
}
