<?php

use App\Modules\CST\Queries\AllocationOverlay;
use Tests\Architecture\Fixtures\App\Modules\CST\Queries\UnscopedFigures;
use Tests\Architecture\ScopeFirst;

// SECURITY_TEST_MATRIX SM-03 and SM-25 (TASK-036; AZ-04; CS-07; PJ-22): every viewer read of CST's query classes resolves
// its viewer, holds the cost family and applies the company scope inside its query before the query runs; C-28's
// overlay check is the one class read by no viewer. The rule is detected on its fixture.

it('resolves the viewer, holds cost.view and applies the scope first in every read of CST\'s query classes (SM-03; SM-25)', function () {
    $classes = ScopeFirst::classes(app_path('Modules/CST/Queries'), 'App\Modules\CST\Queries');

    expect(ScopeFirst::violations($classes, 'cost.view'))->toBe([])
        ->and(ScopeFirst::viewerless($classes))->toBe([AllocationOverlay::class]);
});

it('detects a read without its scope, without its family, running before them, or with no viewer, on its fixture', function () {
    $fixture = UnscopedFigures::class;

    expect(ScopeFirst::violations([$fixture], 'cost.view'))->toBe([
        "{$fixture}::unscoped: applies no company scope before its query runs",
        "{$fixture}::unheld: holds no cost.view before its query runs",
        "{$fixture}::late: holds no cost.view before its query runs",
        "{$fixture}::late: applies no company scope before its query runs",
        "{$fixture}::anonymous: resolves no viewer",
    ]);
});
