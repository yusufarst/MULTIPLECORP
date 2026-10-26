<?php

namespace App\Modules\DOC\Contracts;

use RuntimeException;

/** A render that produced no PDF; its summary holds no secret and no document content (`error_summary`). */
final class RenderFailed extends RuntimeException {}
