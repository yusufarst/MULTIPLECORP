<?php

namespace App\Console\Commands;

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\IAM\Recovery\OwnerRecoveryLinks;
use App\Modules\IAM\Recovery\RecoveryRefused;
use Illuminate\Console\Command;

/**
 * AU-14's Owner-recovery command (INFRASTRUCTURE §7.1): the last resort, run by the human operator only, never by an
 * agent (SX-04), after verifying the Owner in person. The link is shown once, for the Owner to open on the Owner's own
 * device; it is never written into a log, a ticket or the repository.
 */
class IssueOwnerRecoveryLink extends Command
{
    use ReadsOperatorInput;

    protected $signature = 'iam:owner-recovery-link {email : The Owner account\'s e-mail address}
        {--operator= : Your name, the operator\'s, recorded in the audit and security events}
        {--reason= : Why the Owner\'s recovery codes and another active Owner cannot serve (K3)}
        {--confirmer= : During an incident run\'s recovery, the e-mail of the recovered pre-window Owner who confirmed this account in the review}';

    protected $description = 'Issue an OWNER_RECOVERY link for an active Owner account (operator only; AU-14)';

    public function handle(OwnerRecoveryLinks $links): int
    {
        try {
            $operator = OperatorIdentity::stated($this->optionText('operator'));
            $issued = $links->issue($this->argumentText('email'), $this->optionText('reason'), $operator, $this->optionText('confirmer') !== '' ? $this->optionText('confirmer') : null);
        } catch (RecoveryRefused $refused) {
            $this->error($refused->getMessage());

            return self::FAILURE;
        }

        $this->info("Issued an OWNER_RECOVERY link for {$issued['account']->email}, valid for 30 minutes and once.");

        if ($issued['run'] !== null) {
            $this->line("Incident run {$issued['run']->id}, window start {$issued['run']->windowStartText()}".($issued['confirmer'] !== null ? ", confirmer {$issued['confirmer']->email}." : '.'));
        }

        $this->line('Give it only to the Owner, present, to open on the Owner\'s own device; never write it down elsewhere:');
        $this->line('  '.rtrim((is_string($url = config('app.url')) ? $url : ''), '/').'/atur-kata-sandi#'.$issued['token']);
        $this->line('Using it sets the password, clears the TOTP secret and recovery codes, ends every session, revokes the other links and unlinks Google; the Owner then signs in and enrols TOTP.');

        return self::SUCCESS;
    }
}
