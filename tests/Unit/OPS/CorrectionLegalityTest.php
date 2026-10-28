<?php

use App\Modules\OPS\Data\CorrectionLegality;

// TL-03 (TASK-031; BR-CR-06): the legality matrix every correcting command consults is BUSINESS_RULES's table, cell by
// cell — ✓ allowed, ✗ prohibited, C only with its coupled companion (BR-CR-03), any other text its restricted form.

it('holds BR-CR-06\'s table cell by cell', function () {
    $rules = (string) file_get_contents(base_path('docs/02-domain/BUSINESS_RULES.md'));
    $table = substr($rules, (int) strpos($rules, '| Lifecycle \\ Primitive |'));
    $rows = [];

    foreach (array_slice(explode("\n", $table), 2) as $line) {
        if (! str_starts_with($line, '| ')) {
            break;
        }

        $cells = array_map('trim', explode('|', trim($line, '|')));
        $rows[array_shift($cells)] = $cells;
    }

    $lifecycles = [
        'Quotation' => 'QUOTATION', 'Document (generic)' => 'DOCUMENT', 'Invoice unbilled' => 'INVOICE_UNBILLED', 'Invoice billed' => 'INVOICE_BILLED',
        'Payment / Disbursement / Expense' => 'MONEY_FACT', 'Stock movement' => 'STOCK_MOVEMENT', 'Reservation' => 'RESERVATION', 'Delivery' => 'DELIVERY',
        'Admin requirement' => 'ADMIN_REQUIREMENT', 'Master data / Company' => 'MASTER_DATA',
    ];
    expect(array_keys($rows))->toBe(array_keys($lifecycles));

    foreach ($rows as $label => $cells) {
        foreach (CorrectionLegality::PRIMITIVES as $index => $primitive) {
            $cell = $cells[$index];
            $code = CorrectionLegality::of($lifecycles[$label], $primitive);
            $expected = match (true) {
                $cell === '✓' => CorrectionLegality::ALLOWED,
                str_starts_with($cell, '✗') => CorrectionLegality::PROHIBITED,
                str_starts_with($cell, 'C (') => CorrectionLegality::COMPANION,
                // An allowed cell with its qualifier is the restricted form the qualifier names.
                str_starts_with($cell, '✓ ') => trim(substr($cell, strlen('✓ ')), '()'),
                default => $cell,
            };

            expect($code)->toBe($expected, "{$label} × {$primitive}: {$cell}");
        }
    }
});

it('allows a companion cell only with its companion, and nothing unknown', function () {
    expect(CorrectionLegality::allows('INVOICE_BILLED', 'VOID'))->toBeFalse()
        ->and(CorrectionLegality::allows('INVOICE_BILLED', 'VOID', withCompanion: true))->toBeTrue()
        ->and(CorrectionLegality::allows('STOCK_MOVEMENT', 'REVERSAL'))->toBeTrue()
        ->and(CorrectionLegality::allows('MONEY_FACT', 'EDIT'))->toBeFalse()
        ->and(CorrectionLegality::allows('UNKNOWN', 'EDIT'))->toBeFalse();
});
