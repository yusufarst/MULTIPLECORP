<?php

namespace App\Console\Commands;

use App\Modules\IAM\Authentication\CredentialLinks;
use Illuminate\Console\Command;

/**
 * `PurgeCredentialLinks` (SECURITY AU-11; H9-04): the credential-link store's own scheduled job, daily, deleting
 * every link past its expiry — used, revoked or never used alike —, so the store keeps only links that can still
 * be used or have expired since the last run. A link holds only its keyed hash, never the token.
 */
class PurgeCredentialLinks extends Command
{
    protected $signature = 'credential-links:purge';

    protected $description = 'Delete the credential links past their expiry (scheduled daily)';

    public function handle(CredentialLinks $links): int
    {
        $this->info("Purged {$links->purgeExpired()} expired credential links.");

        return self::SUCCESS;
    }
}
