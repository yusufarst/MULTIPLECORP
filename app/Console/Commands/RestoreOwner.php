<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\IAM\Recovery\OwnerRestoration;
use App\Modules\IAM\Recovery\RecoveryRefused;
use Illuminate\Console\Command;

/**
 * AU-25's OWNER-restoration command (INFRASTRUCTURE §7.3 step 2): run by the human operator only, never by an agent
 * (SX-04), after the incident command has completed and the person is verified in person.
 */
class RestoreOwner extends Command
{
    use ReadsOperatorInput;

    protected $signature = 'iam:restore-owner {email : The pre-window Owner\'s e-mail address}
        {--window-start= : The incident run\'s recorded window start}
        {--operator= : Your name, the operator\'s, recorded in the audit event}';

    protected $description = 'Restore a pre-window Owner\'s OWNER role and active flag removed during the window (operator only; AU-25)';

    public function handle(OwnerRestoration $restoration): int
    {
        try {
            $operator = OperatorIdentity::stated($this->optionText('operator'));
            $restored = $restoration->restore($this->argumentText('email'), $this->optionText('window-start'), $operator);
        } catch (RecoveryRefused $refused) {
            $this->error($refused->getMessage());

            return self::FAILURE;
        }

        $this->info("Restored {$restored['account']->email} in incident run {$restored['run']->id}.");

        foreach ($restored['reversed'] as $reversal) {
            $this->line("  reversed the removal of its {$reversal['attribute']} at {$reversal['removed_at']} by ".($reversal['removed_by'] ?? 'no application account'));
        }

        $this->line('Show these to every recovered pre-window Owner before the review (GAP-048); the account now recovers only through iam:owner-recovery-link.');

        return self::SUCCESS;
    }
}
