<?php

namespace App\Console\Commands;

use App\Support\Monitoring\HeartbeatSignals;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * INFRASTRUCTURE §6's alert rules (TASK-075; GAP-013; HO-25; BK-11), run by the scheduler every five minutes: each
 * failing heartbeat signal — a queued job past its bound, failed jobs, a scheduled task without its success, owed or
 * exhausted renditions, connections, the disk at its warning or critical level — logged as an alert, which the alert
 * channel sends as its neutral name, once per window while it stays failing.
 */
class HeartbeatAlerts extends Command
{
    protected $signature = 'ops:heartbeat';

    protected $description = 'Raise an alert for each failing signal of the business heartbeat';

    public function handle(): int
    {
        $failing = 0;

        foreach (HeartbeatSignals::read(HeartbeatSignals::configured()) as $signal) {
            if (! $signal['pass']) {
                $failing++;
                Log::alert("Heartbeat: {$signal['signal']} failing (INFRASTRUCTURE §6).");
                $this->line("FAIL {$signal['signal']} — {$signal['detail']}");
            }
        }

        $this->line($failing === 0 ? 'Heartbeat: every signal passes.' : "Heartbeat: {$failing} signal(s) failing, alerted.");

        return self::SUCCESS;
    }
}
