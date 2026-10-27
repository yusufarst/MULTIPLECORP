<?php

namespace App\Modules\ORG\Services;

use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * BR-ACC-03 and C-54 for the commands that start new business for a company — a project, an invoice, a purchase
 * (TASK-032, TASK-049, TASK-037) —: the company row held at LK-06 in the command's own lock set, so a deactivation and
 * the new business run one after the other, and a deactivated company refused. A payment, an application, a return
 * or a correction on an existing record does not ask it, so they continue (C-54).
 */
final class CompanyGuard
{
    public static function locks(int $companyId): LockSet
    {
        return (new LockSet)->add('companies', ['id' => $companyId]);
    }

    public function ensureOpenForNewBusiness(CommandContext $context, int $companyId): void
    {
        $context->require(self::locks($companyId));

        if (DB::table('companies')->where('id', $companyId)->value('is_active') !== true) {
            throw Refused::rejected('C-54', ['reason' => 'company_inactive']);
        }
    }
}
