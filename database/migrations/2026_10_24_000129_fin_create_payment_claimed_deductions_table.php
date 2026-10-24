<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `payment_claimed_deductions` (AF) and §11.4: the remittance advice of a payment — a fee or a
// tax the client says it deducted —, insertable when it arrives, even after the payment; the claimed gross
// is the payment plus its current rows (L-28). A wrong figure is corrected by a superseding row of the same
// payment with reason, once (C-59); the source guard keeps the claim from falling below what is settled.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_claimed_deductions', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'payment_id', 'payments');
            $table->text('deduction_class');
            Columns::reference($table, 'tax_type_id', 'tax_types')->nullable();
            $table->text('fee_type')->nullable();
            Columns::money($table, 'claimed_amount');
            $table->bigInteger('supersedes_claimed_deduction_id')->nullable();
            $table->text('reason')->nullable();
            Columns::commandIdentity($table, 'payment_claimed_deductions');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('payment_id');
        });

        TableRules::for('payment_claimed_deductions')
            ->unique('payment_claimed_deductions_payment_key', ['payment_id', 'id'])
            ->unique('payment_claimed_deductions_c59_linear_chain', ['supersedes_claimed_deduction_id'])
            ->foreign('payment_claimed_deductions_c59_supersedes_foreign', ['payment_id', 'supersedes_claimed_deduction_id'], 'payment_claimed_deductions', ['payment_id', 'id'])
            ->codes('payment_claimed_deductions_class_code', 'deduction_class', ['FEE', 'TAX'])
            ->check('payment_claimed_deductions_type_by_class', "(deduction_class = 'TAX') = (tax_type_id is not null) and (deduction_class = 'FEE') = (fee_type is not null)")
            ->check('payment_claimed_deductions_amount_positive', 'claimed_amount > 0')
            ->check('payment_claimed_deductions_c59_reason', 'supersedes_claimed_deduction_id is null or reason is not null')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('payment_claimed_deductions');
    }
};
