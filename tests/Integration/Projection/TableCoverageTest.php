<?php

use App\Modules\IAM\Data\CrossCompanyLink;
use App\Modules\IAM\Data\TableClassification;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-012): every table of the schema has its PERMISSIONS_MATRIX §7.2 class in code, the stores
// outside the 125 being never projected raw (DP-09; AU-11; H6-04), and every column §8's links name exists.

uses(RefreshDatabase::class);

/** The framework's and the application's operational stores outside the 125 business tables (§7.2). */
const OPERATIONAL_STORES = ['credential_tokens', 'export_requests', 'failed_jobs', 'migrations', 'sessions'];

it('classifies every business table of the schema, and only those', function () {
    $tables = DB::table('information_schema.tables')
        ->where('table_schema', 'public')
        ->where('table_type', 'BASE TABLE')
        ->orderBy('table_name')
        ->pluck('table_name')
        ->all();

    $classified = array_keys(TableClassification::TABLES);
    sort($classified);

    expect(array_values(array_diff($tables, OPERATIONAL_STORES)))->toBe($classified);
});

it('finds every column of the five cross-company link families (XL-1–XL-5)', function () {
    foreach (CrossCompanyLink::cases() as $link) {
        foreach ($link->columns() as $column) {
            [$table, $name] = explode('.', $column);

            expect(DB::table('information_schema.columns')->where('table_name', $table)->where('column_name', $name)->exists())
                ->toBeTrue("{$link->value}: {$column}");
        }
    }
});
