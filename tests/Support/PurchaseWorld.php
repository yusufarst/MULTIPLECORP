<?php

namespace Tests\Support;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\Unit;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PTY\Models\Supplier;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseLine;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\INV\LocationFactory;
use Database\Factories\ORG\TaxTreatmentRuleFactory;
use Database\Factories\ORG\TaxTypeFactory;
use Illuminate\Support\Str;

/**
 * TASK-037's world (TL-04–TL-08): a company with its PURCHASE numbering, a supplier, products with their units, tax types with their treatment, a bill, and ROUTE-171's request.
 * Static, so the closures of the two-connection harness can call them (Sessions::run).
 */
final class PurchaseWorld
{
    public const CAPABILITIES = ['cost.view', 'projects.view', 'purchase.manage', 'purchase.cancel', 'purchase.close_remainder', 'purchase.correct_cost', 'evidence.view', 'documents.issue'];

    /** A company whose PURCHASE numbering may create its counter. */
    public static function numbered(Company $company): Company
    {
        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::goLive("{$year}-01-01");
        Numbering::scheme($company, SequenceType::Purchase, "{$year}-01-01");
        Numbering::started($company, SequenceType::Purchase, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));

        return $company;
    }

    /** @param  list<string>  $extra */
    public static function actor(Company $company, array $extra = []): User
    {
        return User::query()->findOrFail(Authorization::admin([$company->id], array_values(array_unique([...self::CAPABILITIES, ...$extra])))->id);
    }

    public static function supplier(): Supplier
    {
        return Supplier::factory()->create();
    }

    /** A purchase-line tax type under a treatment for the company from the date; its code. */
    public static function tax(?int $companyId, string $treatment = 'INPUT_CREDITABLE', string $from = '2026-01-01'): string
    {
        $code = 'PPN-'.Str::upper(Str::random(6));
        $type = TaxTypeFactory::new()->createOne(['code' => $code]);
        TaxTreatmentRuleFactory::new()->createOne(['tax_type_id' => $type->id, 'company_id' => $companyId, 'transaction_context' => 'PURCHASE_LINE', 'treatment' => $treatment, 'effective' => "[{$from},)"]);

        return $code;
    }

    /** A supplier bill of the company with its proven total; its public id. */
    public static function bill(int $companyId, string $total = '1000000.00'): string
    {
        return SettlementWorld::evidence($companyId, 'SUPPLIER_BILL', $total)->public_id;
    }

    /**
     * A line of ROUTE-171's request.
     *
     * @param  array{product: Product, base: Unit, box: ?Unit}  $product
     * @param  array<string, mixed>  $more
     * @return array<string, mixed>
     */
    public static function line(array $product, string $quantity = '10', string $price = '100000', array $more = []): array
    {
        return ['jenis' => 'GOODS', 'produk' => $product['product']->public_id, 'satuan' => $product['base']->code, 'jumlah' => $quantity, 'harga' => $price, ...$more];
    }

    /**
     * ROUTE-171's request.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $more
     * @return array<string, mixed>
     */
    public static function payload(Supplier $supplier, array $lines, array $more = []): array
    {
        return ['command_id' => (string) Str::uuid(), 'pemasok' => $supplier->public_id, 'tanggal' => BusinessDate::today(), 'baris' => $lines, ...$more];
    }

    public static function latest(int $companyId): Purchase
    {
        return Purchase::query()->where('company_id', $companyId)->orderByDesc('id')->firstOrFail();
    }

    /** @return list<PurchaseLine> */
    public static function lines(Purchase $purchase): array
    {
        return array_values(PurchaseLine::query()->where('purchase_id', $purchase->id)->orderBy('line_no')->get()->all());
    }

    /** CostWorld's synthetic receipts and draws on the line's product, until TASK-039 and TASK-041 exist. */
    public static function cost(Company $company, int $actorId, int $productId): CostWorld
    {
        return CostWorld::resume(['company' => $company->id, 'product' => $productId, 'location' => LocationFactory::new()->createOne()->id, 'actor' => $actorId]);
    }
}
