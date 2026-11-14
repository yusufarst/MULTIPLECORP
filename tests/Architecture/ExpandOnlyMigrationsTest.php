<?php

// The mixed-version safeguard of TASK-072 (PRQ-04; HO-31; GAP-012; TEST_STRATEGY §15; INFRASTRUCTURE §5 step 4): a migration
// added after the first release is expand-only — its `up()` never drops, renames or retypes a column or a table —, so the
// previous release keeps running against the expanded schema during a deployment; a removal waits for a later release as
// a migration whose name says `_contract_`. The migrations of the baseline before the first release are the schema itself,
// listed by name in expand-baseline.txt, so a migration whose name is dated earlier is never let through by its date.

/** The baseline's migration file names. */
function expandBaseline(): array
{
    $lines = file(__DIR__.'/expand-baseline.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    return array_values(array_filter($lines, fn (string $line): bool => ! str_starts_with($line, '#')));
}

/** The body of a migration's `up()`, or the whole source when it has none. */
function migrationUp(string $source): string
{
    return preg_match('/function\s+up\s*\([^)]*\)\s*:\s*void\s*\{(.*?)\n    \}/s', $source, $match) === 1 ? $match[1] : $source;
}

/** The destructive operations an expand migration's `up()` holds, by name. */
function contractions(string $up): array
{
    $patterns = [
        // Any Blueprint drop but an index's, a key's or a constraint's — dropColumn(s), dropConstrainedForeignId,
        // dropTimestamps, dropSoftDeletes, dropMorphs and the rest —; in SQL, a DROP in an ALTER TABLE but a constraint's,
        // a default's or a NOT NULL's.
        'drop a column' => '/Schema::dropColumns\s*\(|->drop(?!(Index|Unique|Primary|Foreign|ForeignIdFor|FullText|SpatialIndex)\b)\w*\s*\(|\bDROP\s+COLUMN\b|\bALTER\s+TABLE\b[^;]*?[\s,]DROP\s+(?!CONSTRAINT\b|DEFAULT\b|NOT\s+NULL\b|EXPRESSION\b|IDENTITY\b)/is',
        'drop a table' => '/Schema::drop(?!Columns\b)\w*\s*\(|\bDROP\s+TABLE\b/i',
        'rename' => '/->rename(Column)?\s*\(|Schema::rename\s*\(|\bALTER\s+TABLE\b[^;]*?\bRENAME\b/is',
        'retype a column' => '/->change\s*\(\s*\)|\bALTER\s+(COLUMN\s+)?(?!TABLE\b)\S+\s+(SET\s+DATA\s+)?TYPE\b/i',
    ];

    return array_keys(array_filter($patterns, fn (string $pattern): bool => preg_match($pattern, $up) === 1));
}

it('keeps every migration after the baseline expand-only, a contraction waiting for its own release', function () {
    $baseline = array_flip(expandBaseline());
    $refused = [];

    foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
        $name = basename($file);

        if (isset($baseline[$name]) || str_contains($name, '_contract_')) {
            continue;
        }

        foreach (contractions(migrationUp((string) file_get_contents($file))) as $what) {
            $refused[] = "{$name}: {$what}";
        }
    }

    expect($refused)->toBe([]);
});

it('holds a baseline of migrations that exist, frozen at the first release\'s schema', function () {
    $baseline = expandBaseline();
    $present = array_map(basename(...), glob(database_path('migrations/*.php')) ?: []);

    expect($baseline)->toHaveCount(150)
        ->and(array_values(array_diff($baseline, $present)))->toBe([]);
});

it('finds each contraction and lets an expansion through', function () {
    $migration = fn (string $body): string => "<?php\nreturn new class extends Migration {\n    public function up(): void\n    {\n{$body}\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('t');\n    }\n};\n";
    $found = fn (string $body): array => contractions(migrationUp($migration($body)));

    expect($found("        Schema::table('t', fn (\$t) => \$t->dropColumn('c'));"))->toBe(['drop a column'])
        ->and($found("        Schema::dropColumns('t', ['c']);"))->toBe(['drop a column'])
        ->and($found("        Schema::table('t', fn (\$t) => \$t->dropConstrainedForeignId('c_id'));"))->toBe(['drop a column'])
        ->and($found("        Schema::table('t', fn (\$t) => \$t->dropTimestamps());"))->toBe(['drop a column'])
        ->and($found("        Schema::table('t', fn (\$t) => \$t->dropSoftDeletes());"))->toBe(['drop a column'])
        ->and($found("        Schema::table('t', fn (\$t) => \$t->dropMorphs('m'));"))->toBe(['drop a column'])
        ->and($found("        DB::statement('ALTER TABLE t DROP c');"))->toBe(['drop a column'])
        ->and($found("        DB::statement('ALTER TABLE t ADD d int, DROP COLUMN IF EXISTS c');"))->toBe(['drop a column'])
        ->and($found("        Schema::drop('t');"))->toBe(['drop a table'])
        ->and($found("        Schema::table('t', fn (\$t) => \$t->renameColumn('a', 'b'));"))->toBe(['rename'])
        ->and($found("        DB::statement('ALTER TABLE t RENAME c TO d');"))->toBe(['rename'])
        ->and($found("        Schema::table('t', fn (\$t) => \$t->string('c', 20)->change());"))->toBe(['retype a column'])
        ->and($found("        DB::statement('ALTER TABLE t ALTER COLUMN c TYPE bigint');"))->toBe(['retype a column'])
        ->and($found("        DB::statement('ALTER TABLE t ALTER c TYPE bigint');"))->toBe(['retype a column'])
        ->and($found("        DB::statement('ALTER TABLE t ALTER COLUMN c SET DATA TYPE bigint');"))->toBe(['retype a column'])
        // Expansions and an index's, a key's or a constraint's change pass.
        ->and($found("        Schema::table('t', fn (\$t) => \$t->string('c')->nullable());"))->toBe([])
        ->and($found("        Schema::table('t', fn (\$t) => \$t->dropIndex('t_c_index'));"))->toBe([])
        ->and($found("        Schema::table('t', fn (\$t) => \$t->dropForeign(['c_id']));"))->toBe([])
        ->and($found("        Schema::table('t', fn (\$t) => \$t->dropUnique(['c']));"))->toBe([])
        ->and($found("        DB::statement('ALTER TABLE t DROP CONSTRAINT t_c_check');"))->toBe([])
        ->and($found("        DB::statement('ALTER TABLE t ALTER COLUMN c DROP DEFAULT');"))->toBe([])
        ->and($found("        DB::statement('ALTER TABLE t ALTER COLUMN c DROP NOT NULL');"))->toBe([])
        ->and($found("        DB::statement('ALTER TABLE t ADD COLUMN c int');"))->toBe([]);
});
