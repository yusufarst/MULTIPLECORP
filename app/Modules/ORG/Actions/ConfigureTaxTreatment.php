<?php

namespace App\Modules\ORG\Actions;

use App\Modules\ORG\Services\CompanyCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;

/**
 * `ORG ConfigureTaxTreatment` (ROUTE-138; INT-138; NX-01; DATABASE §12; BR-FIN-17; FS-13; DIR-024 D-1; GAP-026): the
 * tax configuration as data — a tax type, an effective-dated rate version with its base fraction and rounding mode, a
 * company's tax profile, a treatment rule by context, global or a company's —, added or changed at the `lock_version`
 * the form read (ST-01), its row at LK-08. Ranges never overlap: C-52's exclusion constraints refuse an overlap, which
 * answers REJECTED at its field. No rate, treatment or formula lives in code.
 */
final class ConfigureTaxTreatment
{
    public const TYPE = 'ORG.ConfigureTaxTreatment';

    /** Each kind's table and the columns the form sets. */
    public const KINDS = [
        'tax_type' => ['tax_types', ['code', 'name', 'category', 'is_active']],
        'rate_version' => ['tax_rate_versions', ['tax_type_id', 'rate_percent', 'base_numerator', 'base_denominator', 'rounding_mode', 'effective']],
        'profile' => ['company_tax_profiles', ['company_id', 'registration_status', 'effective']],
        'rule' => ['tax_treatment_rules', ['company_id', 'transaction_context', 'tax_type_id', 'treatment', 'evidence_required', 'effective']],
    ];

    /** The columns a row keeps once added — its identity in its table (the column grants of DATABASE §12). */
    private const FIXED = [
        'tax_type' => ['code'],
        'rate_version' => ['tax_type_id'],
        'profile' => ['company_id'],
        'rule' => ['company_id', 'transaction_context', 'tax_type_id'],
    ];

    public function __construct(private readonly CompanyCommands $commands) {}

    /**
     * @param  array<string, mixed>  $values  the kind's columns, `effective` as a `daterange` literal
     */
    public function run(string $commandId, int $actorUserId, string $kind, ?int $id, ?int $lockVersion, array $values, ?int $companyId, ?string $companyPublicId): Outcome
    {
        [$table] = self::KINDS[$kind];

        return $this->commands->run($commandId, self::TYPE, $actorUserId, 'tax.configure', $companyId, $companyPublicId, ['kind' => $kind, 'row' => $id, 'lock_version' => $lockVersion, ...$values],
            function (CommandContext $context) use ($kind, $table, $id, $lockVersion, $values, $companyId): CommandResult {
                $context->lock($id === null ? new LockSet : (new LockSet)->add($table, ['id' => $id]));

                if ($id === null) {
                    $row = DB::table($table)->insertGetId([...$values, 'command_id' => $context->request->commandId, 'command_line_no' => 1,
                        'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
                    $this->commands->record($context, 'ORG.TaxConfigurationAdded', $companyId, $table, (int) $row, null, [], ['kind' => $kind, ...$values]);

                    return new CommandResult($table, (int) $row);
                }

                $current = (array) (DB::table($table)->where('id', $id)->first() ?? []);
                $changes = array_diff_key($values, array_flip(self::FIXED[$kind]));
                $before = array_intersect_key($current, $changes);
                $this->commands->update($context, $table, $id, (int) $lockVersion, $changes);
                $this->commands->record($context, 'ORG.TaxConfigurationChanged', $companyId, $table, $id, null, $before, $changes);

                return new CommandResult($table, $id);
            });
    }
}
