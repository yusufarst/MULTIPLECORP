<?php

namespace App\Modules\DOC\Services;

use RuntimeException;

/** An export request's attach refused its file (withheld or failed meanwhile): the file's row rolls back. */
final class ExportNotAttached extends RuntimeException {}
