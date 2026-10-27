<?php

use App\Modules\IAM\Data\AccessPreview;
use App\Modules\IAM\Data\CapabilityAreas;
use App\Modules\IAM\Data\CapabilityCatalogue;

// TL-03 (TASK-024; PT-30; D-DS-09; UXS-27; RG-06; RG-07; GAP-032): the preview's derivation — every capability the
// account holds or will hold against every company it holds or will hold, *Dapat*, *Baru* or *Dicabut*, a capability
// applying to every granted company; the dependants a removed prerequisite disables, their grants kept; a capability
// added without its prerequisite found for the refusal; the OWNER_ONLY capabilities never in it.

it('marks each cell kept, added or removed across every granted company (RG-07; GAP-032)', function () {
    $preview = new AccessPreview(['projects.view'], ['A'], ['projects.view', 'stock.view'], ['A', 'B']);
    $rows = collect($preview->rows())->keyBy('code');

    expect($preview->companies())->toBe(['A', 'B'])
        ->and($rows['projects.view']['marks'])->toBe(['A' => 'kept', 'B' => 'added'])
        ->and($rows['stock.view']['marks'])->toBe(['A' => 'added', 'B' => 'added'])
        ->and($preview->summaries())->toBe(['A' => 2, 'B' => 2]);
});

it('marks a removed company\'s and a removed capability\'s cells removed, the company kept as a column', function () {
    $preview = new AccessPreview(['projects.view', 'stock.view'], ['A', 'B'], ['projects.view'], ['A']);
    $rows = collect($preview->rows())->keyBy('code');

    expect($preview->companies())->toBe(['A', 'B'])
        ->and($rows['projects.view']['marks'])->toBe(['A' => 'kept', 'B' => 'removed'])
        ->and($rows['stock.view']['marks'])->toBe(['A' => 'removed', 'B' => 'removed'])
        ->and($preview->summaries())->toBe(['A' => 1, 'B' => 0])
        ->and($preview->changes())->toMatchArray(['capabilities_removed' => ['stock.view'], 'companies_removed' => ['B']]);
});

it('disables the dependants of a removed prerequisite, their grants kept (RG-06; "Nonaktif — prasyarat dicabut")', function () {
    $held = ['finance.view', 'cost.view', 'profit.view'];
    $preview = new AccessPreview($held, ['A'], ['cost.view', 'profit.view'], ['A']);
    $rows = collect($preview->rows())->keyBy('code');

    expect($preview->disabled())->toBe(['profit.view'])
        ->and($rows['profit.view']['marks'])->toBe(['A' => 'inactive'])
        ->and($rows['finance.view']['marks'])->toBe(['A' => 'removed'])
        ->and($preview->addedWithoutPrerequisite())->toBe([])
        ->and(AccessPreview::effective(['cost.view', 'profit.view']))->toBe(['cost.view']);
});

it('finds a capability added without its prerequisite, which the grant refuses (RG-06)', function () {
    $preview = new AccessPreview([], ['A'], ['profit.view', 'finance.view'], ['A']);

    expect($preview->addedWithoutPrerequisite())->toBe(['profit.view'])
        ->and((new AccessPreview([], ['A'], ['stock.allocate_intercompany', 'stock.view'], ['A']))->addedWithoutPrerequisite())->toBe([]);
});

it('never offers an OWNER_ONLY capability and groups the rest by area, ADM_PLUS under the special area (RG-02; RG-08; OD-02)', function () {
    $grantable = array_merge(...array_values(CapabilityAreas::grantable()));

    expect(array_filter($grantable, fn (string $code): bool => CapabilityCatalogue::isOwnerOnly($code)))->toBe([])
        ->and(count($grantable))->toBe(count(CapabilityCatalogue::codes()) - 17)
        ->and(CapabilityAreas::of('stock.allocate_intercompany'))->toBe('special')
        ->and(CapabilityAreas::of('stock.view'))->toBe('view')
        ->and(CapabilityAreas::of('audit.view'))->toBeNull()
        ->and(collect((new AccessPreview([], ['A'], ['audit.view', 'stock.view'], ['A']))->rows())->pluck('code')->all())->toBe(['stock.view']);
});
