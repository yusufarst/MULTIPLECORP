<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `stock_movements` (AF) and §5.3: the header of every inventory command (WF-INV-01–07), of
// one company — whose project, purchase and case it keys in that company, all-or-none (§1; C-32) — or of
// none for a pool-wide command (a location move, a count, a pending case). A reversal names the movement
// it reverses by a typed pair, of the types its kind may reverse; a movement that created consumptions is
// never MOVEMENT_REVERSAL'd — capped restorations undo it — and a resolution is reversed at most once.
// The unattributed case it serves is keyed by 2026_10_23_000092.
return new class extends Migration
{
    private const TYPES = [
        'RECEIPT', 'RECEIPT_REVERSAL', 'DISPATCH', 'RESTORATION', 'IDENTITY_SUBSTITUTION', 'PURCHASE_RETURN', 'CONDITION_CHANGE',
        'LOCATION_MOVE', 'ADJUSTMENT_DECREASE', 'COUNT_VARIANCE', 'LOSS', 'OPENING', 'DROPSHIP_RETURN_RECEIPT', 'SURPLUS_ATTRIBUTION',
        'UNATTRIBUTED_RECOGNITION', 'UNATTRIBUTED_CONVERSION', 'UNATTRIBUTED_RESOLUTION', 'UNATTRIBUTED_RESOLUTION_REVERSAL',
        'UNATTRIBUTED_FOUND', 'MOVEMENT_REVERSAL',
    ];

    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            $table->text('movement_type');
            Columns::businessDate($table);
            $table->text('reason')->nullable();
            Columns::reference($table, 'company_id', 'companies')->nullable();
            $table->bigInteger('project_id')->nullable();
            $table->bigInteger('purchase_id')->nullable();
            $table->bigInteger('correction_case_id')->nullable();
            Columns::reference($table, 'stock_count_id', 'stock_counts')->nullable();
            $table->bigInteger('unattributed_case_id')->nullable();
            $table->bigInteger('reverses_movement_id')->nullable();
            $table->text('reverses_movement_type')->nullable();
            $table->text('supplier_delivery_reference')->nullable();
            Columns::commandIdentity($table, 'stock_movements');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('stock_movements')
            ->unique('stock_movements_type_key', ['id', 'movement_type'])
            ->unique('stock_movements_c32_company_key', ['company_id', 'id'])
            ->foreign('stock_movements_c32_project_foreign', ['company_id', 'project_id'], 'projects', ['company_id', 'id'])
            ->foreign('stock_movements_c32_purchase_foreign', ['company_id', 'purchase_id'], 'purchases', ['company_id', 'id'])
            ->foreign('stock_movements_c32_correction_case_foreign', ['company_id', 'correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->foreign('stock_movements_reverses_foreign', ['reverses_movement_id', 'reverses_movement_type'], 'stock_movements', ['id', 'movement_type'])
            ->codes('stock_movements_type_code', 'movement_type', self::TYPES)
            ->check('stock_movements_c32_company_members', '(project_id is null or company_id is not null) and (purchase_id is null or company_id is not null) and (correction_case_id is null or company_id is not null)')
            ->check('stock_movements_c55_reversal_by_type', "(movement_type in ('RECEIPT_REVERSAL', 'MOVEMENT_REVERSAL', 'UNATTRIBUTED_RESOLUTION_REVERSAL')) = (reverses_movement_id is not null)")
            ->check('stock_movements_c55_reversal_pair', '(reverses_movement_id is null) = (reverses_movement_type is null)')
            ->check('stock_movements_c55_movement_reversal_types', "movement_type <> 'MOVEMENT_REVERSAL' or reverses_movement_type in ('CONDITION_CHANGE', 'RESTORATION', 'DROPSHIP_RETURN_RECEIPT', 'SURPLUS_ATTRIBUTION', 'OPENING', 'UNATTRIBUTED_RECOGNITION', 'UNATTRIBUTED_FOUND', 'UNATTRIBUTED_CONVERSION')")
            ->check('stock_movements_c55_receipt_reversal_type', "movement_type <> 'RECEIPT_REVERSAL' or reverses_movement_type = 'RECEIPT'")
            ->check('stock_movements_c55_resolution_reversal_type', "movement_type <> 'UNATTRIBUTED_RESOLUTION_REVERSAL' or reverses_movement_type = 'UNATTRIBUTED_RESOLUTION'")
            ->uniqueIndex('stock_movements_c37_one_resolution_reversal', 'reverses_movement_id', "movement_type = 'UNATTRIBUTED_RESOLUTION_REVERSAL'")
            // IX-11: a company's movements and the pool's by business date (PF-13, PF-41).
            ->index('stock_movements_ix11_company_business_date', 'company_id, business_date, id')
            ->index('stock_movements_ix11_business_date', 'business_date, id')
            // §20: receiving and dispatch history; a reversed movement's reversals (IX-16).
            ->index('stock_movements_type_business_date', 'movement_type, business_date')
            ->index('stock_movements_project_business_date', 'project_id, business_date', 'project_id is not null')
            ->index('stock_movements_purchase', 'purchase_id', 'purchase_id is not null')
            ->index('stock_movements_reverses', 'reverses_movement_id', 'reverses_movement_id is not null')
            ->index('stock_movements_stock_count', 'stock_count_id', 'stock_count_id is not null')
            ->index('stock_movements_unattributed_case', 'unattributed_case_id', 'unattributed_case_id is not null')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('stock_movements');
    }
};
