<?php

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-15 (TASK-021; NM-01; QB-13): the draw within its lock budget — on an existing counter it reads the scheme in
// force, locks the counter last (LK-31) and increments it by one statement returning the number, so the row is held
// only for the statements that remain before commit; it reads no business table.

uses(RefreshDatabase::class);

it('draws from an existing counter with one lock and one increment, after which only the command\'s end remains (NM-01; NM-02)', function () {
    Authorization::catalogue();
    $owner = TwoFactor::enrol(Authorization::owner());
    $company = Company::factory()->create();
    $year = substr(CarbonImmutable::now('Asia/Jakarta')->toDateString(), 0, 4);
    Numbering::scheme($company, SequenceType::Doc06);
    Numbering::seed($company, SequenceType::Doc06, $year, 5);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $result = Numbering::draw($owner->id, $company, SequenceType::Doc06, CarbonImmutable::now('Asia/Jakarta')->toDateString());
    $queries = array_map(fn (array $query): string => $query['query'], DB::getQueryLog());
    DB::disableQueryLog();

    $counter = array_keys(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'number_sequences')));
    $increment = array_values(array_filter($counter, fn (int $index): bool => str_starts_with($queries[$index], 'update number_sequences')));

    expect($result)->toBe("COMMITTED 5 CTH/{$year}/0005")
        ->and($counter)->toHaveCount(2)
        ->and($increment)->toHaveCount(1)
        ->and(count($queries))->toBeLessThanOrEqual(30)
        ->and(implode(' ', $queries))->not->toContain('document_versions')->not->toContain('projects');
});
