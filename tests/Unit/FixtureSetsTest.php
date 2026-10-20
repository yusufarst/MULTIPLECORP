<?php

use Illuminate\Support\Str;
use Tests\Support\FixtureSets;

// TASK-003, acceptance criterion 3: the fixture sets FX-01–FX-10 of TEST_STRATEGY §4 load as datasets,
// each true to its §4 row and to the documents it is transcribed from — where they differ, the source
// prevails and the fixture is corrected.

function fixtureDocument(string $path): string
{
    return (string) file_get_contents(base_path($path));
}

/**
 * @return array<int, array{string, string}> each scenario's title and walk-through, by number
 */
function fixtureScenarioRows(string $path, string $heading): array
{
    $section = Str::before(Str::after(fixtureDocument($path), $heading), "\n## ");
    preg_match_all('/^\| (\d+) \| (.+?) \| (.+) \| PASS \|\s*$/m', $section, $rows, PREG_SET_ORDER);

    return collect($rows)->mapWithKeys(fn (array $row) => [(int) $row[1] => [$row[2], $row[3]]])->all();
}

it('loads each fixture set as its row of TEST_STRATEGY §4 defines it', function (string $id) {
    preg_match('/^\| '.$id.' \| (.*) \| (.*) \| (.*) \|$/m', fixtureDocument('docs/08-testing/TEST_STRATEGY.md'), $row);
    $set = FixtureSets::load($id);

    expect($set['title'])->toBe($row[1])
        ->and($set['source'])->toBe(preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $row[2]))
        ->and($set['used_by'])->toBe($row[3]);

    foreach ($set['documents'] as $document) {
        expect(base_path($document))->toBeFile();
    }
})->with(FixtureSets::IDS);

it('refuses a fixture set that does not exist', function () {
    expect(fn () => FixtureSets::load('FX-11'))->toThrow(InvalidArgumentException::class);
});

it('holds the numeric annex as written, every stated amount in its text (FX-01)', function () {
    $annex = Str::before(Str::after(fixtureDocument('docs/02-domain/BUSINESS_RULES.md'), '### Numeric annex'), "\n## ");
    preg_match_all('/^(\d+)\. \*\*(.+?)\*\* (.*)$/m', $annex, $items, PREG_SET_ORDER);
    $examples = FixtureSets::load('FX-01')['entries'];

    expect($items)->toHaveCount(16)->and($examples)->toHaveCount(16);

    foreach ($items as [, $number, $title, $text]) {
        $example = $examples[(int) $number];

        expect($example['title'])->toBe(rtrim($title, '.'))->and($example['text'])->toBe($text);

        foreach ($example['values'] as $value) {
            expect($value)->toBeInt();

            if ($value >= 1_000) {
                expect($text)->toMatch('/(?<![\d,])'.preg_quote(number_format($value), '/').'(?!\d|,\d)/');
            }
        }
    }
});

it('holds the synthetic group with invented names and reserved e-mail domains (FX-02)', function () {
    $group = FixtureSets::load('FX-02')['entries'];

    expect(array_keys($group['companies']))->toBe(['ARJ', 'BTN', 'CKP'])
        ->and($group['shared_pool']['companies'])->toBe(['ARJ', 'BTN']);

    foreach ($group['companies'] as $company) {
        expect($company['name'])->toContain('Contoh');
    }

    foreach ($group['accounts'] as $account) {
        expect($account['email'])->toMatch('/@example\.com$/')
            ->and($account['role'])->toBeIn(['OWNER', 'ADMIN_OPERASIONAL'])
            ->and(array_diff($account['companies'], array_keys($group['companies'])))->toBe([]);
    }

    expect(collect($group['accounts'])->where('role', 'OWNER'))->toHaveCount(2)
        ->and(json_encode($group))->not->toMatch('/password|secret|token/i');
});

it('holds P3\'s eighteen scenarios as written (FX-03)', function () {
    $rows = fixtureScenarioRows('docs/03-workflows/evidence/P3_QUALITY_GATE.md', '## Final adversarial verification (DIR-026 §8)');
    $scenarios = FixtureSets::load('FX-03')['entries'];

    expect(array_keys($scenarios))->toBe(range(1, 18))->and(array_keys($rows))->toBe(range(1, 18));

    foreach ($rows as $number => [$title, $walkthrough]) {
        expect($scenarios[$number]['title'])->toBe($title)->and($scenarios[$number]['walkthrough'])->toBe($walkthrough);
    }
});

it('holds the targeted-review fixtures of DATABASE §31 with P4\'s walk-throughs as written (FX-04)', function () {
    $set = FixtureSets::load('FX-04');
    $rows = fixtureScenarioRows('docs/04-architecture/evidence/P4_QUALITY_GATE.md', '## Final adversarial re-test (DIR-029 §18)');
    $obligations = fixtureDocument('docs/04-architecture/DATABASE/s29-31-diagrams-traceability.md');

    expect($set['entries'])->toHaveCount(15)->and(array_keys($set['scenarios']))->toBe(range(1, 14));

    foreach ($set['entries'] as $fixture) {
        expect($set['title'])->toContain($fixture['fixture'])
            ->and($obligations)->toContain($fixture['fixture'])
            ->and(array_diff($fixture['scenarios'], array_keys($rows)))->toBe([]);
    }

    foreach ($rows as $number => [$title, $walkthrough]) {
        expect($set['scenarios'][$number]['title'])->toBe($title)->and($set['scenarios'][$number]['walkthrough'])->toBe($walkthrough);
    }
});

it('holds DIR-027\'s eight unattributed-loss cases with the clarification they prove (FX-05)', function () {
    $set = FixtureSets::load('FX-05');

    expect($set['entries'])->toHaveCount(8)
        ->and(fixtureDocument('docs/00-governance/GAP_REGISTER.md'))->toContain($set['rule']);
});

it('holds AS-04\'s volume as written and multiplies every table at 10x and 100x (FX-06)', function () {
    $set = FixtureSets::load('FX-06');
    preg_match('/^\| AS-04 \| (.*) \|/m', fixtureDocument('docs/06-api-performance/PERFORMANCE.md'), $row);

    foreach ($set['entries'] as $table => $count) {
        expect($row[1])->toMatch('/(?<![\d,])'.preg_quote(number_format($count), '/').' (\w+ )?'.Str::afterLast($table, ' ').'\b/');
    }

    expect($set['scales'])->toBe([1, 10, 100])
        ->and($set['entries']['ledger entries'] * 100)->toBe(12_000_000)
        ->and($set['entries']['audit events'] * 100)->toBe(30_000_000);
});

it('holds institution names that each search term reaches at the start and further in (FX-07)', function () {
    $set = FixtureSets::load('FX-07');
    $names = $set['entries'];

    expect(array_unique($names))->toBe($names);

    foreach ($names as $name) {
        expect($name)->toContain('Contoh');
    }

    foreach ($set['terms'] as $term) {
        $hits = array_filter($names, fn (string $name) => str_contains($name, $term));

        expect(count($hits))->toBeGreaterThan(2)
            ->and(array_filter($hits, fn (string $name) => str_starts_with($name, $term)))->not->toBe([])
            ->and(array_filter($hits, fn (string $name) => ! str_starts_with($name, $term)))->not->toBe([]);
    }
});

it('holds opening-import batches that are not clean (FX-09)', function () {
    $set = FixtureSets::load('FX-09');
    $batches = $set['entries'];
    $required = [
        'lot' => ['product', 'location', 'quantity', 'unit_cost'],
        'receivable' => ['client', 'billed_at', 'remaining'],
        'customer_credit' => ['client', 'amount'],
        'purchase' => ['supplier', 'ordered_at', 'quantity', 'unit_price'],
    ];
    $defect = function (array $row) use ($required): ?string {
        foreach ($required[$row['kind']] as $field) {
            if (! isset($row[$field])) {
                return "missing {$field}";
            }
        }

        foreach ($row as $field => $value) {
            $valid = match ($field) {
                'quantity' => preg_match('/^\d+\.\d{3}$/', (string) $value) === 1,
                'unit_cost', 'unit_price', 'remaining', 'amount' => preg_match('/^\d+\.\d{2}$/', (string) $value) === 1,
                'billed_at', 'due_date', 'ordered_at' => $value === null
                    || (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $date) === 1 && checkdate((int) $date[2], (int) $date[3], (int) $date[1])),
                default => true,
            };

            if (! $valid) {
                return "invalid {$field}";
            }
        }

        return null;
    };

    expect(array_keys($batches))->toBe(['clean', 'malformed', 'duplicates', 'out_of_scope', 'legacy_numbers']);

    foreach ([...$batches['clean'], ...$batches['duplicates'], ...$batches['out_of_scope'], ...$batches['legacy_numbers']] as $row) {
        expect($defect($row))->toBeNull();
    }

    foreach ($batches['malformed'] as $case) {
        expect($case['defect'])->not->toBeEmpty()->and($defect($case['row']))->not->toBeNull();
    }

    $cleanNumbers = array_column($batches['clean'], 'legacy_number');

    foreach ($batches['duplicates'] as $row) {
        expect($cleanNumbers)->toContain($row['legacy_number']);
    }

    foreach ($batches['out_of_scope'] as $row) {
        expect($set['batch']['scope'])->not->toContain($row['company']);
    }

    foreach ($batches['legacy_numbers'] as $row) {
        expect($row['legacy_number'])->not->toMatch('/^[A-Z]+\/ARJ\/\d{4}\/\d{4}$/');
    }
});

it('holds a sample of each generated document type, DOC-01–DOC-14 (FX-10)', function () {
    preg_match_all('/^\| (DOC-\d\d) \| (.+?) \|/m', fixtureDocument('docs/01-product/V1_SCOPE.md'), $rows, PREG_SET_ORDER);
    $documents = FixtureSets::load('FX-10')['entries'];
    $companies = array_keys(FixtureSets::load('FX-02')['entries']['companies']);

    expect(array_keys($documents))->toBe(array_column($rows, 1));

    foreach ($rows as [, $id, $name]) {
        $document = $documents[$id];

        expect($document['name'])->toBe($name)
            ->and($companies)->toContain($document['issuer'])
            ->and($document['total'])->toBe(array_sum(array_map(fn (array $line) => $line[1] * $line[3], $document['lines'])));
    }
});
