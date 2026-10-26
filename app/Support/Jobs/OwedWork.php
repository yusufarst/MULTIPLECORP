<?php

namespace App\Support\Jobs;

/**
 * One kind of work JB-09 sweeps (CONCURRENCY_IDEMPOTENCY §15; DIR-099 reading (1)): its durable rows read, the
 * stale ones re-dispatched or failed only by a conditional update. The export job registers its sweep with
 * TASK-060, the import jobs theirs with TASK-057, each tagged `owed-work` in the container.
 */
interface OwedWork
{
    public const TAG = 'owed-work';

    /** Re-dispatches what is owed; answers how many rows it acted on. */
    public function sweep(): int;
}
