<?php

namespace App\Modules\ImportOrchestration\Models;

use App\Modules\OPS\Models\ImportBatch;

/**
 * The import orchestration's own view of an OPS import batch (ARCHITECTURE §3): the same `import_batches` row and casts,
 * so the tier-7 pipeline names no other module's model outside its Models folder.
 */
final class Batch extends ImportBatch
{
    protected $table = 'import_batches';
}
