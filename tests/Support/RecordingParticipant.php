<?php

namespace Tests\Support;

use App\Modules\PRJ\Contracts\ProjectLifecycleParticipant;
use App\Support\Command\CommandContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A test participant of `ProjectLifecycleParticipant` until INV's exists (TASK-034's tool requirement; TASK-038): it
 * records each hook with whether it ran inside the command's transaction, and fails on demand so the command is shown
 * to roll back with it.
 */
final class RecordingParticipant implements ProjectLifecycleParticipant
{
    /** @var list<array{hook: string, project: int, released: array<int, string>, transaction: int}> */
    public static array $calls = [];

    public static bool $fail = false;

    public static function register(): void
    {
        self::$calls = [];
        self::$fail = false;
        app()->tag([self::class], ProjectLifecycleParticipant::TAG);
    }

    public function reconfirmed(CommandContext $context, int $projectId): void
    {
        $this->record('reconfirmed', $projectId, []);
    }

    public function scopeReduced(CommandContext $context, int $projectId, array $released): void
    {
        $this->record('scopeReduced', $projectId, $released);
    }

    public function cancelled(CommandContext $context, int $projectId): void
    {
        $this->record('cancelled', $projectId, []);
    }

    public function revalidated(CommandContext $context, int $projectId): void
    {
        $this->record('revalidated', $projectId, []);
    }

    /** @param  array<int, string>  $released */
    private function record(string $hook, int $projectId, array $released): void
    {
        self::$calls[] = ['hook' => $hook, 'project' => $projectId, 'released' => $released, 'transaction' => DB::transactionLevel()];

        if (self::$fail) {
            throw new RuntimeException('The participant failed.');
        }
    }
}
