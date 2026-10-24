<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\ReceivableWriteoff;
use App\Modules\IAM\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented Owner write-off of Rp100.000 of an invoice's outstanding Rp1.110.000.
 *
 * @extends Factory<ReceivableWriteoff>
 */
final class ReceivableWriteoffFactory extends Factory
{
    protected $model = ReceivableWriteoff::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'company_id' => static fn (array $row): int => Invoice::query()->whereKey($row['invoice_id'])->firstOrFail()->company_id,
            'amount' => '100000.00',
            'reason' => 'Sisa piutang tidak tertagih setelah konfirmasi klien',
            'outstanding_before' => '1110000.00',
            'outstanding_after' => '1010000.00',
            'decided_by' => User::factory(),
            'business_date' => '2026-10-08',
        ];
    }
}
