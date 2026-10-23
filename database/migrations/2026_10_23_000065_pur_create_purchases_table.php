<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.6 `purchases` (SR): a purchase of its company, for a project of the same company or for
// replenishment (L-05; C-32), with the supplier as used (§14). Its business number is drawn at its save
// from the company's PURCHASE counter and never reused (DIR-077 CA-09; C-10), so the number columns are not
// updatable; an imported purchase keeps a legacy number that fits the scheme (DIR-079 CB-01). A purchase is
// never deleted: a cancellation keeps its row, its number and the actor, time and reason (BR-PUR-01–04).
// The PO is a document of this record (DOC-02/DOC-14; BR-DOC-04), not another table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->text('purchase_number');
            $table->text('period_key');
            $table->bigInteger('sequence_no');
            Columns::reference($table, 'supplier_id', 'suppliers');
            // The supplier's name, tax identifier and address as used at commit (§14).
            $table->jsonb('supplier_snapshot');
            $table->bigInteger('project_id')->nullable();
            Columns::businessDate($table);
            $table->text('supplier_reference')->nullable();
            $table->text('state')->default('OPEN');
            Columns::user($table, 'cancelled_by')->nullable();
            Columns::timestamp($table, 'cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->text('origin')->default('LIVE');
            Columns::reference($table, 'import_row_id', 'import_rows')->nullable()->unique();
            Columns::commandIdentity($table, 'purchases');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('purchases')
            ->unique('purchases_c10_number', ['company_id', 'purchase_number'])
            ->unique('purchases_c32_company_key', ['company_id', 'id'])
            ->foreign('purchases_c32_project_foreign', ['company_id', 'project_id'], 'projects', ['company_id', 'id'])
            ->codes('purchases_state_code', 'state', ['OPEN', 'CLOSED', 'CANCELLED'])
            ->codes('purchases_origin_code', 'origin', ['LIVE', 'OPENING'])
            ->check('purchases_period_key_format', "period_key ~ '^[0-9]{4}(-(0[1-9]|1[0-2]))?$'")
            ->check('purchases_supplier_snapshot_object', "jsonb_typeof(supplier_snapshot) = 'object'")
            ->check('purchases_cancellation_columns', "num_nonnulls(cancelled_by, cancelled_at, cancelled_reason) in (0, 3) and (state = 'CANCELLED') = (cancelled_at is not null)")
            // IX-11: a company's purchases by business date (PF-13, PF-41); IX-14: its open purchases (QS-05).
            ->index('purchases_ix11_company_business_date', 'company_id, business_date, id')
            ->index('purchases_ix14_company_state', 'company_id, state')
            ->index('purchases_project', 'project_id', 'project_id is not null')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['state', 'cancelled_by', 'cancelled_at', 'cancelled_reason', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('purchases');
    }
};
