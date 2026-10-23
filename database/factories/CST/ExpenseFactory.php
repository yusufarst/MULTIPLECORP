<?php

namespace Database\Factories\CST;

use App\Modules\CST\Models\Expense;
use App\Modules\ORG\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented manual company-level expense.
 *
 * @extends Factory<Expense>
 */
final class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'kind' => 'MANUAL',
            'category' => 'Operasional kantor',
            'amount' => '250000.00',
            'business_date' => '2026-10-01',
        ];
    }
}
