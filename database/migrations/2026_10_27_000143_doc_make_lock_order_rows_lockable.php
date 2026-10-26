<?php

use App\Support\Database\DatabaseRole;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The lock order of CONCURRENCY_IDEMPOTENCY §4 takes these rows FOR NO KEY UPDATE (LR-03) — `documents` under LK-30,
// `document_types` under LK-08, `opening_customer_credits` under LK-26 —, which PostgreSQL grants only to a role
// holding UPDATE on a column of the table. The runtime role is given UPDATE of one column of each for that lock
// alone, and a trigger refuses every update of their rows, so no value ever changes (DIR-099; the pattern of
// DIR-097). Expand-only; no row is touched.
return new class extends Migration
{
    /** Each table, the one column its lock is granted through, and its lock class. */
    private const TABLES = [
        'documents' => ['mode', 'LK-30'],
        'document_types' => ['is_numbered', 'LK-08'],
        'opening_customer_credits' => ['import_row_id', 'LK-26'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => [$column, $lock]) {
            TableRules::for($table)->grantUpdate([$column]);
            $name = "{$table}_unchanged";

            DB::unprepared(<<<SQL
                create or replace function {$name}() returns trigger
                language plpgsql as \$\$
                begin
                    raise exception 'A {$table} row never changes: its update privilege serves the row lock of {$lock} only (DIR-099).'
                        using errcode = 'check_violation', constraint = '{$name}';
                end
                \$\$;

                create trigger {$name}
                    before update on {$table}
                    for each row execute function {$name}();
                SQL);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => [$column]) {
            DB::statement("drop trigger {$table}_unchanged on {$table}");
            DB::statement("drop function {$table}_unchanged()");
            DB::statement("revoke update ({$column}) on table {$table} from ".DatabaseRole::RUNTIME);
        }
    }
};
