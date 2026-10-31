<?php

namespace App\Modules\PUR\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Queries\TaxConfiguration;
use App\Modules\PUR\Data\PurchaseEntry;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Services\PurchaseTaxes;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * Every payload reference of a purchase command resolved through the scope (CS-04; DP-03; WS-02): a project, demand line, product, unit, supplier, source bill or tax type of another company, or none, answers alike. Company, number, state, posted values and cost are never read from a payload.
 */
final class PurchaseInputs
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly TaxConfiguration $tax,
    ) {}

    /** The purchase by public id, in a company where the viewer holds the capability. */
    public function purchase(int $viewerId, string $publicId, string $capability): ?Purchase
    {
        $purchase = preg_match(self::UUID, $publicId) === 1 ? Purchase::query()->where('public_id', strtolower($publicId))->first() : null;

        return $purchase === null || ! $this->capabilities->for($viewerId)->allows($capability, $purchase->company_id) ? null : $purchase;
    }

    /**
     * The project of a project purchase; its company is the purchase's, shown and not chosen (L-05; CS-03).
     *
     * @return array{id: int, company: int}|null
     */
    public function project(int $viewerId, string $publicId, string $capability): ?array
    {
        $row = preg_match(self::UUID, $publicId) === 1 ? DB::table('projects')->where('public_id', strtolower($publicId))->first(['id', 'company_id']) : null;

        return $row === null || ! $this->capabilities->for($viewerId)->allows($capability, (int) Row::text($row, 'company_id')) ? null : ['id' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id')];
    }

    /** A replenishment purchase's chosen company (WF-PUR-02; CS-03). */
    public function company(int $viewerId, string $publicId, string $capability): ?int
    {
        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table('companies')->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) && $this->capabilities->for($viewerId)->allows($capability, (int) $id) ? (int) $id : null;
    }

    public function companyName(int $companyId): string
    {
        $name = DB::table('companies')->where('id', $companyId)->value('legal_name');

        return is_string($name) ? $name : '';
    }

    public function supplier(string $publicId): ?int
    {
        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table('suppliers')->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function product(string $publicId): ?int
    {
        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table('products')->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function unit(int $productId, string $code): ?int
    {
        $id = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->where('pu.product_id', $productId)->where('u.code', $code)->where('pu.is_active', true)->value('u.id');

        return is_numeric($id) ? (int) $id : null;
    }

    /** A confirmed demand line of the project, by its handle (L-05). */
    public function demandLine(?int $projectId, string $handle): ?int
    {
        foreach ($projectId === null ? [] : DB::table('project_lines')->where('project_id', $projectId)->whereNotNull('current_pin_id')->pluck('id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('project_lines', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /** A tax type by its code. */
    public function taxType(string $code): ?int
    {
        return $this->tax->typeOf($code);
    }

    /** A SUPPLIER_BILL of the company the viewer may see, by public id; another company's answers like none (L-43; PJ-08). */
    public function bill(int $viewerId, int $companyId, string $publicId): ?int
    {
        $row = preg_match(self::UUID, $publicId) === 1 ? DB::table('evidence_documents')->where('company_id', $companyId)->where('public_id', strtolower($publicId))->where('evidence_type', 'SUPPLIER_BILL')->first(['id', 'evidence_type']) : null;

        return $row !== null && EvidenceTypes::mayView($this->capabilities->for($viewerId), Row::text($row, 'evidence_type'), $companyId) ? (int) Row::text($row, 'id') : null;
    }

    /** A line of the purchase by its handle. */
    public function line(int $purchaseId, string $handle): ?int
    {
        foreach (DB::table('purchase_lines')->where('purchase_id', $purchaseId)->pluck('id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('purchase_lines', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /** A charge of the purchase by its handle. */
    public function charge(int $purchaseId, string $handle): ?int
    {
        foreach (DB::table('purchase_charges')->where('purchase_id', $purchaseId)->pluck('id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('purchase_charges', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * The treatment of each tax type the entry names, for its company, context and business date (DIR-024 D-1; BR-FIN-17).
     *
     * @return array<int, array{id: int, treatment: string}|null>
     */
    public function rules(PurchaseEntry $entry): array
    {
        $rules = [];

        foreach ($entry->lines as $line) {
            foreach ($line['taxes'] as $tax) {
                $rules[$tax['type']] ??= $this->tax->treatmentRule($entry->companyId, PurchaseTaxes::CONTEXT, $tax['type'], $entry->businessDate);
            }
        }

        return $rules;
    }
}
