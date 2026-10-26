<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\IAM\Recovery\RecoveryRefused;
use App\Modules\IAM\Recovery\Reencryption;
use Illuminate\Console\Command;

/**
 * AU-21's re-encryption command for a planned key rotation (INFRASTRUCTURE §7.4): run by the human operator only, never
 * by an agent (SX-04), with the new key current and the old one in APP_PREVIOUS_KEYS.
 */
class ReencryptTwoFactorSecrets extends Command
{
    use ReadsOperatorInput;

    protected $signature = 'iam:reencrypt-secrets {--operator= : Your name, the operator\'s, recorded in the audit events}';

    protected $description = 'Re-encrypt every TOTP secret and recovery-code set under the current key (operator only; AU-21)';

    public function handle(Reencryption $reencryption): int
    {
        try {
            $result = $reencryption->run(OperatorIdentity::stated($this->optionText('operator')));
        } catch (RecoveryRefused $refused) {
            $this->error($refused->getMessage());

            return self::FAILURE;
        }

        $this->line("Re-encrypted {$result['reencrypted']} of {$result['enrolled']} enrolled accounts.");

        if (! $result['verified']) {
            $this->error('The counts differ: keep the old key in APP_PREVIOUS_KEYS and run the command again (AU-21).');

            return self::FAILURE;
        }

        $this->info('The count is verified: the old key may be retired from APP_PREVIOUS_KEYS and its retirement entered in the key register.');

        return self::SUCCESS;
    }
}
