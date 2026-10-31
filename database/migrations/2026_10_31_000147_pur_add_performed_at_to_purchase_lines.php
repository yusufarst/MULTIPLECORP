<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// `DIR-101` F1 (DATABASE §4.6, §28): the performed fact of a NONSTOCK line, set and taken back only by ROUTE-180 and read by AX-12's guard. Expand-only, touching no row. The trigger keeping a line a draft until its
// first effect excepts this one column — a non-stock line carries its cost from the save (L-18), so it always has an effect —, and the mark posts no cost and is no first effect under L-23.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            Columns::timestamp($table, 'performed_at')->nullable();
        });

        TableRules::for('purchase_lines')
            ->check('purchase_lines_performed_nonstock', "performed_at is null or kind = 'NONSTOCK'")
            ->grantUpdate(['performed_at']);

        DB::unprepared(<<<'SQL'
            create or replace function purchase_lines_draft_until_first_effect() returns trigger
            language plpgsql as $$
            begin
                if (to_jsonb(new) - array['performed_at', 'lock_version', 'updated_at']) is not distinct from (to_jsonb(old) - array['performed_at', 'lock_version', 'updated_at']) then
                    return new;
                end if;

                if purchase_line_has_effect(old.id) then
                    raise exception 'A purchase line is edited only before its first effect, then adjusted (DATABASE §4.6; L-23).'
                        using errcode = 'check_violation', constraint = 'purchase_lines_draft_until_first_effect';
                end if;

                return new;
            end
            $$;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            create or replace function purchase_lines_draft_until_first_effect() returns trigger
            language plpgsql as $$
            begin
                if purchase_line_has_effect(old.id) then
                    raise exception 'A purchase line is edited only before its first effect, then adjusted (DATABASE §4.6; L-23).'
                        using errcode = 'check_violation', constraint = 'purchase_lines_draft_until_first_effect';
                end if;

                return new;
            end
            $$;
            SQL);

        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn('performed_at');
        });
    }
};
