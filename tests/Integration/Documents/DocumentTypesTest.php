<?php

use App\Modules\DOC\Data\DocumentSubject;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-029; DIR-089 (3); criterion 16): the fourteen rows the seed writes take only what the approved V1 text
// determines — each name V1_SCOPE's catalogue's, every type numbered, DOC-05 in its two modes and every other in
// STANDARD — and each type's subject agrees with the arc `documents_subject_by_type` enforces, read from PostgreSQL:
// the subject's columns set make the arc hold, any other column or a missing one breaks it.

uses(RefreshDatabase::class);

/** @return array<string, array{0: string, 1: string, 2: list<string>}> */
function seededTypes(): array
{
    $migration = require database_path('migrations/2026_10_28_000145_doc_seed_document_types.php');

    return (new ReflectionClassConstant($migration, 'TYPES'))->getValue();
}

/** Whether the arc holds for a row of a type and mode with exactly the columns given set. */
function arcHolds(string $type, string $mode, array $columns): bool
{
    $definition = (string) DB::selectOne("select pg_get_constraintdef(oid) as definition from pg_constraint where conname = 'documents_subject_by_type'")->definition;
    $expression = preg_replace('/^CHECK \((.*)\)$/s', '$1', $definition);
    $values = implode(', ', array_map(fn (string $column): string => (in_array($column, $columns, true) ? '1' : 'null')."::bigint as {$column}", DocumentSubject::COLUMNS));

    return DB::selectOne("select ({$expression}) as holds from (select ?::text as document_type_code, ?::text as mode, {$values}) d", [$type, $mode])->holds === true;
}

it('seeds each type with V1_SCOPE\'s name, numbered, in its modes (DIR-089 (3); criterion 16)', function () {
    $catalogue = [];

    foreach (explode("\n", (string) file_get_contents(base_path('docs/01-product/V1_SCOPE.md'))) as $line) {
        if (preg_match('/^\| (DOC-\d{2}) \| ([^|]+) \|/', $line, $row) === 1) {
            $catalogue[$row[1]] = trim($row[2]);
        }
    }

    $types = seededTypes();
    expect(array_keys($types))->toBe(array_keys($catalogue));

    foreach ($types as $code => [$name, , $modes]) {
        expect($name)->toBe($catalogue[$code])
            ->and($modes)->toBe($code === 'DOC-05' ? ['RECEIPT', 'PRE_PAYMENT'] : ['STANDARD']);
    }

    // The seeded rows, where this database keeps them, are the migration's.
    foreach (DB::table('document_types')->whereIn('code', array_keys($types))->where('name', 'not like', 'Dokumen DOC-%')->get() as $row) {
        expect([$row->name, $row->subject_kind, $row->is_numbered])->toBe([$types[$row->code][0], $types[$row->code][1], true]);
    }
});

it('names for each type the subject the documents arc requires, and nothing else satisfies it (criterion 16)', function () {
    foreach (seededTypes() as $code => [, $subject, $modes]) {
        $alternatives = explode('|', $subject);
        // DOC-05: RECEIPT names the payment with its invoice, PRE_PAYMENT the invoice alone, in the seed's order.
        $byMode = $code === 'DOC-05' ? array_combine($modes, $alternatives) : array_fill_keys($modes, $alternatives);

        foreach ($byMode as $mode => $accepted) {
            foreach ((array) $accepted as $alternative) {
                $columns = explode('+', $alternative);
                expect(arcHolds($code, $mode, $columns))->toBeTrue("{$code} {$mode} {$alternative}");

                foreach (DocumentSubject::COLUMNS as $other) {
                    if (! in_array($other, $columns, true)) {
                        expect(arcHolds($code, $mode, [...$columns, $other]))->toBeFalse("{$code} {$mode} {$alternative} + {$other}");
                    }
                }

                if (count($columns) > 1) {
                    expect(arcHolds($code, $mode, [$columns[0]]))->toBeFalse("{$code} {$mode} without {$columns[1]}");
                }
            }

            expect(arcHolds($code, $mode, []))->toBeFalse("{$code} {$mode} without a subject");
        }
    }
});
