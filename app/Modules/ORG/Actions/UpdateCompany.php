<?php

namespace App\Modules\ORG\Actions;

use App\Modules\ORG\Data\CompanyFields;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Services\CompanyCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * `ORG UpdateCompany` (ROUTE-132; INT-132; NX-01; BR-SN-01; J-MD-01): the company's legal data, the company row at
 * LK-06 and changed at the `lock_version` the form read (ST-01). Issued documents keep the identity they were issued
 * with — their payload is their own snapshot (C-11) —, so a change here alters only what is issued from now on. An
 * NPWP another company already holds is refused at its field. The step-up a change of an identity asset asks is the
 * route's, before the command runs (AU-15).
 */
final class UpdateCompany
{
    public const TYPE = 'ORG.UpdateCompany';

    public function __construct(private readonly CompanyCommands $commands) {}

    /** @param  array<string, string|null>  $fields */
    public function run(string $commandId, int $actorUserId, Company $company, int $lockVersion, array $fields): Outcome
    {
        return $this->commands->run($commandId, self::TYPE, $actorUserId, 'companies.manage', $company->id, $company->public_id, [...$fields, 'lock_version' => $lockVersion],
            function (CommandContext $context) use ($company, $lockVersion, $fields): CommandResult {
                $context->lock((new LockSet)->add('companies', ['id' => $company->id]));
                $row = Company::query()->findOrFail($company->id);
                $npwp = CompanyFields::npwpDigits($fields['npwp'] ?? null);

                if ($npwp !== null && DB::table('companies')->where('id', '<>', $row->id)
                    ->whereRaw("regexp_replace(coalesce(npwp, ''), '[^0-9]', '', 'g') = ?", [$npwp])->exists()) {
                    throw Refused::rejected('BD-16', ['field' => 'npwp']);
                }

                $before = [];
                $after = [];

                foreach (CompanyFields::LEGAL as $field) {
                    if (($row->{$field} ?? null) !== ($fields[$field] ?? null)) {
                        $before[$field] = $row->{$field};
                        $after[$field] = $fields[$field] ?? null;
                    }
                }

                $this->commands->update($context, 'companies', $row->id, $lockVersion, $after);

                if ($after !== []) {
                    $this->commands->record($context, 'ORG.CompanyUpdated', $row->id, 'company', $row->id, $row->public_id, $before, $after);
                }

                return new CommandResult('company', $row->id);
            });
    }
}
