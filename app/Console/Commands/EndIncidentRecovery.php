<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\IAM\Recovery\OwnerRestoration;
use App\Modules\IAM\Recovery\RecoveryRefused;
use Illuminate\Console\Command;

/**
 * Records the end of an incident run's recovery (AU-25; INFRASTRUCTURE §7.3 step 8): run by the human operator only,
 * never by an agent (SX-04), once the review is complete; after it the OWNER-restoration command refuses.
 */
class EndIncidentRecovery extends Command
{
    use ReadsOperatorInput;

    protected $signature = 'iam:end-recovery
        {--window-start= : The incident run\'s recorded window start}
        {--operator= : Your name, the operator\'s, recorded in the audit event}';

    protected $description = 'Record the end of an incident run\'s recovery once no unreviewed Owner remains (operator only; AU-25)';

    public function handle(OwnerRestoration $restoration): int
    {
        try {
            $run = $restoration->endRecovery($this->optionText('window-start'), OperatorIdentity::stated($this->optionText('operator')));
        } catch (RecoveryRefused $refused) {
            $this->error($refused->getMessage());

            return self::FAILURE;
        }

        $this->info("Recorded the end of incident run {$run->id}'s recovery.");

        return self::SUCCESS;
    }
}
