<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `receivable_writeoffs` (AD) and §11.4: the Owner's disposal of an invoice's outstanding, of
// its company (BR-FIN-08; AX-24; C-32) — no Cash-In and no profit; superseded only by a contra committed with
// the recovery's application (L-17). One per invoice per command (§1), the outstanding before and after it
// stated.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivable_writeoffs', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->bigInteger('invoice_id');
            Columns::money($table, 'amount');
            $table->text('reason');
            Columns::money($table, 'outstanding_before');
            Columns::money($table, 'outstanding_after');
            Columns::user($table, 'decided_by');
            Columns::businessDate($table);
            Columns::commandIdentity($table, 'receivable_writeoffs');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('invoice_id');
        });

        TableRules::for('receivable_writeoffs')
            ->unique('receivable_writeoffs_c32_company_key', ['company_id', 'id'])
            ->unique('receivable_writeoffs_one_per_invoice_per_command', ['command_id', 'invoice_id'])
            ->foreign('receivable_writeoffs_c32_invoice_foreign', ['company_id', 'invoice_id'], 'invoices', ['company_id', 'id'])
            ->check('receivable_writeoffs_amounts', 'amount > 0 and outstanding_after >= 0 and outstanding_after = outstanding_before - amount')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('receivable_writeoffs');
    }
};
