<?php

use App\Modules\ORG\Data\NumberFormat;
use App\Modules\ORG\Data\NumberingPeriod;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\NumberingScheme;
use App\Modules\ORG\Services\NumberingState;

// TL-03 (TASK-021; NM-03; DIR-098 D1; GAP-016): the scheme and period rules — the format's tokens and their refusals,
// a period from a WIB business day under each granularity, the periods a scheme governs, which periods are live
// against the seed boundary and go-live, and the sequence types' address, audit and seed codes.

function schemeFrom(string $from, string $granularity): NumberingScheme
{
    $scheme = new NumberingScheme;
    $scheme->forceFill(['effective_from' => $from, 'period_granularity' => $granularity]);

    return $scheme;
}

it('formats a number with the decided tokens, padding the number and writing the month in Roman (DIR-098 D1)', function () {
    expect(NumberFormat::format('ARJ/INV/{tahun}/{nomor:4}', '2026', 141))->toBe('ARJ/INV/2026/0141')
        ->and(NumberFormat::format('{nomor:3}/SJ/{bulan_romawi}/{tahun}', '2026-10', 7))->toBe('007/SJ/X/2026')
        ->and(NumberFormat::format('PB-{tahun2}{bulan}-{nomor}', '2026-03', 12345))->toBe('PB-2603-12345')
        ->and(NumberFormat::format('{nomor:2}', '2026', 123))->toBe('123');
});

it('refuses a template without exactly one number token, an unknown token, a stray brace or a month in a yearly format', function () {
    expect(NumberFormat::problem('ARJ/{tahun}/{nomor:4}', 'YEAR'))->toBeNull()
        ->and(NumberFormat::problem('{nomor}/{bulan_romawi}/{tahun2}', 'MONTH'))->toBeNull()
        ->and(NumberFormat::problem('ARJ/{tahun}', 'YEAR'))->toBe('numbering.format.number')
        ->and(NumberFormat::problem('{nomor}/{nomor}/{tahun}', 'YEAR'))->toBe('numbering.format.number')
        ->and(NumberFormat::problem('{nomor:0}/{tahun}', 'YEAR'))->toBe('numbering.format.token')
        ->and(NumberFormat::problem('{nomor}/{hari}', 'YEAR'))->toBe('numbering.format.token')
        ->and(NumberFormat::problem('{nomor}/}', 'YEAR'))->toBe('numbering.format.brace')
        ->and(NumberFormat::problem('{nomor}/{bulan}', 'YEAR'))->toBe('numbering.format.month_in_year')
        ->and(NumberFormat::problem('', 'YEAR'))->toBe('numbering.format.length')
        ->and(NumberFormat::problem(str_repeat('a', 57).'{nomor}', 'YEAR'))->toBe('numbering.format.length');
});

it('refuses a template without its period\'s tokens, which would repeat a number across periods (DIR-098 D1a; C-10; NM-09)', function () {
    expect(NumberFormat::problem('SJ-{nomor:4}', 'YEAR'))->toBe('numbering.format.period')
        ->and(NumberFormat::problem('SJ/{tahun}/{nomor}', 'MONTH'))->toBe('numbering.format.period')
        ->and(NumberFormat::problem('SJ/{bulan}/{nomor}', 'MONTH'))->toBe('numbering.format.period')
        ->and(NumberFormat::problem('SJ/{tahun2}{bulan}/{nomor}', 'MONTH'))->toBeNull()
        ->and(NumberFormat::problem('SJ/{tahun2}/{nomor}', 'YEAR'))->toBeNull();
});

it('takes a period from the WIB business day, a YEAR key and a MONTH key never coinciding (NM-03; BR-DT-06; GAP-016)', function () {
    expect(NumberingPeriod::of('2026-12-31', 'YEAR')->key)->toBe('2026')
        ->and(NumberingPeriod::of('2027-01-01', 'MONTH')->key)->toBe('2027-01')
        ->and(NumberingPeriod::fromKey('2026-02')->lastDay())->toBe('2026-02-28')
        ->and(NumberingPeriod::fromKey('2024-02')->lastDay())->toBe('2024-02-29')
        ->and(NumberingPeriod::fromKey('2026')->firstDay())->toBe('2026-01-01')
        ->and(NumberingPeriod::isKey('2026-13'))->toBeFalse()
        ->and(fn () => NumberingPeriod::of('2026-02-30', 'YEAR'))->toThrow(InvalidArgumentException::class);
});

it('reads a period as governed when a scheme of its granularity is in force on some date of it (NM-03)', function () {
    $schemes = [schemeFrom('2026-01-01', 'YEAR'), schemeFrom('2026-07-01', 'MONTH')];

    expect(NumberingState::governs($schemes, NumberingPeriod::fromKey('2026')))->toBeTrue()
        ->and(NumberingState::governs($schemes, NumberingPeriod::fromKey('2026-06')))->toBeFalse()
        ->and(NumberingState::governs($schemes, NumberingPeriod::fromKey('2026-07')))->toBeTrue()
        ->and(NumberingState::governs($schemes, NumberingPeriod::fromKey('2027')))->toBeFalse()
        ->and(array_map(fn (NumberingPeriod $period): string => $period->key, NumberingState::periodsOf($schemes, '2026-07-15')))->toBe(['2026', '2026-07'])
        ->and(array_map(fn (NumberingPeriod $period): string => $period->key, NumberingState::periodsOf($schemes, '2026-03-02')))->toBe(['2026']);
});

it('reads a period as live only when it begins after the boundary and does not lie wholly before go-live (NM-03)', function () {
    expect(NumberingState::isLive(NumberingPeriod::fromKey('2026-11'), '2026-10-26', '2026-10-01'))->toBeTrue()
        ->and(NumberingState::isLive(NumberingPeriod::fromKey('2026-10'), '2026-10-26', '2026-10-01'))->toBeFalse()
        ->and(NumberingState::isLive(NumberingPeriod::fromKey('2027'), null, '2026-10-01'))->toBeFalse()
        ->and(NumberingState::isLive(NumberingPeriod::fromKey('2027'), '2026-10-26', null))->toBeFalse()
        ->and(NumberingState::isLive(NumberingPeriod::fromKey('2026-11'), '2026-10-26', '2026-12-01'))->toBeFalse()
        ->and(NumberingState::beforeGoLive(NumberingPeriod::fromKey('2026-09'), '2026-10-01'))->toBeTrue()
        ->and(NumberingState::beforeGoLive(NumberingPeriod::fromKey('2026'), '2026-10-01'))->toBeFalse()
        ->and(NumberingState::beforeGoLive(NumberingPeriod::fromKey('2030'), null))->toBeTrue();
});

it('names each sequence type by its address, audit code, seed kind and catalogue key (DIR-098 readings 3 and 5)', function () {
    expect(SequenceType::fromSlug('doc-06'))->toBe(SequenceType::Doc06)
        ->and(SequenceType::fromSlug('purchase'))->toBe(SequenceType::Purchase)
        ->and(SequenceType::fromSlug('doc-15'))->toBeNull()
        ->and(SequenceType::Doc01->auditSuffix())->toBe('DOC_01')
        ->and(SequenceType::Project->seedImportKind())->toBe('NUMBER_SEED:PROJECT')
        ->and(SequenceType::Doc14->labelKey())->toBe('documents.doc_14')
        ->and(count(SequenceType::cases()))->toBe(16);
});
