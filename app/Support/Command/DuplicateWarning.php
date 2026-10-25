<?php

namespace App\Support\Command;

/**
 * The server side of INT-015 (IP-05; L-49; BD-02, BD-13): a likely duplicate refuses a command with its current
 * matches, and the second submission is a new intent naming the matches it acknowledges, with its reason. A
 * match that appeared meanwhile — one the submission does not name — refuses it again with the current list.
 */
final class DuplicateWarning
{
    /**
     * @param  string  $rule  the duplicate rule — BD-02 for a payment, BD-13 for a fungible event
     * @param  list<string>  $matches  the public identifiers of the current matches, read under the locks
     * @param  list<string>  $acknowledged  the matches the submission names
     */
    public static function check(string $rule, array $matches, array $acknowledged, ?string $reason): void
    {
        $unacknowledged = array_values(array_diff($matches, $acknowledged));

        if ($unacknowledged !== [] || ($matches !== [] && ($reason === null || trim($reason) === ''))) {
            sort($matches);

            throw Refused::rejected($rule, ['matches' => $matches]);
        }
    }
}
