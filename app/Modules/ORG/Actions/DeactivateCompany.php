<?php

namespace App\Modules\ORG\Actions;

use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Services\CompanyCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;

/**
 * `ORG DeactivateCompany` (ROUTE-133; INT-133; NX-01; BR-ACC-03; C-54): after its confirmation (IP-08), the company
 * stops starting new business — projects, invoices, purchases — while collection, returns and corrections on its
 * existing records continue. The company row at LK-06, which every command that starts new business for the company
 * also takes (`CompanyGuard`), so neither commits against the other's stale state. A deactivated company stays in the
 * Owner's scope and grantable, for history (CS-01; CS-02).
 */
final class DeactivateCompany
{
    public const TYPE = 'ORG.DeactivateCompany';

    public function __construct(private readonly CompanyCommands $commands) {}

    public function run(string $commandId, int $actorUserId, Company $company, int $lockVersion, string $reason): Outcome
    {
        return $this->commands->run($commandId, self::TYPE, $actorUserId, 'companies.manage', $company->id, $company->public_id, ['lock_version' => $lockVersion, 'reason' => $reason],
            function (CommandContext $context) use ($company, $lockVersion, $reason): CommandResult {
                $context->lock((new LockSet)->add('companies', ['id' => $company->id]));
                $row = Company::query()->findOrFail($company->id);

                if (! $row->is_active) {
                    throw Refused::rejected('C-54', ['reason' => 'inactive']);
                }

                $this->commands->update($context, 'companies', $row->id, $lockVersion, [
                    'is_active' => false, 'deactivated_at' => $context->now(), 'deactivated_by' => $context->request->actorUserId, 'deactivated_reason' => trim($reason),
                ]);
                $this->commands->record($context, 'ORG.CompanyDeactivated', $row->id, 'company', $row->id, $row->public_id, ['is_active' => true], ['is_active' => false], $reason);

                return new CommandResult('company', $row->id);
            });
    }
}
