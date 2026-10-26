<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\KeyCompromise;
use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\IAM\Recovery\RecoveryRefused;
use Illuminate\Console\Command;

/**
 * AU-21's key-compromise incident command (INFRASTRUCTURE §7.2): run by the human operator only, never by an agent
 * (SX-04), after new keys are in the environment and the maintenance mode is on every web process without a bypass.
 */
class KeyCompromiseIncident extends Command
{
    use ReadsOperatorInput;

    protected $signature = 'iam:key-compromise
        {--window-start= : The compromise window\'s start established with the Owner, ISO 8601 with its offset}
        {--operator= : Your name, the operator\'s, recorded in every audit event of the run}';

    protected $description = 'Void every account\'s credentials after a key compromise, protocol (a)-(d) (operator only; AU-21)';

    public function handle(KeyCompromise $incident): int
    {
        try {
            $operator = OperatorIdentity::stated($this->optionText('operator'));
            $result = $incident->run($this->optionText('window-start'), $operator, fn (string $line) => $this->line($line));
        } catch (RecoveryRefused $refused) {
            $this->error($refused->getMessage());

            return self::FAILURE;
        }

        $this->info("Incident run {$result['run']->id} completed; window start {$result['run']->windowStartText()}.");
        $this->line('Lift the maintenance mode only once every step is verified (INFRASTRUCTURE §7.2 step 7); recovery follows §7.3 with this window start.');

        if ($result['halt'] !== null) {
            $this->warn($result['halt']);
        }

        return self::SUCCESS;
    }
}
