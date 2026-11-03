<?php

namespace App\Modules\PRJ\Data;

use App\Support\Command\Fingerprint;

/**
 * SF-REVAL's materiality test (WF-PRJ-03; BR-CR-05; CM-32): a correction is material when it changes a predicate input or a project CALC value — compared
 * as their canonical form, so an amount changed by a rupiah is material and a re-read of the same facts is not. Pure.
 */
final class Materiality
{
    /**
     * @param  array{predicates: array<string, mixed>, calc: array<string, string>}  $before  the inputs and values before the correction
     * @param  array{predicates: array<string, mixed>, calc: array<string, string>}  $after
     */
    public static function material(array $before, array $after): bool
    {
        return Fingerprint::canonicalJson($before) !== Fingerprint::canonicalJson($after);
    }
}
