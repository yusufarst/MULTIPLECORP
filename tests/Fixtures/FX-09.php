<?php

// FX-09 (TEST_STRATEGY §4): opening-import batches of invented legacy data, never assumed clean (brief
// §45; DATABASE §24; AC-17) — clean rows, malformed rows each with its defect, a duplicate, a row of a
// company outside the batch's scope and legacy numbers in formats of their own. Money and quantities
// are exact decimal strings. The opening-import Task maps these rows to its batch format (TL-16; CJ-16).
return [
    'id' => 'FX-09',
    'title' => 'Opening-import batches of synthetic legacy data — clean rows, malformed rows, duplicates, rows of an out-of-scope company and legacy numbers, because historical data is never assumed clean',
    'source' => 'brief §45; DATABASE §24; AC-17',
    'used_by' => 'TL-16; CJ-16',
    'documents' => ['docs/04-architecture/DATABASE/s20-24-index-storage-migration.md'],
    'batch' => ['scope' => ['ARJ'], 'cutoff' => '2026-01-31'],
    'entries' => [
        'clean' => [
            ['kind' => 'lot', 'company' => 'ARJ', 'legacy_number' => 'STK/ARJ/2025/0101', 'product' => 'Kertas HVS A4 Contoh', 'location' => 'Gudang Contoh', 'quantity' => '120.000', 'unit_cost' => '45000.00'],
            ['kind' => 'lot', 'company' => 'ARJ', 'legacy_number' => 'STK/ARJ/2025/0102', 'product' => 'Proyektor Contoh', 'location' => 'Gudang Contoh', 'quantity' => '3.000', 'unit_cost' => '5500000.00'],
            ['kind' => 'receivable', 'company' => 'ARJ', 'legacy_number' => 'INV/ARJ/2025/0123', 'client' => 'SMAN 1 Kota Contoh', 'billed_at' => '2025-12-01', 'due_date' => '2025-12-31', 'remaining' => '1000000.00'],
            ['kind' => 'customer_credit', 'company' => 'ARJ', 'legacy_number' => 'KRD/ARJ/2025/0007', 'client' => 'SD 1 Contoh Makmur', 'amount' => '200000.00'],
        ],
        'malformed' => [
            ['defect' => 'negative quantity', 'row' => ['kind' => 'lot', 'company' => 'ARJ', 'legacy_number' => 'STK/ARJ/2025/0103', 'product' => 'Kertas HVS F4 Contoh', 'location' => 'Gudang Contoh', 'quantity' => '-5.000', 'unit_cost' => '47000.00']],
            ['defect' => 'impossible date', 'row' => ['kind' => 'receivable', 'company' => 'ARJ', 'legacy_number' => 'INV/ARJ/2025/0124', 'client' => 'SMAN 10 Kota Contoh', 'billed_at' => '2025-02-30', 'due_date' => '2025-03-30', 'remaining' => '750000.00']],
            ['defect' => 'amount in words', 'row' => ['kind' => 'receivable', 'company' => 'ARJ', 'legacy_number' => 'INV/ARJ/2025/0125', 'client' => 'SMKN 1 Contoh Utama', 'billed_at' => '2025-11-03', 'due_date' => '2025-12-03', 'remaining' => 'satu juta']],
            ['defect' => 'money with three decimals', 'row' => ['kind' => 'lot', 'company' => 'ARJ', 'legacy_number' => 'STK/ARJ/2025/0104', 'product' => 'Spidol Contoh', 'location' => 'Gudang Contoh', 'quantity' => '40.000', 'unit_cost' => '8500.125']],
            ['defect' => 'missing client', 'row' => ['kind' => 'customer_credit', 'company' => 'ARJ', 'legacy_number' => 'KRD/ARJ/2025/0008', 'amount' => '50000.00']],
        ],
        'duplicates' => [
            ['kind' => 'receivable', 'company' => 'ARJ', 'legacy_number' => 'INV/ARJ/2025/0123', 'client' => 'SMAN 1 Kota Contoh', 'billed_at' => '2025-12-01', 'due_date' => '2025-12-31', 'remaining' => '1000000.00'],
        ],
        'out_of_scope' => [
            ['kind' => 'receivable', 'company' => 'BTN', 'legacy_number' => 'INV/BTN/2025/0031', 'client' => 'SD 10 Contoh Abadi', 'billed_at' => '2025-10-15', 'due_date' => '2025-11-14', 'remaining' => '300000.00'],
        ],
        'legacy_numbers' => [
            ['kind' => 'receivable', 'company' => 'ARJ', 'legacy_number' => '77/INV-ARJ/XII/2025', 'client' => 'Fakultas Teknik UGM Contoh', 'billed_at' => '2025-12-10', 'due_date' => null, 'remaining' => '2500000.00'],
            ['kind' => 'purchase', 'company' => 'ARJ', 'legacy_number' => 'PO-0042', 'supplier' => 'CV Contoh Pemasok Lestari', 'ordered_at' => '2026-01-20', 'quantity' => '10.000', 'unit_price' => '125000.00'],
        ],
    ],
];
