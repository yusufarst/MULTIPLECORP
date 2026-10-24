<?php

use App\Modules\IAM\Data\CapabilityCatalogue;

// TL-03 (TASK-011): the capability catalogue of PERMISSIONS_MATRIX §4 and the WORKFLOWS §4 mapping of §5,
// as the resolver and the gates read them (RG-06; SM-04's classes).

it('holds the 80 capabilities of §4 with their counts by class', function () {
    $classes = array_count_values(array_column(CapabilityCatalogue::CAPABILITIES, 0));
    $views = array_count_values(array_map(fn (string $code): string => CapabilityCatalogue::CAPABILITIES[$code][0], CapabilityCatalogue::VIEWS));

    expect(CapabilityCatalogue::codes())->toHaveCount(80)
        ->and($classes)->toBe(['ADM' => 37, 'OWNER_ONLY' => 17, 'ADM_PLUS' => 26])
        ->and($views)->toBe(['ADM' => 7, 'OWNER_ONLY' => 5]);
});

it('names only catalogue capabilities, GRANT or CONTEXTUAL as prerequisites, without a cycle', function () {
    $depth = function (string $code, array $path = []) use (&$depth): int {
        expect($path)->not->toContain($code);

        return 1 + max([0, ...array_map(fn (string $prerequisite): int => $depth($prerequisite, [...$path, $code]), CapabilityCatalogue::prerequisites($code))]);
    };

    foreach (CapabilityCatalogue::CAPABILITIES as $code => [, $prerequisites]) {
        foreach ($prerequisites as $prerequisite) {
            expect(CapabilityCatalogue::has($prerequisite) || in_array($prerequisite, [CapabilityCatalogue::GRANT, CapabilityCatalogue::CONTEXTUAL], true))->toBeTrue();
        }

        expect($depth($code))->toBeLessThan(5);
    }
});

it('maps each WORKFLOWS §4 row to capabilities of its class (§5)', function () {
    $classes = ['OWN!' => ['OWNER_ONLY'], 'ADM' => ['ADM'], 'ADM+' => ['ADM_PLUS'], 'ADM / OWN' => ['ADM', 'OWNER_ONLY'], 'BG' => ['ADM'], 'SYS' => []];
    $mapped = [];

    expect(CapabilityCatalogue::WORKFLOW_ROWS)->toHaveCount(27);

    foreach (CapabilityCatalogue::WORKFLOW_ROWS as $row => [$class, $capabilities]) {
        foreach ($capabilities as $capability) {
            expect($classes[$class])->toContain(CapabilityCatalogue::classOf($capability)->value);
            $mapped[$capability] = true;
        }
    }

    // Every command capability has its row; the views and the scope are read classes, outside WORKFLOWS §4.
    $commands = array_diff(CapabilityCatalogue::codes(), CapabilityCatalogue::VIEWS);

    expect(array_values(array_diff($commands, array_keys($mapped))))->toBe([])
        ->and(CapabilityCatalogue::WORKFLOW_ROWS['W4-25'][1])->toBe(['import.prepare', 'import.commit'])
        ->and(CapabilityCatalogue::WORKFLOW_ROWS['W4-26'][1])->toBe([]);
});

it('needs a company in scope for a command capability and a GRANT prerequisite, not for the pooled view', function () {
    expect(CapabilityCatalogue::needsCompany('products.edit'))->toBeTrue()
        ->and(CapabilityCatalogue::needsCompany('finance.view'))->toBeTrue()
        ->and(CapabilityCatalogue::needsCompany('stock.view'))->toBeFalse()
        ->and(CapabilityCatalogue::needsCompany('audit.view'))->toBeFalse();
});

it('refuses a code outside the catalogue', function () {
    CapabilityCatalogue::classOf('stock.teleport');
})->throws(InvalidArgumentException::class);
