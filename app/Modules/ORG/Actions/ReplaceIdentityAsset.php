<?php

namespace App\Modules\ORG\Actions;

use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Services\CompanyCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * `ORG ReplaceIdentityAsset` (ROUTE-135; INT-135; NX-01; PJ-19; FL-02): a company's logo, stamp or signature set to a
 * file the acting Owner uploaded first through ROUTE-228 as this company's identity asset — refused there if outside
 * the purpose's allowlist —, the company at LK-06 and behind the step-up (AU-15). The file is immutable; a replaced
 * one stays with the versions issued with it (BR-SN-01), and the audit names files by public identifier only (LG-03).
 */
final class ReplaceIdentityAsset
{
    public const TYPE = 'ORG.ReplaceIdentityAsset';

    public const ASSETS = ['logo' => 'logo_file_id', 'stamp' => 'stamp_file_id', 'signature' => 'signature_file_id'];

    public function __construct(private readonly CompanyCommands $commands) {}

    public function run(string $commandId, int $actorUserId, Company $company, string $asset, string $filePublicId, int $lockVersion): Outcome
    {
        $column = self::ASSETS[$asset];

        return $this->commands->run($commandId, self::TYPE, $actorUserId, 'companies.manage', $company->id, $company->public_id, ['asset' => $asset, 'file' => $filePublicId, 'lock_version' => $lockVersion],
            function (CommandContext $context) use ($company, $column, $asset, $filePublicId, $lockVersion): CommandResult {
                $context->lock((new LockSet)->add('companies', ['id' => $company->id]));
                // The actor's own upload of this company's identity asset (DIR-089 (5)); anything else answers as unknown.
                $file = DB::table('file_objects')->where('public_id', strtolower($filePublicId))->where('purpose', 'IDENTITY_ASSET')
                    ->where('company_id', $company->id)->where('uploaded_by', $context->request->actorUserId)->value('id');

                if (! is_numeric($file)) {
                    throw new ModelNotFoundException;
                }

                $row = Company::query()->findOrFail($company->id);
                $before = DB::table('file_objects')->where('id', $row->{$column})->value('public_id');
                $this->commands->update($context, 'companies', $row->id, $lockVersion, [$column => (int) $file]);
                $this->commands->record($context, 'ORG.IdentityAssetReplaced', $row->id, 'company', $row->id, $row->public_id,
                    [$asset => is_string($before) ? $before : null], [$asset => strtolower($filePublicId)]);

                return new CommandResult('company', $row->id);
            });
    }
}
