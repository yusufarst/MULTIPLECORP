<?php

use App\Modules\FIN\Data\SettlementEntry;
use App\Modules\FIN\Services\SettlementPosting;
use App\Support\Money;
use Tests\Support\FixtureSets;

// TL-03 (TASK-052; FX-01 6 and 13; BR-FIN-09; BR-FIN-16; L-28; L-45; C-22; C-58): the annex's own figures — net remittance plus fee is the gross, payment plus taxes is the billed value —; the legs a settlement moves, the type its evidence takes and the key of its one ACTIVE row.

function deductionEntry(string $class, ?int $tax, ?string $fee, ?string $direction): SettlementEntry
{
    return new SettlementEntry(1, 2, $class, $tax, $fee, $direction, '100.00', 3, '2026-10-01', false, 'Bukti');
}

it('holds FX-01\'s SIPLAH net settlement: the gross closed by money and one fee, no formula (annex 6; FS-13)', function () {
    $values = FixtureSets::load('FX-01')['entries'][6]['values'];
    expect(Money::of((string) $values['remitted'])->plus(Money::of((string) $values['verified_fee']))->isEqualTo(Money::of((string) $values['gross'])))->toBeTrue();
});

it('holds FX-01\'s treasurer: payment plus taxes collected and withheld is the billed value (annex 13; BR-FIN-16)', function () {
    $values = FixtureSets::load('FX-01')['entries'][13]['values'];
    $settled = Money::of((string) $values['transfer'])->plus(Money::of((string) $values['tax_collected']))->plus(Money::of((string) $values['tax_withheld']));
    expect($settled->isEqualTo(Money::of((string) $values['billed'])))->toBeTrue();
});

it('moves the column of its class, a collected tax also the collected column (DATABASE §11.4; C-22)', function () {
    $amount = Money::of('100');

    expect(SettlementPosting::legs('FEE', null, $amount))->toBe(['fee_settled_amount' => '100.00'])
        ->and(SettlementPosting::legs('TAX', 'WITHHELD', $amount))->toBe(['tax_settled_amount' => '100.00'])
        ->and(SettlementPosting::legs('TAX', 'COLLECTED', $amount->negated()))->toBe(['tax_settled_amount' => '-100.00', 'collected_tax_settled_amount' => '-100.00']);
});

it('takes the evidence type and the role its class and direction name (C-58; L-45)', function () {
    expect(SettlementEntry::evidenceType('FEE', null))->toBe('FEE_ADVICE')->and(SettlementEntry::evidenceType('TAX', 'WITHHELD'))->toBe('TAX_WITHHOLDING_PROOF')->and(SettlementEntry::evidenceType('TAX', 'COLLECTED'))->toBe('TAX_COLLECTION_PROOF')
        ->and(SettlementEntry::roleOf('FEE', null))->toBe('FEE_SETTLEMENT')->and(SettlementEntry::roleOf('TAX', 'WITHHELD'))->toBe('TAX_SETTLEMENT_WITHHELD')->and(SettlementEntry::roleOf('TAX', 'COLLECTED'))->toBe('TAX_SETTLEMENT_COLLECTED');
});

it('keys one ACTIVE settlement by class and specific type, a tax by its direction too (BD-08; L-28)', function () {
    expect(deductionEntry('FEE', null, 'BIAYA_SIPLAH', null)->specificType())->toBe('BIAYA_SIPLAH')
        ->and(deductionEntry('TAX', 7, null, 'WITHHELD')->specificType())->toBe('7:WITHHELD')
        ->and(deductionEntry('TAX', 7, null, 'COLLECTED')->specificType())->not->toBe(deductionEntry('TAX', 7, null, 'WITHHELD')->specificType());
});
