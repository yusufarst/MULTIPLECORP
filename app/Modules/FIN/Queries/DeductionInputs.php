<?php

namespace App\Modules\FIN\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What ROUTE-255, ROUTE-260 and ROUTE-263's submitted identifiers name, read for the controller, which holds no database work (ARCHITECTURE §2): each within the capability the route declares, an unknown or out-of-scope one answering
 * alike (AZ-11; SM-01; DP-13).
 */
final class DeductionInputs
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /**
     * A payment the viewer may settle against.
     *
     * @return array{id: int, company: int, client: int}|null
     */
    public function payment(int $viewerId, string $publicId, string $capability): ?array
    {
        $row = preg_match(self::UUID, $publicId) === 1 ? DB::table('payments')->where('public_id', strtolower($publicId))->first(['id', 'company_id', 'client_organization_id']) : null;

        if ($row === null || ! $this->capabilities->for($viewerId)->allows($capability, (int) Row::text($row, 'company_id'))) {
            return null;
        }

        return ['id' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id'), 'client' => (int) Row::text($row, 'client_organization_id')];
    }

    /**
     * A claim of the payment, the head of its chain.
     *
     * @return array{class: string, tax_type: ?int, fee_type: ?string}|null
     */
    public function claim(int $paymentId, string $handle): ?array
    {
        foreach (DB::table('payment_claimed_deductions as c')->where('c.payment_id', $paymentId)->whereNotExists(fn (Builder $next) => $next->from('payment_claimed_deductions as n')->whereColumn('n.supersedes_claimed_deduction_id', 'c.id'))->get(['c.id', 'c.deduction_class', 'c.tax_type_id', 'c.fee_type']) as $row) {
            if (hash_equals(RowHandle::of('payment_claimed_deductions', (int) Row::text($row, 'id')), $handle)) {
                $tax = Row::nullable($row, 'tax_type_id');

                return ['class' => Row::text($row, 'deduction_class'), 'tax_type' => $tax === null ? null : (int) $tax, 'fee_type' => Row::nullable($row, 'fee_type')];
            }
        }

        return null;
    }

    /** An invoice of the company by public id. */
    public function invoice(int $companyId, string $publicId): ?int
    {
        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table('invoices')->where('company_id', $companyId)->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    /** Evidence of the company the viewer may see, by public id (PJ-08). */
    public function evidence(int $viewerId, int $companyId, string $publicId): ?int
    {
        $row = preg_match(self::UUID, $publicId) === 1 ? DB::table('evidence_documents')->where('company_id', $companyId)->where('public_id', strtolower($publicId))->first(['id', 'evidence_type']) : null;

        return $row !== null && EvidenceTypes::mayView($this->capabilities->for($viewerId), Row::text($row, 'evidence_type'), $companyId) ? (int) Row::text($row, 'id') : null;
    }

    /**
     * A fact of the kind by its identifier, in the companies where the viewer holds the capability.
     *
     * @return array{id: int, company: int}|null
     */
    public function fact(int $viewerId, string $table, string $identifier, string $capability): ?array
    {
        $viewer = $this->capabilities->for($viewerId);

        if ($table === 'disbursements') {
            $row = preg_match(self::UUID, $identifier) === 1 ? DB::table('disbursements')->where('public_id', strtolower($identifier))->first(['id', 'company_id']) : null;

            return $row !== null && $viewer->allows($capability, (int) Row::text($row, 'company_id')) ? ['id' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id')] : null;
        }

        if (! RowHandle::valid($identifier)) {
            return null;
        }

        foreach ($viewer->scope->apply(DB::table($table), 'company_id')->select(['id', 'company_id'])->lazyByIdDesc(1000) as $row) {
            if (hash_equals(RowHandle::of($table, (int) Row::text($row, 'id')), $identifier)) {
                return $viewer->allows($capability, (int) Row::text($row, 'company_id')) ? ['id' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id')] : null;
            }
        }

        return null;
    }

    public function companyPublicId(int $companyId): string
    {
        $id = DB::table('companies')->where('id', $companyId)->value('public_id');

        return is_string($id) ? $id : '';
    }
}
