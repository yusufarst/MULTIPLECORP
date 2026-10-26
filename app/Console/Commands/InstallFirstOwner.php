<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\Installation;
use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\IAM\Recovery\RecoveryRefused;
use Illuminate\Console\Command;

/**
 * The installation step that creates the first Owner (PRQ-09; INFRASTRUCTURE §5; GAP-049): run once by the human
 * operator on the host, never by an agent (SX-04). It sets no password: the operator issues the first Owner's
 * OWNER_RECOVERY link right after it, with the Owner present (DIR-085 OF-06).
 */
class InstallFirstOwner extends Command
{
    use ReadsOperatorInput;

    protected $signature = 'iam:install-owner {--name= : The first Owner\'s name} {--email= : The first Owner\'s e-mail address} {--operator= : Your name, the operator\'s, recorded in the audit event}';

    protected $description = 'Create the first Owner of a new workspace, the authorization catalogue synced first (operator only)';

    public function handle(Installation $installation): int
    {
        try {
            $operator = OperatorIdentity::stated($this->optionText('operator'));
            $owner = $installation->install($this->optionText('name'), $this->optionText('email'), $operator);
        } catch (RecoveryRefused $refused) {
            $this->error($refused->getMessage());

            return self::FAILURE;
        }

        $this->info("Created the first Owner {$owner->email} ({$owner->public_id}), recorded with the operator {$operator->describe()}.");
        $this->line('The account has no usable password. Now, with the Owner present, issue its OWNER_RECOVERY link and let the Owner open it on the Owner\'s own device (DIR-085 OF-06):');
        $this->line("  php artisan iam:owner-recovery-link {$owner->email} --operator=\"{$operator->name}\" --reason=\"First Owner set-up at installation\"");

        return self::SUCCESS;
    }
}
