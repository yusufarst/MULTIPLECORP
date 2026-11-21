<?php

namespace App\Modules\ORG\Services;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Go-live's store of INFRASTRUCTURE §4.2 (DIR-101; APPR-027; HO-32; NM-03): one file on the deployment's persistent
 * private storage — the `storage` volume every web and worker process mounts —, holding the day as `Y-m-d` (WIB),
 * created once with exclusive creation by the operator's command (TASK-077) and read here by every process. No
 * application write path reaches it; a restart or a database restore leaves it as it is. A missing or unreadable file
 * is go-live not recorded, so the numbering command neither starts nor seeds rather than guessing a day.
 */
final class FileGoLive implements NumberingGoLive
{
    public function __construct(private readonly string $path) {}

    public static function configured(): self
    {
        return new self(config()->string('numbering.go_live_file'));
    }

    public function path(): string
    {
        return $this->path;
    }

    public function day(): ?string
    {
        if (! is_file($this->path)) {
            return null;
        }

        return self::valid(trim((string) @file_get_contents($this->path)));
    }

    /** The day when it is a real `Y-m-d` date, else null. */
    public static function valid(string $day): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $day, 'Asia/Jakarta')?->format('Y-m-d') === $day ? $day : null;
        } catch (Throwable) {
            return null;
        }
    }
}
