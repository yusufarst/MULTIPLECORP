<?php

namespace App\Modules\ImportOrchestration\Services;

use RuntimeException;

/** A source that cannot be read as a table of data (FL-06): its reason, a key of `import.source.*`. */
final class SourceRefused extends RuntimeException
{
    public const EMPTY = 'empty';

    public const HEADER = 'header';

    public const UNREADABLE = 'unreadable';

    public const ENCODING = 'encoding';

    public const PACKAGE = 'package';

    public const TOO_MANY_ROWS = 'rows';

    public const TOO_MANY_COLUMNS = 'columns';

    public const CELL = 'cell';

    public function __construct(public readonly string $reason)
    {
        parent::__construct("The import source was refused: {$reason}.");
    }
}
