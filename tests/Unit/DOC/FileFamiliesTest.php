<?php

use App\Modules\DOC\Data\FileFamilies;

// TL-03 (TASK-022; PERMISSIONS_MATRIX PJ-15, PJ-08): every document type's rendition opens to one family's view
// capability, and every evidence type's file to its family's — TAX_INVOICE to finance or cost —, OPENING_SIGNOFF to
// the import rule alone, an unknown type to nobody.

it('names one family for each of the fourteen document types (PJ-15)', function () {
    $families = [];

    foreach (range(1, 14) as $number) {
        $families[sprintf('DOC-%02d', $number)] = FileFamilies::document(sprintf('DOC-%02d', $number));
    }

    expect($families)->toBe([
        'DOC-01' => 'finance.view', 'DOC-02' => 'cost.view', 'DOC-03' => 'projects.view', 'DOC-04' => 'finance.view',
        'DOC-05' => 'finance.view', 'DOC-06' => 'finance.view', 'DOC-07' => 'finance.view', 'DOC-08' => 'projects.view',
        'DOC-09' => 'finance.view', 'DOC-10' => 'projects.view', 'DOC-11' => 'finance.view', 'DOC-12' => 'projects.view',
        'DOC-13' => 'finance.view', 'DOC-14' => 'cost.view',
    ])->and(FileFamilies::document('DOC-15'))->toBeNull();
});

it('names each evidence type\'s families (PJ-08)', function () {
    expect(FileFamilies::evidence('BANK_STATEMENT'))->toBe(['finance.view'])
        ->and(FileFamilies::evidence('SUPPLIER_BILL'))->toBe(['cost.view'])
        ->and(FileFamilies::evidence('TAX_INVOICE'))->toBe(['finance.view', 'cost.view'])
        ->and(FileFamilies::evidence('CLIENT_SPK'))->toBe(['projects.view'])
        ->and(FileFamilies::evidence('WAREHOUSE_RECORD'))->toBe(['stock.view'])
        ->and(FileFamilies::evidence('OPENING_SIGNOFF'))->toBe([])
        ->and(FileFamilies::evidence('TIDAK_ADA'))->toBe([]);
});
