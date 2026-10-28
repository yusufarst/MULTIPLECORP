<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Contracts\DocumentSubjectProvider;
use App\Modules\DOC\Data\DocumentSubject;
use App\Support\Command\Refused;
use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * The providers of the document subjects (ARCHITECTURE §3; WF-DOC-01): the one an issuing module registered for a
 * type and mode, never two. A type its issuing Task has not built yet has none, so it has no draft and no issue.
 */
final class DocumentSubjects
{
    public function __construct(private readonly Container $container) {}

    public function provider(string $typeCode, string $mode): ?DocumentSubjectProvider
    {
        $found = null;

        foreach ($this->container->tagged(DocumentSubjectProvider::TAG) as $provider) {
            if (! $provider instanceof DocumentSubjectProvider || ! in_array($mode, $provider->types()[$typeCode] ?? [], true)) {
                continue;
            }

            if ($found !== null) {
                throw new LogicException("Two providers supply {$typeCode} in mode {$mode}; one record renders one way (ARCHITECTURE §3).");
            }

            $found = $provider;
        }

        return $found;
    }

    /** The subject's provider, or REJECTED when its issuing module supplies none (WF-DOC-01). */
    public function of(DocumentSubject $subject): DocumentSubjectProvider
    {
        return $this->provider($subject->typeCode, $subject->mode) ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }
}
