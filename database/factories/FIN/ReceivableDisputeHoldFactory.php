<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\ReceivableDisputeHold;
use App\Modules\IAM\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented uncleared dispute hold on an invoice, set by an invented user.
 *
 * @extends Factory<ReceivableDisputeHold>
 */
final class ReceivableDisputeHoldFactory extends Factory
{
    protected $model = ReceivableDisputeHold::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'reason' => 'Klien mempertanyakan jumlah barang yang ditagih',
            'set_by' => User::factory(),
        ];
    }
}
