<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `opening_customer_credits` (AF) and §24: a client's credit carried over at cutover, an
// application source and never live Cash-In (BR-XD-06; L-50), once per import row (AX-28; C-47), with its
// signed evidence of its company (C-32). Its company and client key the applications it funds (C-14).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_customer_credits', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'company_id', 'companies');
            Columns::reference($table, 'client_organization_id', 'client_organizations');
            Columns::money($table, 'amount');
            $table->bigInteger('import_row_id')->unique();
            $table->bigInteger('signed_evidence_id');
            Columns::businessDate($table);
            Columns::commandIdentity($table, 'opening_customer_credits');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('signed_evidence_id');
        });

        TableRules::for('opening_customer_credits')
            ->unique('opening_customer_credits_c32_company_key', ['company_id', 'id'])
            ->unique('opening_customer_credits_c14_client_key', ['company_id', 'client_organization_id', 'id'])
            ->foreign('opening_customer_credits_c47_import_row_foreign', ['import_row_id', 'company_id'], 'import_rows', ['id', 'company_id'])
            ->foreign('opening_customer_credits_c32_evidence_foreign', ['company_id', 'signed_evidence_id'], 'evidence_documents', ['company_id', 'id'])
            ->check('opening_customer_credits_amount_positive', 'amount > 0')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('opening_customer_credits');
    }
};
