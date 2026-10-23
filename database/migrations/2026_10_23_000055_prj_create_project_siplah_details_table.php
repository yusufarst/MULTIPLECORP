<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.5 `project_siplah_details` (SR): a SIPLAH project's manual metadata (CAP-12), informational —
// its amounts never move money (BR-FIN-10). Editable with audit until the first channel-dependent commit
// sets `locked_at`; from then on the trigger refuses every change (BR-PRJ-05; C-51, APP + trigger).
return new class extends Migration
{
    private const AMOUNTS = ['siplah_value', 'real_purchase_value', 'fee_amount', 'va_fee_amount', 'cashback_amount', 'refund_amount'];

    public function up(): void
    {
        Schema::create('project_siplah_details', function (Blueprint $table) {
            $table->bigInteger('project_id')->primary();
            $table->text('external_reference')->nullable();
            $table->text('program_code')->nullable();
            $table->text('account_code')->nullable();

            foreach (self::AMOUNTS as $amount) {
                Columns::money($table, $amount)->nullable();
            }

            $table->text('notes')->nullable();
            Columns::timestamp($table, 'locked_at')->nullable();
            Columns::commandIdentity($table, 'project_siplah_details');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
        });

        TableRules::for('project_siplah_details')
            ->check('project_siplah_details_amounts_not_negative', implode(' and ', array_map(static fn (string $amount): string => "({$amount} is null or {$amount} >= 0)", self::AMOUNTS)))
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['external_reference', 'program_code', 'account_code', ...self::AMOUNTS, 'notes', 'locked_at', 'lock_version', 'updated_at']);

        DB::unprepared(<<<'SQL'
            create or replace function project_siplah_details_c51_locked() returns trigger
            language plpgsql as $$
            begin
                if old.locked_at is not null then
                    raise exception 'SIPLAH details are locked after the first channel-dependent commit (BR-PRJ-05; C-51).'
                        using errcode = 'check_violation', constraint = 'project_siplah_details_c51_locked';
                end if;

                return new;
            end
            $$;

            create trigger project_siplah_details_c51_locked
                before update on project_siplah_details
                for each row execute function project_siplah_details_c51_locked();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('project_siplah_details');
        DB::unprepared('drop function if exists project_siplah_details_c51_locked()');
    }
};
