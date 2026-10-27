<?php

namespace App\Modules\DOC\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * WS-12's two upload bounds from durable rows (H6-07): 60 uploads an hour and the daily byte budget per account,
 * counted from `file_objects` — uploader, time and size, on IX-15 — in the upload's own short transaction under the
 * uploader's row lock (LK-03), so two uploads of one account count one after the other and a store outage or restart
 * of the framework limiter in front of them loosens neither. The numbers are WS-12's, the byte budget an assumption
 * kept until P8 measures (config/files.php).
 */
final class UploadBudget
{
    /** Claims the upload's place, inside the transaction that registers its file. */
    public function claim(int $uploaderId, int $bytes): void
    {
        DB::select('select id from users where id = ? for no key update', [$uploaderId]);
        $perHour = config('files.uploads.per_hour');
        $dailyBytes = config('files.uploads.daily_bytes');
        $now = CarbonImmutable::now();
        $hour = $now->subHour()->format('Y-m-d H:i:s.uP');
        $row = (array) DB::selectOne(<<<'SQL'
            select count(*) filter (where recorded_at > ?) as hour_count,
                   min(recorded_at) filter (where recorded_at > ?) as hour_oldest,
                   coalesce(sum(size_bytes), 0) as day_bytes,
                   min(recorded_at) as day_oldest
            from file_objects
            where uploaded_by = ? and recorded_at > ?
            SQL, [$hour, $hour, $uploaderId, $now->subDay()->format('Y-m-d H:i:s.uP')]);

        if (is_numeric($row['hour_count'] ?? null) && is_numeric($perHour) && (int) $row['hour_count'] >= (int) $perHour) {
            throw new UploadLimited(self::minutesUntil($row['hour_oldest'] ?? null, 60, $now), 'hourly');
        }

        if (is_numeric($row['day_bytes'] ?? null) && is_numeric($dailyBytes) && (int) $row['day_bytes'] + $bytes > (int) $dailyBytes) {
            throw new UploadLimited(self::minutesUntil($row['day_oldest'] ?? null, 24 * 60, $now), 'daily_bytes');
        }
    }

    private static function minutesUntil(mixed $oldest, int $window, CarbonImmutable $now): int
    {
        if (! is_string($oldest)) {
            return $window;
        }

        $free = CarbonImmutable::parse($oldest)->addMinutes($window);

        return max(1, (int) ceil($now->diffInSeconds($free, false) / 60));
    }
}
