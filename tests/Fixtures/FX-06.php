<?php

// FX-06 (TEST_STRATEGY §4): the scale fixtures of PERFORMANCE AS-04 and AS-05 — AS-04's volume at 1x,
// as written, multiplied for every table at 10x and 100x. The Task that measures a budget generates the
// rows of its tables at each scale and analyzes them (TL-15; TASK-068).
return [
    'id' => 'FX-06',
    'title' => 'The scale fixtures 1x, 10x and 100x of AS-04 and AS-05, generated and analyzed',
    'source' => 'PERFORMANCE §1',
    'used_by' => 'TL-15',
    'documents' => ['docs/06-api-performance/PERFORMANCE.md'],
    'scales' => [1, 10, 100],
    'entries' => [
        'products' => 5_000,
        'client organizations' => 500,
        'client units' => 2_000,
        'suppliers' => 300,
        'projects' => 2_000,
        'project lines' => 20_000,
        'purchases' => 3_000,
        'purchase lines' => 15_000,
        'stock movements' => 40_000,
        'ledger entries' => 120_000,
        'lots' => 15_000,
        'invoices' => 3_000,
        'payments' => 4_000,
        'document versions' => 15_000,
        'evidence files' => 20_000,
        'audit events' => 300_000,
        'security events' => 100_000,
        'import rows' => 30_000,
    ],
];
