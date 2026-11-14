<?php

// The mixed-version safeguard of TASK-072 (PRQ-04; HO-31; GAP-012; TEST_STRATEGY §15; INFRASTRUCTURE §5 step 4): a migration
// added after the first release is expand-only — its `up()` never drops, renames or retypes a column or a table —, so the
// previous release keeps running against the expanded schema during a deployment; a removal waits for a later release as
// a migration whose name says `_contract_`. The migrations of the baseline before the first release are the schema itself.

const EXPAND_BASELINE = '2026_11_14_999999';

/** The body of a migration's `up()`, or the whole source when it has none. */
function migrationUp(string $source): string
{
    return preg_match('/function\s+up\s*\([^)]*\)\s*:\s*void\s*\{(.*?)\n    \}/s', $source, $match) === 1 ? $match[1] : $source;
}

/** The destructive operations an expand migration's `up()` holds, by name. */
function contractions(string $up): array
{
    $patterns = [
        'drop a column' => '/->drop(Column|Columns|IfExists)?\s*\(|->dropColumn|\bDROP\s+COLUMN\b/i',
        'drop a table' => '/Schema::drop(IfExists)?\s*\(|\bDROP\s+TABLE\b/i',
        'rename' => '/->rename(Column)?\s*\(|Schema::rename\s*\(|\bRENAME\s+(COLUMN|TO)\b/i',
        'retype a column' => '/->change\s*\(\s*\)|\bALTER\s+COLUMN\s+\S+\s+(SET\s+DATA\s+)?TYPE\b/i',
    ];

    return array_keys(array_filter($patterns, fn (string $pattern): bool => preg_match($pattern, $up) === 1));
}

it('keeps every migration after the baseline expand-only, a contraction waiting for its own release', function () {
    $refused = [];

    foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
        $name = basename($file);

        if (strcmp($name, EXPAND_BASELINE) <= 0 || str_contains($name, '_contract_')) {
            continue;
        }

        foreach (contractions(migrationUp((string) file_get_contents($file))) as $what) {
            $refused[] = "{$name}: {$what}";
        }
    }

    expect($refused)->toBe([]);
});

it('finds each contraction and lets an expansion through', function () {
    $migration = fn (string $body): string => "<?php\nreturn new class extends Migration {\n    public function up(): void\n    {\n{$body}\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('t');\n    }\n};\n";

    expect(contractions(migrationUp($migration("        Schema::table('t', fn (\$t) => \$t->dropColumn('c'));"))))->toBe(['drop a column'])
        ->and(contractions(migrationUp($migration("        Schema::drop('t');"))))->toBe(['drop a table'])
        ->and(contractions(migrationUp($migration("        Schema::table('t', fn (\$t) => \$t->renameColumn('a', 'b'));"))))->toBe(['rename'])
        ->and(contractions(migrationUp($migration("        Schema::table('t', fn (\$t) => \$t->string('c', 20)->change());"))))->toBe(['retype a column'])
        ->and(contractions(migrationUp($migration("        DB::statement('ALTER TABLE t ALTER COLUMN c TYPE bigint');"))))->toBe(['retype a column'])
        ->and(contractions(migrationUp($migration("        Schema::table('t', fn (\$t) => \$t->string('c')->nullable());"))))->toBe([]);
});
