<?php

use App\Modules\Reporting\Reports\Keyed;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Illuminate\Support\Facades\DB;
use Tests\Support\QueryBudget;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-068; QB-15; PF-28; PFM-08; PFM-16; PF-30): an export reads its rows in keyed chunks — one statement per chunk,
// each starting after the last key of the one before, never an offset —, so its memory is one chunk's; and ReconcileGuards
// reads each guard family in one set-based statement, its plan kept as evidence at 1x. Its duration is staging's (TASK-079);
// 100x is NOT TESTED for the initial release (APPR-035).

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

it('reads each guard family in one set-based statement, its plan kept as evidence (PFM-08; PF-30)', function () {
    $directory = storage_path('logs/verify/plans');
    is_dir($directory) || mkdir($directory, 0775, true);
    $reconciler = app(Reconciler::class);

    foreach (Families::all() as $family) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $reconciler->differences(DB::connection(), $family);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        // The guard columns' types come from the catalogue first; the family's data is read once, set-based.
        $reads = array_values(array_filter($log, fn (array $query): bool => preg_match('/^\s*(select|with)\b/i', (string) $query['query']) === 1
            && ! str_contains((string) $query['query'], 'pg_attribute')));
        expect($reads)->toHaveCount(1, "{$family->table()}: one set-based statement");

        $plan = (string) DB::scalar('explain (analyze, buffers, format json) '.$reads[0]['query'], $reads[0]['bindings']);
        file_put_contents($directory.'/reconcile-'.$family->table().'.json', $plan);
    }
});
