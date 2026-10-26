<?php

namespace App\Modules\IAM\Authentication;

use RuntimeException;

/** A password verification or hash that found no slot of H6-12, or no store to take one from: the throttled answer. */
final class VerificationRefused extends RuntimeException {}
