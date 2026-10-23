<?php

namespace Database\Factories\PUR;

use App\Modules\ORG\Models\Company;
use App\Modules\PTY\Models\Supplier;
use App\Modules\PUR\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented OPEN replenishment purchase from an invented supplier, its number its company's PURCHASE
 * number of 2026.
 *
 * @extends Factory<Purchase>
 */
final class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'company_id' => Company::factory(),
            'purchase_number' => sprintf('CTH/PO/2026/%04d', $number),
            'period_key' => '2026',
            'sequence_no' => $number,
            'supplier_id' => Supplier::factory(),
            'supplier_snapshot' => ['nama' => 'Pemasok Contoh', 'npwp' => null, 'alamat' => 'Jl. Contoh No. 1'],
            'business_date' => '2026-10-01',
        ];
    }

    /** The company of an invented row's purchase (C-32). */
    public static function companyOf(mixed $purchase): int
    {
        return Purchase::query()->whereKey($purchase)->firstOrFail()->company_id;
    }
}
