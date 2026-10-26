<?php

namespace App\Support\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Every queued job's shared mechanics (CONCURRENCY_IDEMPOTENCY §15; ARCHITECTURE §11): dispatched only after
 * the transaction that owes the work commits, discarded with one that rolls back; a payload of identifiers
 * only — the durable row's id, the actor and company context —, the correlation id travelling in the
 * framework's context, never an S3 value or a secret (DP-09; LG-04); and the payload's format version, which a
 * worker that knows only an older one releases back with a delay instead of failing, a newer worker accepting
 * the previous version for one release (GAP-012; HO-31). Never a framework batch job (JB-07).
 */
abstract class PlatformJob implements ShouldQueue
{
    use Queueable;

    /** The payload's format; a job class raises it when its payload changes, keeping the previous one readable. */
    public const int FORMAT = 1;

    public int $format;

    public function __construct()
    {
        $this->format = static::FORMAT;
        $this->afterCommit();
    }

    /** The newest payload format this worker reads. */
    public function knownFormat(): int
    {
        return static::FORMAT;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new PayloadFormat];
    }
}
