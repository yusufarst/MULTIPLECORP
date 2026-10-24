<?php

namespace Tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The prohibited patterns of ARCHITECTURE §19, SECURITY WS-01 and PERFORMANCE §3 that a dependency
 * rule cannot see, found by scanning the PHP source.
 */
final class SourceScan
{
    /**
     * Each rule: its source, the patterns it refuses and the folders it applies to or skips.
     *
     * @var array<string, array{source: string, patterns: list<string>, only?: list<string>, skip?: list<string>}>
     */
    public const RULES = [
        'whole-request-mass-assignment' => [
            'source' => 'SECURITY WS-01; ARCHITECTURE §19',
            'patterns' => ['/\$request->(all|except)\(/', '/request\(\)->(all|except)\(/', '/Request::(all|except)\(/'],
        ],
        'unguarded-model' => [
            'source' => 'SECURITY WS-01; SM-03',
            'patterns' => ['/::unguard(ed)?\(/', '/\$guarded\s*=\s*\[\s*\]/'],
        ],
        'float-money-or-quantity' => [
            'source' => 'ARCHITECTURE §6, §19; EXECUTION_CONTEXT invariant 1',
            'patterns' => ['/\(float\)|\(double\)/', '/\bfloatval\(/', '/=>\s*[\'"](float|double|real)[\'"]/', '/\bfloat\s+\$/', '/:\s*\??float\b/'],
        ],
        'max-number-plus-one' => [
            'source' => 'ARCHITECTURE §19',
            'patterns' => ['/max\s*\([^)]*\)\s*\+\s*1\b/i'],
        ],
        'morph-link' => [
            'source' => 'ARCHITECTURE §19',
            'patterns' => ['/->morph(To|One|Many|ToMany|edByMany)\(/', '/->(nullable)?(uuid|ulid)?morphs\(/i'],
        ],
        'delete-flag' => [
            'source' => 'ARCHITECTURE §19; EXECUTION_CONTEXT invariant 4',
            'patterns' => ['/->softDeletes(Tz)?\(/', '/\bSoftDeletes\b/'],
        ],
        'model-event' => [
            'source' => 'ARCHITECTURE §2, §19',
            'patterns' => [
                '/static::(creating|created|updating|updated|saving|saved|deleting|deleted|restoring|restored|replicating|retrieved)\(/',
                '/\$dispatchesEvents/',
                '/#\[ObservedBy/',
                '/::observe\(/',
            ],
            'only' => ['/Models/'],
        ],
        'role-name-check' => [
            'source' => 'SECURITY AZ-02; SM-03',
            'patterns' => ['/[\'"](OWNER|ADMIN_OPERASIONAL)[\'"]/', '/->hasRole\(/', '/\bBuiltinRole::/'],
        ],
        'scope-bypass' => [
            'source' => 'SECURITY EN-04; SM-03',
            'patterns' => ['/->withoutGlobalScopes?\(/', '/::withoutGlobalScopes?\(/'],
        ],
        'balance-table-write' => [
            'source' => 'ARCHITECTURE §7, §19: every guard update goes through the shared GuardUpdate helper',
            'patterns' => ['/DB::table\(\s*[\'"]\w+_balances[\'"]/'],
            'skip' => ['/Queries/', '/Support/GuardUpdate'],
        ],
        'balance-model-outside-posting' => [
            'source' => 'ARCHITECTURE §7, §19: a balance belongs to its posting service and is read by queries',
            'patterns' => ['/\\\\Models\\\\\w+Balance\b/'],
            'skip' => ['/Services/', '/Queries/', '/Models/'],
        ],
        'automatic-eager-loading' => [
            'source' => 'PERFORMANCE PF-03',
            'patterns' => ['/automaticallyEagerLoadRelationships\(/', '/withRelationshipAutoloading\(/'],
        ],
        'auth-provider-outside-boundary' => [
            'source' => 'ARCHITECTURE §8, §19',
            'patterns' => ['/\bLaravel\\\\(Fortify|Socialite)\b/'],
            'skip' => ['/Modules/IAM/Authentication/'],
        ],
    ];

    /**
     * The places AZ-02 allows a role name's spelling, added by the Tasks that build them.
     *
     * @var list<string>
     */
    public const ROLE_NAME_ALLOWED = [
        // TASK-005: OWNER is also an unlink reason of C-63 — the CHECK lists the code, no role is checked.
        '/database/migrations/2026_10_20_000008_iam_create_user_external_identities_table.php',
        // TASK-011: the built-in roles' enumeration, the effective-capability resolver (RG-02) and the
        // catalogue's rows (RG-01) — the resolver is the one reader AZ-02 names for authorization.
        '/app/Modules/IAM/Enums/BuiltinRole.php',
        '/app/Modules/IAM/Services/EffectiveCapabilities.php',
        '/app/Modules/IAM/Services/CatalogueSync.php',
    ];

    /**
     * The violations of a rule in the given folders or files, as "path:line: text".
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    public static function violations(string $rule, array $paths): array
    {
        $definition = self::RULES[$rule];
        $violations = [];

        foreach (self::files($paths) as $file) {
            $normalized = str_replace('\\', '/', $file);

            if (isset($definition['only']) && ! self::contains($normalized, $definition['only'])) {
                continue;
            }

            if (isset($definition['skip']) && self::contains($normalized, $definition['skip'])) {
                continue;
            }

            if ($rule === 'role-name-check' && self::contains($normalized, self::ROLE_NAME_ALLOWED)) {
                continue;
            }

            foreach (file($file) ?: [] as $number => $line) {
                foreach ($definition['patterns'] as $pattern) {
                    if (preg_match($pattern, $line) === 1) {
                        $violations[] = sprintf('%s:%d: %s', $normalized, $number + 1, trim($line));
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private static function files(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;

                continue;
            }

            if (! is_dir($path)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    /** @param  list<string>  $needles */
    private static function contains(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
