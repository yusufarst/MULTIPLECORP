<?php

namespace App\Modules\PUR\Services;

use App\Support\Command\CommandContext;
use App\Support\Command\Refused;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * A line's tax components (DIR-024 D-1; BR-FIN-17; DATABASE §12): the treatment resolved for its company, context and business date and snapshotted with its rate; the stated amount the truth; only INPUT_TO_ACQUISITION_COST's part offered to the lot's cost (SF-CHARGE; L-43). No rate or formula lives in code (FS-13).
 */
final class PurchaseTaxes
{
    public const CONTEXT = 'PURCHASE_LINE';

    public const TO_COST = 'INPUT_TO_ACQUISITION_COST';

    /**
     * @param  list<array{type: int, rate: string, amount: string}>  $taxes
     * @param  array<int, array{id: int, treatment: string}|null>  $rules  the treatment of each tax type for the company and business date, ORG's reading
     * @return array{rows: list<array<string, string|int>>, noncreditable: Money}
     */
    public function components(array $taxes, array $rules, Money $base): array
    {
        $rows = [];
        $noncreditable = Money::zero();

        foreach ($taxes as $tax) {
            $rule = $rules[$tax['type']] ?? null;

            if ($rule === null) {
                throw Refused::rejected('BR-FIN-17', ['reason' => 'no_rule']);
            }

            $amount = Money::of($tax['amount']);
            $treatment = $rule['treatment'];
            $noncreditable = $treatment === self::TO_COST ? $noncreditable->plus($amount) : $noncreditable;
            $rows[] = ['tax_type_id' => $tax['type'], 'treatment_rule_id' => $rule['id'], 'treatment' => $treatment, 'rate_percent' => $tax['rate'], 'base_amount' => $base->toString(), 'amount' => $amount->toString()];
        }

        return ['rows' => $rows, 'noncreditable' => $noncreditable];
    }

    /** @param  list<array<string, string|int>>  $rows */
    public function insert(CommandContext $context, int $lineId, array $rows, int $after): void
    {
        foreach ($rows as $index => $row) {
            DB::table('purchase_line_tax_components')->insert([...$row, 'purchase_line_id' => $lineId, 'command_id' => $context->request->commandId, 'command_line_no' => $after + $index + 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
        }
    }
}
