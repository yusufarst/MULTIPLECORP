<?php

namespace App\Support\Backup;

/** The backup directory's file system, read for the disk-pressure rule (BACKUP_RECOVERY §3). */
class DiskProbe
{
    /** @return array{total: int, free: int} */
    public function measure(string $path): array
    {
        $total = disk_total_space($path);
        $free = disk_free_space($path);

        return ['total' => $total === false ? 0 : (int) $total, 'free' => $free === false ? 0 : (int) $free];
    }
}
