<?php

namespace App\Modules\IAM\Recovery;

/**
 * AU-25's halt: no pre-window Owner can be established from the audit trail with certainty — the window reaching back
 * before every OWNER assignment, or an OWNER assignment without an audit event —, or none is available. The
 * in-application path stops; on a trusted state the halt goes first to the Owner's incident decision outside the
 * application, with the client, which may choose Scenario B's restore or keep the trusted state, and on a restored
 * state it ends in that decision (DIR-083 OE-10). No Owner is created, promoted or guessed (TT-F25).
 */
final class RecoveryHalted extends RecoveryRefused
{
    public static function because(string $condition): self
    {
        return new self("HALT (AU-25): {$condition}. The in-application recovery path stops: no Owner is created, "
            .'promoted or guessed. On a trusted state this goes first to the Owner\'s incident decision outside the '
            .'application, with the client, which may choose Scenario B\'s restore or keep the trusted state; on a '
            .'restored state it ends in that decision (DIR-083 OE-10; INFRASTRUCTURE §7.3 step 6).');
    }
}
