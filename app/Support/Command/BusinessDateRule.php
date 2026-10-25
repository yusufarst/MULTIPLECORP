<?php

namespace App\Support\Command;

use App\Support\BusinessDate;
use InvalidArgumentException;

/**
 * SF-DATE (BR-DT-01–BR-DT-06; C-50; ST-08): a command's `business_date` is a WIB day no later than today — C-50's
 * trigger is the backstop —; an earlier real date commits for the Owner and an Admin alike, `recorded_at` and
 * the audit keeping both dates (BR-DT-02, BR-DT-03). A date the form only prefilled that is no longer today when
 * the command first executes is a CONFLICT carrying the current date, so a form left open across midnight
 * backdates nothing (ST-08); a date the user chose is taken as it is, and a replay never re-evaluates either.
 */
final class BusinessDateRule
{
    /** Phase C: refuses a future date (C-50) and a stale prefilled one (ST-08). */
    public static function check(string $businessDate, bool $prefilled): void
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $businessDate, $day) !== 1 || ! checkdate((int) $day[2], (int) $day[3], (int) $day[1])) {
            throw new InvalidArgumentException('A business date is a Y-m-d day.');
        }

        $today = BusinessDate::today();

        if ($businessDate > $today) {
            throw Refused::rejected('C-50', ['today' => $today]);
        }

        if ($prefilled && $businessDate !== $today) {
            throw Refused::conflict('ST-08', ['today' => $today]);
        }
    }
}
