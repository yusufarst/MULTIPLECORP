<?php

use App\Modules\Reporting\Reports\Keyed;
use Illuminate\Support\Facades\DB;
use Tests\Support\QueryBudget;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-068; QB-15; PF-28; PFM-16): an export reads its rows in keyed chunks — one statement per chunk, each starting
// after the last key of the one before, never an offset —, so its memory is one chunk's. ReconcileGuards' plans are
// IndexRegisterTest's, on the register's synthetic data; its duration is staging's (TASK-079).

uses(RefreshDatabase::class);

it('reads an export in keyed chunks, never an offset, one statement per chunk (QB-15; PF-28)', function () {
    DB::table('units')->insert(array_map(fn (int $n): array => ['code' => sprintf('QB15-%03d', $n), 'name' => "Satuan {$n}"], range(1, 35)));
    $query = DB::table('units')->where('code', 'like', 'QB15-%')->select('code');
    $codes = [];
    $statements = QueryBudget::statements(function () use ($query, &$codes): void {
        foreach (Keyed::rows($query, ['code' => 'code'], 10) as $row) {
            $codes[] = (string) $row->code;
        }
    });

    expect($codes)->toBe(array_map(fn (int $n): string => sprintf('QB15-%03d', $n), range(1, 35)))
        ->and($statements)->toHaveCount(4)
        ->and(array_values(array_filter($statements, fn (string $sql): bool => preg_match('/\boffset\b/i', $sql) === 1)))->toBe([])
        ->and(count(array_filter($statements, fn (string $sql): bool => str_contains($sql, '(code) > (?)'))))->toBe(3);
});
