<?php

use App\Support\Database\DatabaseRole;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// LK-02 (CONCURRENCY_IDEMPOTENCY §4; RV-04, RV-18; LR-03): the Owner set is locked as the built-in `roles` row
// OWNER, FOR NO KEY UPDATE, which PostgreSQL grants only to a role holding UPDATE on a column of the table. The
// runtime role is given UPDATE of `is_builtin` for that lock alone, and a trigger refuses every update of a role
// row, so no value of the catalogue's roles ever changes (RG-01). Expand-only; no row is touched.
return new class extends Migration
{
    public function up(): void
    {
        TableRules::for('roles')->grantUpdate(['is_builtin']);

        DB::unprepared(<<<'SQL'
            create or replace function roles_rg01_unchanged() returns trigger
            language plpgsql as $$
            begin
                raise exception 'A role row never changes: its update privilege serves the Owner-set lock only (LK-02; RG-01).'
                    using errcode = 'check_violation', constraint = 'roles_rg01_unchanged';
            end
            $$;

            create trigger roles_rg01_unchanged
                before update on roles
                for each row execute function roles_rg01_unchanged();
            SQL);
    }

    public function down(): void
    {
        DB::statement('drop trigger roles_rg01_unchanged on roles');
        DB::statement('drop function roles_rg01_unchanged()');
        DB::statement('revoke update (is_builtin) on table roles from '.DatabaseRole::RUNTIME);
    }
};
