<?php

use App\Modules\FIN\Data\ContraEntry;
use App\Modules\FIN\Data\PaymentEntry;
use App\Modules\FIN\Data\SourcePosition;
use App\Support\Money;
use Tests\Support\FixtureSets;

// TL-03 (TASK-051; FX-01 1–5; FX-04; BR-FIN-03; BR-FIN-05; CALC-06; L-46; DATABASE §11.3): capacity, outstanding and credit against the annex's own figures; the rules a source's position would break, the Rp1 attack among them; the typed exits of a refund-backed application.

function sourcePosition(string $gross, string $contra = '0', string $applied = '0', string $refund = '0', string $shortfall = '0', string $settled = '0'): SourcePosition
{
    return new SourcePosition(Money::of($gross), Money::of($contra), Money::of($applied), Money::of($refund), Money::of($shortfall), Money::of($settled));
}

it('holds FX-01\'s baseline: two payments leave Rp700,000 then nothing outstanding (annex 1)', function () {
    $values = FixtureSets::load('FX-01')['entries'][1]['values'];
    $outstanding = Money::of($values['invoice'])->minus(Money::of($values['payment_1']));
    expect($outstanding->isEqualTo(Money::of($values['outstanding_after_payment_1'])))->toBeTrue()
        ->and($outstanding->minus(Money::of($values['payment_2']))->isEqualTo(Money::of($values['outstanding_after_payment_2'])))->toBeTrue();
});

it('counts an advance once: Cash-In once, billing activating the rest (annex 2)', function () {
    $values = FixtureSets::load('FX-01')['entries'][2]['values'];
    $position = sourcePosition((string) $values['advance_payment'], applied: (string) $values['advance_payment']);
    expect($position->capacity()->isZero())->toBeTrue()->and($position->net()->isEqualTo(Money::of($values['cash_in'])))->toBeTrue();
});

it('leaves an overpayment\'s rest as credit, spendable once (annex 3; BR-FIN-05)', function () {
    $values = FixtureSets::load('FX-01')['entries'][3]['values'];
    $position = sourcePosition((string) $values['payment'], applied: (string) $values['application']);
    expect($position->capacity()->isEqualTo(Money::of($values['unapplied_credit'])))->toBeTrue();

    $refunded = sourcePosition((string) $values['payment'], applied: (string) $values['application'], refund: (string) $values['refund_application']);
    expect($refunded->capacity()->isZero())->toBeTrue()->and($refunded->breach())->toBeNull();

    $twice = sourcePosition((string) $values['payment'], applied: (string) ($values['application'] + $values['unapplied_credit']), refund: (string) $values['refund_application']);
    expect($twice->breach()['rule'] ?? null)->toBe('C-13');
});

it('moves an application without changing the payment\'s total (annex 4)', function () {
    $values = FixtureSets::load('FX-01')['entries'][4]['values'];
    $before = sourcePosition('1000000', applied: (string) $values['moved_application']);
    expect($before->net()->isEqualTo(Money::of('1000000')))->toBeTrue()->and($before->capacity()->isEqualTo(Money::of('600000')))->toBeTrue();
});

it('releases to credit what a void disposes, nothing orphaned (annex 5)', function () {
    $held = sourcePosition('400000', applied: '400000');
    $released = sourcePosition('400000');
    expect($held->capacity()->isZero())->toBeTrue()->and($released->capacity()->isEqualTo($held->net()))->toBeTrue();
});

it('refuses every way of freeing capacity with the Rp1 contra, and admits the only one that keeps it at nothing (FX-04; C-44; DATABASE §11.3)', function () {
    expect(FixtureSets::load('FX-04')['entries'][1]['fixture'])->toBe('the Rp1 refund-shortfall attack');

    // End R: P's refund backed 0, shortfall 200,000, capacity would be 199,999.
    expect(sourcePosition('1200000', contra: '1', applied: '1000000', shortfall: '200000')->breach()['rule'] ?? null)->toBe('C-44')
        // R superseded by 199,999 from P with a Rp1 shortfall.
        ->and(sourcePosition('1200000', contra: '1', applied: '1000000', refund: '199999', shortfall: '1')->breach()['rule'] ?? null)->toBe('C-44')
        // Nothing disposed.
        ->and(sourcePosition('1200000', contra: '1', applied: '1000000', refund: '200000')->breach()['rule'] ?? null)->toBe('C-13');

    $committable = sourcePosition('1200000', contra: '1', applied: '999999', refund: '200000');
    expect($committable->breach())->toBeNull()->and($committable->capacity()->isZero())->toBeTrue();
});

it('admits a legitimate shortfall only with no invoice application and no free capacity (L-46; §11.3\'s contrast)', function () {
    $real = sourcePosition('1200000', contra: '1100000', refund: '100000', shortfall: '100000');
    expect($real->breach())->toBeNull()->and($real->net()->isEqualTo(Money::of('100000')))->toBeTrue()->and($real->capacity()->isZero())->toBeTrue();
});

it('keeps no settlement on a fully contra\'d payment (C-45)', function () {
    expect(sourcePosition('100000', contra: '100000', settled: '5000')->breach()['rule'] ?? null)->toBe('C-45')
        ->and(sourcePosition('100000', contra: '99999', settled: '5000')->breach())->toBeNull();
});

it('names a refund-backed application\'s typed exits and the dispositions, nothing else (L-46; ST-07; L-29)', function () {
    expect(SourcePosition::REFUND_EXITS)->toBe(['RE_RECORDED', 'SOURCE_CONTRA', 'REFUND_CONTRA'])
        ->and([ContraEntry::KEEP, ContraEntry::RELEASE, ContraEntry::MOVE, ContraEntry::SHORTFALL, ContraEntry::RESIDUAL])->toBe(['KEEP', 'RELEASE', 'MOVE', 'SHORTFALL', 'RESIDUAL'])
        ->and(PaymentEntry::PROOF)->toBe(['PAYMENT_PROOF', 'TRANSFER_PROOF', 'BANK_STATEMENT'])
        ->and(PaymentEntry::METHODS)->toBe(['TRANSFER', 'CASH', 'VIRTUAL_ACCOUNT', 'OTHER']);
});
