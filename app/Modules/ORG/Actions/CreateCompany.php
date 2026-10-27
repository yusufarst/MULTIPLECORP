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
 * `ORG CreateCompany` (ROUTE-131; INT-131; NX-01; CAP-01; REF-005; IMG-01): a legal entity of its own — code, legal
 * name, legal form, NPWP, address, contact and director —, independent of the product's brand, created active as a
 * new root the command chooses (CS-03). A legal identity that already exists — its code, or its NPWP by digits — is
 * refused at its field; a lost race on the code's unique key answers CONFLICT (BD-16; SQ-08).
 */
final class CreateCompany
{
    public const TYPE = 'ORG.CreateCompany';

    public function __construct(private readonly CompanyCommands $commands) {}

    /** @param  array<string, string|null>  $fields */
    public function run(string $commandId, int $actorUserId, array $fields): Outcome
    {
        return $this->commands->run($commandId, self::TYPE, $actorUserId, 'companies.manage', null, null, $fields, function (CommandContext $context) use ($fields): CommandResult {
            $context->lock(new LockSet);
            $npwp = CompanyFields::npwpDigits($fields['npwp'] ?? null);

            if (DB::table('companies')->where('code', $fields['code'])->exists()) {
                throw Refused::rejected('BD-16', ['field' => 'code']);
            }

            if ($npwp !== null && DB::table('companies')->whereRaw("regexp_replace(coalesce(npwp, ''), '[^0-9]', '', 'g') = ?", [$npwp])->exists()) {
                throw Refused::rejected('BD-16', ['field' => 'npwp']);
            }

            $company = new Company;
            $company->forceFill([...$fields, 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(),
                'recorded_by' => $context->request->actorUserId])->save();
            $this->commands->record($context, 'ORG.CompanyCreated', $company->id, 'company', $company->id, $company->public_id, [], $fields);

            return new CommandResult('company', $company->id);
        });
    }
}
