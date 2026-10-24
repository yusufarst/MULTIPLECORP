<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.9 `dropship_confirmations` (AF): a supplier's direct shipment to the client, confirmed once per
// command, with zero warehouse events (BR-INV-11). Its project and purchase are its company's (C-32), and the
// supplier's shipment reference identifies it once per purchase (AX-08; C-49). DOC-03 renders it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropship_confirmations', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->bigInteger('project_id');
            $table->bigInteger('purchase_id');
            $table->text('supplier_shipment_reference');
            Columns::businessDate($table);
            Columns::commandIdentity($table, 'dropship_confirmations');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('project_id');
        });

        TableRules::for('dropship_confirmations')
            ->unique('dropship_confirmations_c32_company_key', ['company_id', 'id'])
            ->unique('dropship_confirmations_c49_shipment', ['purchase_id', 'supplier_shipment_reference'])
            ->unique('dropship_confirmations_one_per_command', ['command_id'])
            ->foreign('dropship_confirmations_c32_project_foreign', ['company_id', 'project_id'], 'projects', ['company_id', 'id'])
            ->foreign('dropship_confirmations_c32_purchase_foreign', ['company_id', 'purchase_id'], 'purchases', ['company_id', 'id'])
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('dropship_confirmations');
    }
};
