<?php

namespace App\Modules\IAM\Recovery;

use RuntimeException;

/** An operator command's refusal, written nowhere and printed to the operator with the state that caused it. */
class RecoveryRefused extends RuntimeException {}
