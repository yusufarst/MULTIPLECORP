<?php

namespace App\Modules\FIN\Services;

use App\Modules\FIN\Data\InvoiceValues;
use App\Modules\ORG\Services\OutputTaxes;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * An invoice's output tax under the treatment for its company, context and business date (BR-FIN-17; C-52; DATABASE §12): every
 * active output tax with a rate in force and a SALES_INVOICE rule — the company's over the global one —, its amount proposed from
 * the configured rate unless the actor stated the tax document's own, snapshotted on the version's rows and never rewritten by a
 * later configuration. None configured, none written (FS-13). A value-only termin takes its share of the tax pinned at the
 * confirmation, the last one absorbing the residual (BR-FIN-13).
 */
final class InvoiceTaxes
{
    public function __construct(private readonly OutputTaxes $outputTaxes) {}

    /**
     * The components of a value on a day.
     *
     * @param  array<string, string>  $stated  the tax document's amounts by tax code
     * @return list<array{tax_type_id: int, code: string, treatment_rule_id: int, treatment: string, rate_percent: string, base_amount: string, amount: string, evidence_required: bool}>
     */
    public function components(int $companyId, ?int $projectId, string $kind, string $sales, string $day, array $stated, ?int $exceptInvoiceId = null): array
    {
        $components = [];

        foreach ($this->outputTaxes->inForce($companyId, $day) as $rule) {
            $termin = $kind === 'VALUE_ONLY' && $projectId !== null ? self::terminTax($projectId, $rule['code'], $sales, $exceptInvoiceId) : null;
            $components[] = [
                'tax_type_id' => $rule['tax_type_id'],
                'code' => $rule['code'],
                'treatment_rule_id' => $rule['rule_id'],
                'treatment' => $rule['treatment'],
                'rate_percent' => $rule['rate_percent'],
                'base_amount' => $sales,
                'amount' => isset($stated[$rule['code']]) ? Money::of($stated[$rule['code']])->toString() : ($termin ?? InvoiceValues::proposedTax($sales, $rule['rate_percent'], $rule['numerator'], $rule['denominator'], $rule['rounding'])),
                'evidence_required' => $rule['evidence_required'],
            ];
        }

        return $components;
    }

    /** The termin's share of the tax the project's pins hold for the code, or null where the pins hold none. */
    private static function terminTax(int $projectId, string $code, string $sales, ?int $exceptInvoiceId): ?string
    {
        $pinned = DB::selectOne(<<<'SQL'
            select coalesce(sum(p.sales_value), 0) as value,
                coalesce(sum((select sum((c->>'amount')::numeric) from jsonb_array_elements(p.tax_components) c where c->>'type' = ?)), 0) as tax
            from project_lines l join confirmation_line_pins p on p.id = l.current_pin_id where l.project_id = ?
            SQL, [$code, $projectId]);

        if (! is_object($pinned) || ! Money::of(Row::text($pinned, 'tax'))->isPositive()) {
            return null;
        }

        $invoiced = DB::selectOne(<<<'SQL'
            select coalesce(sum(v.sales_value), 0) as value,
                coalesce(sum((select sum(t.amount) from invoice_tax_components t join tax_types y on y.id = t.tax_type_id where t.invoice_version_id = v.id and y.code = ?)), 0) as tax
            from invoices i join invoice_versions v on v.id = i.current_version_id
            where i.project_id = ? and i.state = 'ISSUED' and i.origin = 'LIVE' and (?::bigint is null or i.id <> ?::bigint)
                and i.id is distinct from (select r.replaces_invoice_id from invoices r where r.id = ?::bigint)
            SQL, [$code, $projectId, $exceptInvoiceId, $exceptInvoiceId, $exceptInvoiceId]);

        return InvoiceValues::terminTax(Row::text($pinned, 'value'), Row::text($pinned, 'tax'), is_object($invoiced) ? Row::text($invoiced, 'value') : '0.00', is_object($invoiced) ? Row::text($invoiced, 'tax') : '0.00', $sales);
    }
}
