<?php

namespace Tests\Architecture;

/**
 * The dependency rules of ARCHITECTURE §2–§4 and §19 as Pest architecture expectations, built for a
 * root namespace: `App` for the application, a fixture root to prove that each rule fails.
 */
final class Rules
{
    /** The module tiers of ARCHITECTURE §3. */
    public const TIERS = [
        'IAM' => 0, 'OPS' => 0,
        'ORG' => 1, 'PTY' => 1, 'CAT' => 1, 'DOC' => 1,
        'PRJ' => 2,
        'CST' => 3,
        'PUR' => 4, 'ADM' => 4,
        'INV' => 5,
        'FUL' => 6, 'FIN' => 6,
        'Reporting' => 7, 'ImportOrchestration' => 7,
    ];

    /** The only dependencies within a tier (ARCHITECTURE §3). */
    public const SAME_TIER = ['OPS' => ['IAM'], 'DOC' => ['ORG']];

    /** HTTP, session and rendering concerns (ARCHITECTURE §2). */
    public const HTTP = [
        'Illuminate\Cookie',
        'Illuminate\Http',
        'Illuminate\Routing',
        'Illuminate\Session',
        'Illuminate\View',
        'Inertia',
        'Symfony\Component\HttpFoundation',
    ];

    /** @return list<string> */
    public static function modules(): array
    {
        return array_keys(self::TIERS);
    }

    /** @return list<string> */
    public static function layer(string $root, string $folder): array
    {
        return array_map(fn (string $module): string => "{$root}\\Modules\\{$module}\\{$folder}", self::modules());
    }

    /** @return list<string> */
    public static function presentation(string $root): array
    {
        return ["{$root}\\Http", ...self::layer($root, 'Http')];
    }

    /**
     * Dependencies point downward only: presentation → application → domain (ARCHITECTURE §2).
     *
     * @return list<array{string, list<string>}>
     */
    public static function layers(string $root): array
    {
        $application = [...self::layer($root, 'Actions'), ...self::layer($root, 'Queries'), ...self::layer($root, 'Jobs')];
        $rules = [];

        foreach (self::modules() as $module) {
            foreach (['Models', 'Enums', 'Data', 'Services'] as $domain) {
                $rules[] = ["{$root}\\Modules\\{$module}\\{$domain}", [...self::HTTP, ...self::presentation($root), ...$application]];
            }

            foreach (['Actions', 'Queries', 'Jobs', 'Policies'] as $applicationFolder) {
                $rules[] = ["{$root}\\Modules\\{$module}\\{$applicationFolder}", [...self::HTTP, ...self::presentation($root)]];
            }
        }

        $rules[] = ["{$root}\\Support", [...self::HTTP, ...self::presentation($root), ...$application]];

        return $rules;
    }

    /**
     * A module writes only through the actions and posting services of a lower tier, never in a cycle,
     * and never through another module's models; Reporting only reads (ARCHITECTURE §3, §19).
     *
     * @return list<array{string, list<string>}>
     */
    public static function tiers(string $root): array
    {
        $rules = [];

        foreach (self::modules() as $module) {
            $forbidden = [];

            foreach (self::modules() as $other) {
                if ($other === $module) {
                    continue;
                }

                if ($module === 'Reporting' || ! self::mayWrite($module, $other)) {
                    $forbidden[] = "{$root}\\Modules\\{$other}\\Actions";
                    $forbidden[] = "{$root}\\Modules\\{$other}\\Services";
                }
            }

            $rules[] = ["{$root}\\Modules\\{$module}", $forbidden];

            $otherModels = array_values(array_filter(
                self::layer($root, 'Models'),
                fn (string $namespace): bool => $namespace !== "{$root}\\Modules\\{$module}\\Models",
            ));

            foreach (['Actions', 'Services'] as $writer) {
                $rules[] = ["{$root}\\Modules\\{$module}\\{$writer}", $otherModels];
            }
        }

        return $rules;
    }

    /**
     * Controllers stay thin: no database work, model or posting service in them (ARCHITECTURE §2; SM-03).
     *
     * @return list<array{string, list<string>}>
     */
    public static function controllers(string $root): array
    {
        $forbidden = [
            'Illuminate\Database',
            'Illuminate\Support\Facades\DB',
            ...self::layer($root, 'Models'),
            ...self::layer($root, 'Services'),
        ];

        return array_map(
            fn (string $namespace): array => ["{$namespace}\\Controllers", $forbidden],
            self::presentation($root),
        );
    }

    public static function mayWrite(string $module, string $other): bool
    {
        return self::TIERS[$other] < self::TIERS[$module]
            || in_array($other, self::SAME_TIER[$module] ?? [], true);
    }

    /**
     * Runs the rules, or only those of the given target; a failing rule throws.
     *
     * @param  list<array{string, list<string>}>  $rules
     */
    public static function check(array $rules, ?string $only = null): void
    {
        foreach ($rules as [$target, $forbidden]) {
            if ($forbidden !== [] && ($only === null || $target === $only)) {
                expect($target)->not->toUse($forbidden);
            }
        }
    }

    /**
     * A catch-all `Services` folder outside the modules (ARCHITECTURE §19).
     *
     * @return list<string>
     */
    public static function catchAllServiceFolders(string $path): array
    {
        return is_dir("{$path}/Services") ? ["{$path}/Services"] : [];
    }
}
