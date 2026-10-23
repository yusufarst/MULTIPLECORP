<?php

use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\ORG\Models\Company;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\Refusal;

// TL-05 (TASK-006), C-10 and C-11 under two connections (TEST_STRATEGY §6): real PostgreSQL sessions as
// the runtime role, the second blocked on the first's uncommitted write. A counter's draws serialize on
// its row and never draw a number twice, a period's counter is created once, a stale write never lowers
// it, and a number, an issued version, a draft or a chain written twice at once is refused by its key
// once the first commits — never a duplicate committed.

uses(DatabaseTruncation::class);

afterEach(function () {
    // The sessions commit, so the tests after these in this process start from empty tables.
    $this->truncateTablesForAllConnections();
});

const DRAW = "update number_sequences set next_value = next_value + 1
    where company_id = ? and sequence_type = 'DOC-06' and period_key = ? returning next_value - 1";

/**
 * Two sessions write one statement: the first holds its write uncommitted until the second is blocked on
 * it, then commits; each session's outcome is `committed` or the refusal's SQLSTATE and message.
 *
 * @param  list<mixed>  $first
 * @param  list<mixed>  $second
 * @return array<string, mixed>
 */
function race(string $sql, array $first, array $second): array
{
    $holder = static function () use ($sql, $first): string {
        DB::beginTransaction();
        DB::affectingStatement($sql, $first);
        Barrier::await('the first holds its write');
        Sessions::awaitBlocked();
        DB::commit();

        return 'committed';
    };
    $waiter = static function () use ($sql, $second): string {
        Barrier::await('the first holds its write');

        try {
            DB::affectingStatement($sql, $second);
        } catch (QueryException $exception) {
            return $exception->getCode().' '.$exception->getMessage();
        }

        return 'committed';
    };

    return Sessions::run(['first' => $holder, 'second' => $waiter]);
}

/**
 * Drafts of DOC-01 documents of one company, for the issue races.
 *
 * @return list<int>
 */
function quotationDrafts(int $count): array
{
    $company = Company::factory()->create();
    $type = DocumentTypeFactory::ensure('DOC-01');

    return array_map(
        fn (): int => DocumentVersion::factory()->for(Document::factory()->create(['company_id' => $company->id, 'document_type_code' => $type]))->create()->id,
        range(1, $count),
    );
}

it('serializes two sessions\' draws from one counter, never drawing a number twice (C-10; NM-02)', function (string $first, string $second) {
    $company = Company::factory()->create()->id;
    DB::table('number_sequences')->insert(['company_id' => $company, 'sequence_type' => 'DOC-06', 'period_key' => '2026']);
    // A session's process knows only what its closure carries.
    $draw = DRAW;

    $holder = static function () use ($company, $draw): int {
        DB::beginTransaction();
        $number = (int) DB::scalar($draw, [$company, '2026']);
        Barrier::await('the first has drawn');
        Sessions::awaitBlocked();
        DB::commit();

        return $number;
    };
    $waiter = static function () use ($company, $draw): int {
        Barrier::await('the first has drawn');

        return (int) DB::transaction(static fn (): mixed => DB::scalar($draw, [$company, '2026']));
    };

    $numbers = Sessions::run([$first => $holder, $second => $waiter]);

    expect($numbers[$first])->toBe(1)
        ->and($numbers[$second])->toBe(2)
        ->and(DB::table('number_sequences')->where('company_id', $company)->value('next_value'))->toBe(3);
})->with([
    'a draws first, b waits' => ['a', 'b'],
    'b draws first, a waits' => ['b', 'a'],
]);

it('creates a period\'s counter once when two sessions draw its first number together (C-10; NM-02)', function () {
    $company = Company::factory()->create()->id;
    $firstUse = "insert into number_sequences (company_id, sequence_type, period_key) values (?, 'DOC-06', '2026-10') on conflict do nothing";
    $draw = DRAW;

    $holder = static function () use ($company, $firstUse, $draw): int {
        DB::beginTransaction();
        DB::insert($firstUse, [$company]);
        $number = (int) DB::scalar($draw, [$company, '2026-10']);
        Barrier::await('the first has created and drawn');
        Sessions::awaitBlocked();
        DB::commit();

        return $number;
    };
    $waiter = static function () use ($company, $firstUse, $draw): int {
        Barrier::await('the first has created and drawn');

        return (int) DB::transaction(static function () use ($company, $firstUse, $draw): mixed {
            DB::insert($firstUse, [$company]);

            return DB::scalar($draw, [$company, '2026-10']);
        });
    };

    $numbers = Sessions::run(['first' => $holder, 'second' => $waiter]);

    expect($numbers)->toBe(['first' => 1, 'second' => 2])
        ->and(DB::table('number_sequences')->where('company_id', $company)->pluck('next_value')->all())->toBe([3]);
});

it('refuses the second of two concurrent writes that would break C-10 or C-11, the second waiting for the first (TEST_STRATEGY §6)', function (Closure $prepare) {
    [$sql, $first, $second, $sqlState, $constraint] = $prepare();

    $outcomes = race($sql, $first, $second);

    expect($outcomes['first'])->toBe('committed')
        ->and($outcomes['second'])->toStartWith($sqlState)->toContain($constraint);
})->with([
    'a period\'s counter created twice (C-10)' => [function () {
        $company = Company::factory()->create()->id;

        return ["insert into number_sequences (company_id, sequence_type, period_key) values (?, 'DOC-06', '2026-11')", [$company], [$company], Refusal::UNIQUE, 'number_sequences_pkey'];
    }],
    'a counter lowered by a stale write (C-10)' => [function () {
        $company = Company::factory()->create()->id;
        DB::table('number_sequences')->insert(['company_id' => $company, 'sequence_type' => 'DOC-06', 'period_key' => '2026', 'next_value' => 10]);
        // Both read 10: the first draws 10, the second writes back what it read.
        $write = "update number_sequences set next_value = ? where company_id = ? and sequence_type = 'DOC-06' and period_key = '2026'";

        return [$write, [11, $company], [10, $company], Refusal::CHECK, 'number_sequences_c10_never_lowered'];
    }],
    'one number issued on two documents (C-10)' => [function () {
        [$a, $b] = quotationDrafts(2);
        $issue = "update document_versions set state = 'ISSUED', business_date = '2026-10-01', period_key = '2026', sequence_no = 1,
            formatted_number = ?, payload = '{}', issue_command_id = uuidv7() where id = ?";

        return [$issue, ['CTH/PNW/2026/0001', $a], ['CTH/PNW/2026/0001-B', $b], Refusal::UNIQUE, 'document_versions_c10_number'];
    }],
    'one formatted number issued on two documents (C-10)' => [function () {
        [$a, $b] = quotationDrafts(2);
        $issue = "update document_versions set state = 'ISSUED', business_date = '2026-10-01', period_key = '2026', sequence_no = ?,
            formatted_number = 'CTH/PNW/2026/0001', payload = '{}', issue_command_id = uuidv7() where id = ?";

        return [$issue, [1, $a], [2, $b], Refusal::UNIQUE, 'document_versions_c10_formatted_number'];
    }],
    'two issued versions of one document (C-11)' => [function () {
        $document = Document::factory()->create(['document_type_code' => DocumentTypeFactory::ensure('DOC-01')]);
        $issue = "insert into document_versions (document_id, company_id, document_type_code, version_no, state, business_date, period_key, sequence_no, formatted_number, payload)
            values (?, ?, 'DOC-01', ?, 'ISSUED', '2026-10-01', '2026', ?, ?, '{}')";

        return [$issue, [$document->id, $document->company_id, 1, 1, 'CTH/PNW/2026/0001'], [$document->id, $document->company_id, 2, 2, 'CTH/PNW/2026/0002'], Refusal::UNIQUE, 'document_versions_c11_one_issued'];
    }],
    'two drafts of one document (C-11)' => [function () {
        $document = Document::factory()->create(['document_type_code' => DocumentTypeFactory::ensure('DOC-01')]);
        $draft = "insert into document_versions (document_id, company_id, document_type_code, version_no) values (?, ?, 'DOC-01', ?)";

        return [$draft, [$document->id, $document->company_id, 1], [$document->id, $document->company_id, 2], Refusal::UNIQUE, 'document_versions_c11_one_draft'];
    }],
    'two quotation chains of one project (C-11)' => [function () {
        $company = Company::factory()->create()->id;
        DocumentTypeFactory::ensure('DOC-01');

        return ["insert into documents (company_id, document_type_code, project_id) values (?, 'DOC-01', 7)", [$company], [$company], Refusal::UNIQUE, 'documents_c11_quotation_chain'];
    }],
]);
