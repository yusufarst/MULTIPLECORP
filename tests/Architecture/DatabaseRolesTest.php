<?php

use App\Support\Database\DatabaseRole;

// SECURITY H9-01 and DATABASE §28 (TASK-005): the migration/owner role is used only by migrations — no
// application code reaches its connection — and every database test runs through the project's traits,
// which migrate as that role and test as the runtime.

/**
 * @param  list<string>  $paths
 * @return list<string> "path:line: text" of each line matching $pattern
 */
function linesMatching(array $paths, string $pattern, array $except = []): array
{
    $found = [];

    foreach ($paths as $path) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            $name = str_replace('\\', '/', (string) $file);

            if (! str_ends_with($name, '.php') || array_filter($except, fn (string $allowed): bool => str_ends_with($name, $allowed)) !== []) {
                continue;
            }

            foreach (file($name) ?: [] as $number => $line) {
                if (preg_match($pattern, $line) === 1) {
                    $found[] = sprintf('%s:%d: %s', $name, $number + 1, trim($line));
                }
            }
        }
    }

    return $found;
}

it('keeps the migration connection out of the application code', function () {
    $pattern = '/MIGRATION_CONNECTION|connection\(\s*[\'"]'.DatabaseRole::MIGRATION_CONNECTION.'[\'"]/';

    expect(linesMatching([app_path(), base_path('routes')], $pattern, ['/app/Support/Database/DatabaseRole.php']))->toBe([]);
});

it('detects the migration connection named in application code', function () {
    $fixture = sys_get_temp_dir().'/migration-connection-'.getmypid();
    mkdir($fixture);
    file_put_contents("{$fixture}/Leak.php", "<?php\nDB::connection('migration')->statement('select 1');\n");

    try {
        expect(linesMatching([$fixture], '/MIGRATION_CONNECTION|connection\(\s*[\'"]migration[\'"]/'))->not->toBe([]);
    } finally {
        unlink("{$fixture}/Leak.php");
        rmdir($fixture);
    }
});

it('runs every database test through the project\'s traits, never the framework\'s directly', function () {
    $pattern = '/Illuminate\\\\Foundation\\\\Testing\\\\(RefreshDatabase|DatabaseTruncation|DatabaseMigrations|DatabaseTransactions)\b/';

    expect(linesMatching([base_path('tests')], $pattern, ['/tests/TestCase.php', '/tests/Support/RefreshDatabase.php', '/tests/Support/DatabaseTruncation.php', '/tests/Architecture/DatabaseRolesTest.php']))->toBe([]);
});
