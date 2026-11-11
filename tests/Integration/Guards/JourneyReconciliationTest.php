<?php

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Fixtures\GuardsFixture;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;
use Tests\Support\RefreshDatabase;

// TS-11 (TASK-062): the critical journeys' ReconcileGuards check — every guard family reconciled, a discrepancy traced to
// the company owning its record and reported for that company's journey only, the whole database's count beside it — so
// a journey on a shared browser database fails on its own discrepancy and on no other spec's.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }
});

/** @return array{differences: list<string>, all: int, families: int} */
function journeyReconciliation(string ...$companies): array
{
    $answer = GuardsFixture::differences(Request::create('/__fixtures/rekonsiliasi', 'GET', ['perusahaan' => implode(',', $companies)]))->getData(true);

    return is_array($answer) ? $answer : throw new RuntimeException('No answer.');
}

it('reports no discrepancy for a journey whose guards and numbers agree with their facts (TS-11)', function () {
    $world = InvoiceWorld::make();
    $world->issued($world->project(), '1000000.00');
    // The world's projects come from the factory, numbered without a counter (TASK-021): the counter they need, seeded.
    foreach (DB::table('projects')->where('company_id', $world->company->id)->groupBy('period_key')->selectRaw('period_key, max(sequence_no) as highest')->get() as $period) {
        Numbering::seed($world->company, SequenceType::Project, (string) $period->period_key, (int) $period->highest + 1);
    }

    $answer = journeyReconciliation($world->company->public_id);

    expect($answer['differences'])->toBe([])->and($answer['families'])->toBe(14);
});

it('reports a guard that differs from its facts for the company owning it, and for no other company (TS-11)', function () {
    $world = InvoiceWorld::make();
    $invoice = $world->issued($world->project(), '1000000.00');
    $other = Company::factory()->createOne();
    $stored = (string) DB::table('invoice_balances')->where('invoice_id', $invoice)->value('sales_value');

    DB::table('invoice_balances')->where('invoice_id', $invoice)->update(['sales_value' => '999999.00']);

    try {
        $own = journeyReconciliation($world->company->public_id);
        $foreign = journeyReconciliation($other->public_id);

        expect($own['differences'])->toHaveCount(1)->and($own['differences'][0])->toContain('invoice_balances')->toContain((string) $invoice)
            ->and($foreign['differences'])->toBe([])
            ->and($foreign['all'])->toBeGreaterThanOrEqual(1);
    } finally {
        DB::table('invoice_balances')->where('invoice_id', $invoice)->update(['sales_value' => $stored]);
    }
});

it('reports a number issued without its counter for the company owning it, and for no other company (TS-11; DATABASE §3.1)', function () {
    $world = InvoiceWorld::make();
    $world->issued($world->project(), '1000000.00');

    // The factory numbered the world's projects with no PROJECT counter: ReconcileGuards' verified family reports it.
    $own = journeyReconciliation($world->company->public_id);

    expect(implode(' ', $own['differences']))->toContain('number_sequences')->toContain('PROJECT')
        ->and(journeyReconciliation(Company::factory()->createOne()->public_id)['differences'])->toBe([]);
});

it('refuses a journey that names no company, or one that does not exist', function () {
    expect(GuardsFixture::differences(Request::create('/__fixtures/rekonsiliasi', 'GET', ['perusahaan' => '0190a0a0-0000-7000-8000-000000000000']))->getStatusCode())->toBe(422)
        ->and(GuardsFixture::differences(Request::create('/__fixtures/rekonsiliasi', 'GET'))->getStatusCode())->toBe(422);
});
