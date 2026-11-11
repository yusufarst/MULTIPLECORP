<?php

namespace Tests\Support\Fixtures;

use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use App\Support\Search\Row;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * TASK-062's browser fixture (TS-11; TEST_STRATEGY §7): `ReconcileGuards` over every guard family, read-only, its
 * differences returned for the journey's own companies — each difference traced from its family's key to the company
 * that owns the record (a lot's source, a line's, an invoice's, a purchase's, a payment's, a case's). A journey runs on
 * companies created for its run, which no other spec of the shared browser database touches, so what it reads is its
 * own and deterministic; a difference whose record traces to no company at all is counted as the journey's, fail-closed.
 * The whole database's count is returned beside them as evidence.
 */
final class GuardsFixture
{
    /** Each family's key traced to the companies owning its record. */
    private const OWNER = [
        'stock_product_balances' => 'select l.source_company_id from stock_movement_entries e join inventory_lots l on l.id = e.lot_id where e.product_id = :k0 union select r.company_id from reservations r where r.product_id = :k0',
        'stock_lot_balances' => 'select source_company_id from inventory_lots where id = :k0',
        'project_line_balances' => 'select company_id from project_lines where id = :k0',
        'invoice_balances' => 'select company_id from invoices where id = :k0',
        'application_source_balances' => 'select company_id from application_source_balances where id = :k0',
        'evidence_balances' => 'select d.company_id from evidence_balances g join evidence_documents d on d.id = g.evidence_document_id where g.id = :k0 union select l.company_id from evidence_balances g join bank_statement_lines l on l.id = g.bank_statement_line_id where g.id = :k0 union select company_id from bank_statement_lines where id = -:k0',
        'money_fact_balances' => 'select company_id from money_fact_balances where id = :k0',
        'project_commercial_balances' => 'select company_id from projects where id = :k0',
        'lot_cost_balances' => 'select source_company_id from inventory_lots where id = :k0',
        'purchase_charge_balances' => 'select company_id from purchase_charges where id = :k0',
        'purchase_charge_share_balances' => 'select company_id from purchase_charges where id = :k0',
        'purchase_line_balances' => 'select company_id from purchase_lines where id = :k0',
        'correction_cases' => 'select company_id from correction_cases where id = :k0',
    ];

    public static function differences(Request $request): JsonResponse
    {
        $asked = array_values(array_filter(explode(',', (string) $request->query('perusahaan', '')), fn (string $id): bool => preg_match('/^[0-9a-f-]{36}$/i', $id) === 1));
        $companies = $asked === [] ? [] : array_map(fn (object $row): int => (int) Row::text($row, 'id'), DB::table('companies')->whereIn('public_id', array_map('strtolower', $asked))->get(['id'])->all());

        if ($asked === [] || count($companies) !== count($asked)) {
            return response()->json(['message' => 'Every company of the journey is named by its public identifier.'], 422);
        }

        $reconciler = new Reconciler;
        $journey = [];
        $all = 0;

        foreach (Families::all() as $family) {
            $owner = self::OWNER[$family->table()] ?? throw new LogicException("No company trace for the guard family {$family->table()}.");

            foreach ($reconciler->differences(DB::connection(), $family) as $difference) {
                $all++;
                $key = array_values($difference['key']);
                // Positional bindings, one per use of the key: a repeated named placeholder is not portable across PDO's prepares.
                $owners = array_map(fn (object $row): int => (int) array_values((array) $row)[0], DB::select(str_replace(':k0', '?', $owner), array_fill(0, substr_count($owner, ':k0'), $key[0] ?? null)));

                // Fail-closed: a record tracing to no company is the journey's to answer for.
                if ($owners === [] || array_intersect($owners, $companies) !== []) {
                    $journey[] = json_encode($difference, JSON_THROW_ON_ERROR);
                }
            }
        }

        // The verified families too (DATABASE §3.1), as ReconcileGuards runs them: a number issued above its counter. Their
        // key carries its company; a violation of none is the journey's, fail-closed.
        foreach (Families::verifications() as $verification) {
            foreach ($reconciler->violations(DB::connection(), $verification) as $violation) {
                $all++;
                $company = $violation['key']['company_id'] ?? null;

                if (! is_numeric($company) || in_array((int) $company, $companies, true)) {
                    $journey[] = json_encode($violation, JSON_THROW_ON_ERROR);
                }
            }
        }

        return response()->json(['differences' => $journey, 'all' => $all, 'families' => count(Families::all()) + count(Families::verifications())]);
    }
}
