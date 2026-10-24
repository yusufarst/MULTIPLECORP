<?php

namespace App\Console\Commands;

use App\Modules\IAM\Services\CatalogueSync;
use Illuminate\Console\Command;

/**
 * Adds the missing rows of the authorization catalogue — the capabilities, the built-in roles and the
 * OWNER role's capabilities (PERMISSIONS_MATRIX §3–§4) —, run by the operator at the installation step and
 * after every deployment, under the runtime role; it never changes or removes a row.
 */
class SyncAuthorizationCatalogue extends Command
{
    protected $signature = 'authorization:sync-catalogue';

    protected $description = 'Add the missing capabilities, built-in roles and OWNER role capabilities of PERMISSIONS_MATRIX';

    public function handle(CatalogueSync $sync): int
    {
        $added = $sync->run();

        $this->info(sprintf('Added %d capabilities, %d roles and %d OWNER role capabilities.', $added['capabilities'], $added['roles'], $added['role_capabilities']));

        return self::SUCCESS;
    }
}
