<?php

namespace App\Modules\ORG\Actions;

use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\CompanyBankAccount;
use App\Modules\ORG\Services\CompanyCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * `ORG AddBankAccount · ChangeBankAccount` (ROUTE-134; INT-134; NX-01; DATABASE §4.2; BR-SN-02): a company's bank
 * account added; changed by adding the new one and deactivating the old, so an issued document or a payment keeps the
 * destination it recorded — a referenced account's details are never edited, which its trigger enforces too —; or
 * deactivated alone after its confirmation. The company at LK-06 and the account at LK-07; behind the step-up (AU-15).
 */
final class ManageBankAccount
{
    public const ADD = 'ORG.AddBankAccount';

    public const CHANGE = 'ORG.ChangeBankAccount';

    public const DEACTIVATE = 'ORG.DeactivateBankAccount';

    public function __construct(private readonly CompanyCommands $commands) {}

    /** @param  array{bank_name: string, account_number: string, account_holder: string, branch: ?string}  $details */
    public function add(string $commandId, int $actorUserId, Company $company, array $details): Outcome
    {
        return $this->commands->run($commandId, self::ADD, $actorUserId, 'companies.manage', $company->id, $company->public_id, $details,
            function (CommandContext $context) use ($company, $details): CommandResult {
                $context->lock((new LockSet)->add('companies', ['id' => $company->id]));

                return new CommandResult('company_bank_account', $this->insert($context, $company, $details, 1));
            });
    }

    /**
     * The new account and the old one's deactivation, in one command (BR-SN-02).
     *
     * @param  array{bank_name: string, account_number: string, account_holder: string, branch: ?string}  $details
     */
    public function change(string $commandId, int $actorUserId, Company $company, int $accountId, int $lockVersion, array $details, string $reason): Outcome
    {
        return $this->commands->run($commandId, self::CHANGE, $actorUserId, 'companies.manage', $company->id, $company->public_id, [...$details, 'account' => $accountId, 'lock_version' => $lockVersion, 'reason' => $reason],
            function (CommandContext $context) use ($company, $accountId, $lockVersion, $details, $reason): CommandResult {
                $context->lock((new LockSet)->add('companies', ['id' => $company->id])->add('company_bank_accounts', ['id' => $accountId]));
                $this->retire($context, $company, $accountId, $lockVersion, $reason);

                return new CommandResult('company_bank_account', $this->insert($context, $company, $details, 1));
            });
    }

    public function deactivate(string $commandId, int $actorUserId, Company $company, int $accountId, int $lockVersion, string $reason): Outcome
    {
        return $this->commands->run($commandId, self::DEACTIVATE, $actorUserId, 'companies.manage', $company->id, $company->public_id, ['account' => $accountId, 'lock_version' => $lockVersion, 'reason' => $reason],
            function (CommandContext $context) use ($company, $accountId, $lockVersion, $reason): CommandResult {
                $context->lock((new LockSet)->add('companies', ['id' => $company->id])->add('company_bank_accounts', ['id' => $accountId]));
                $this->retire($context, $company, $accountId, $lockVersion, $reason);

                return new CommandResult('company_bank_account', $accountId);
            });
    }

    /** @param  array{bank_name: string, account_number: string, account_holder: string, branch: ?string}  $details */
    private function insert(CommandContext $context, Company $company, array $details, int $line): int
    {
        if (DB::table('company_bank_accounts')->where('company_id', $company->id)->where('bank_name', $details['bank_name'])
            ->where('account_number', $details['account_number'])->exists()) {
            throw Refused::rejected('BD-16', ['field' => 'account_number']);
        }

        $account = new CompanyBankAccount;
        $account->forceFill([...$details, 'company_id' => $company->id, 'command_id' => $context->request->commandId, 'command_line_no' => $line,
            'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId])->save();
        $this->commands->record($context, 'ORG.BankAccountAdded', $company->id, 'company_bank_account', $account->id, null, [], [...$details]);

        return $account->id;
    }

    private function retire(CommandContext $context, Company $company, int $accountId, int $lockVersion, string $reason): void
    {
        $account = CompanyBankAccount::query()->where('company_id', $company->id)->findOrFail($accountId);

        if (! $account->is_active) {
            throw Refused::rejected('BR-SN-02', ['reason' => 'inactive']);
        }

        $this->commands->update($context, 'company_bank_accounts', $account->id, $lockVersion, [
            'is_active' => false, 'deactivated_at' => $context->now(), 'deactivated_by' => $context->request->actorUserId, 'deactivated_reason' => trim($reason),
        ]);
        $this->commands->record($context, 'ORG.BankAccountDeactivated', $company->id, 'company_bank_account', $account->id, null, ['is_active' => true], ['is_active' => false], $reason);
    }
}
