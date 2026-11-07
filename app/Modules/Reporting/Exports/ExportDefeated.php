<?php

namespace App\Modules\Reporting\Exports;

use RuntimeException;

/** A finished export whose READY update a WITHHELD or FAILED mark defeated (JB-03; RV-08): its step rolls back. */
final class ExportDefeated extends RuntimeException {}
