<?php

namespace App\Support\Command;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\CommandAuthorization;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\ConnectionInterface;

/**
 * What a command's action sees inside the envelope (ARCHITECTURE §5; CONCURRENCY_IDEMPOTENCY §6): its request,
 * its connection, the clock read once — `recorded_at` for every row it writes —, and its locks. The first
 * `lock()` takes the rows up to LK-03, the actor's row among them, and re-authorizes there, before any
 * precondition is read (RV-01); every later one only moves forward (LR-01). `require()` is §5's re-derivation:
 * a required row that is not held restarts the action with the union of the sets derived so far.
 */
final class CommandContext
{
    private ?Authority $authority = null;

    private readonly LockPosition $held;

    /** @var list<Closure(): void> */
    private array $afterCommit = [];

    public function __construct(
        public readonly CommandRequest $request,
        public readonly ConnectionInterface $connection,
        public readonly CarbonImmutable $now,
        private LockSet $derived,
        private readonly CommandAuthorization $authorization,
        private readonly ?Closure $observer = null,
    ) {
        $this->held = new LockPosition;
    }

    /**
     * Locks the set — joined with every set derived before a restart and with the actor's row — in canonical
     * order, re-authorizing once LK-03 is held. Returns the keys that matched no row.
     *
     * @return list<array{table: string, key: array<string, int|string>}>
     */
    public function lock(LockSet $set): array
    {
        $this->derived = $this->derived->union($set)->add('users', ['id' => $this->request->actorUserId]);
        $missing = [];

        if ($this->authority === null) {
            $missing = $this->derived->acquire($this->connection, $this->held, LockClass::User, $this->observer);
            $this->authority = $this->authorization->reauthorizeLocked($this->request->actorUserId, $this->request->capability, $this->request->companyId);
        }

        return [...$missing, ...$this->derived->acquire($this->connection, $this->held, null, $this->observer)];
    }

    /** §5 step 3–4: the set re-derived under the locks must be held, or the action restarts with the union. */
    public function require(LockSet $required): void
    {
        if (! $this->derived->covers($required)) {
            throw new Restart($this->derived->union($required));
        }
    }

    /** The actor's authority re-read under its row lock (RV-01); null before the first lock. */
    public function authority(): ?Authority
    {
        return $this->authority;
    }

    /** Work after the commit — dispatches, cache invalidation — whose failure never changes the outcome (SQ-21). */
    public function afterCommit(Closure $work): void
    {
        $this->afterCommit[] = $work;
    }

    /** @return list<Closure(): void> */
    public function afterCommitWork(): array
    {
        return $this->afterCommit;
    }

    public function derived(): LockSet
    {
        return $this->derived;
    }
}
