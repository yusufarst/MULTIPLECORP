<?php

namespace App\Support\Backup;

/**
 * A generation's truthful status (BK-07; BKR-02): only VERIFIED counts as a recovery point. A run that did not
 * complete leaves no manifest (INCOMPLETE); a part missing, resized or changed is CORRUPT; an absent directory is
 * MISSING. A checksum that verifies is never restore proof — the drill is (BACKUP_RECOVERY §5).
 */
enum GenerationStatus: string
{
    case Verified = 'VERIFIED';
    case Incomplete = 'INCOMPLETE';
    case Corrupt = 'CORRUPT';
    case Missing = 'MISSING';

    public function verified(): bool
    {
        return $this === self::Verified;
    }
}
