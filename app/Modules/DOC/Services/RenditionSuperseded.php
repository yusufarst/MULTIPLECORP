<?php

namespace App\Modules\DOC\Services;

use RuntimeException;

/** A finishing attempt that is no longer RENDERING: its step rolls back and its bytes are removed (JB-01). */
final class RenditionSuperseded extends RuntimeException {}
