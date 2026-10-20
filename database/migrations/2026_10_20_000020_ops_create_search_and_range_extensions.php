<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The two contrib extensions the schema relies on (DEP-07; TOOLCHAIN "Database roles and schema
// foundations"): `pg_trgm` for the name and title search of DATABASE §21, `btree_gist` for the
// exclusion constraints over effective ranges of §12 and §17 (C-52). Both are trusted extensions, which
// the migration role, owning the database, creates (DATABASE §28). An extension belongs to no table
// family (D-DB-01); OPS carries it, as it carries the framework's infrastructure table of TASK-005.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create extension if not exists pg_trgm');
        DB::statement('create extension if not exists btree_gist');
    }

    public function down(): void
    {
        DB::statement('drop extension if exists btree_gist');
        DB::statement('drop extension if exists pg_trgm');
    }
};
