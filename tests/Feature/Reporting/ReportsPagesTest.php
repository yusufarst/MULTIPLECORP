<?php

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Models\Company;
use App\Modules\Reporting\Reports\Filters;
use App\Modules\Reporting\Reports\Reports;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\OtherCashWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;

// TL-04, TL-06 (TASK-060; ROUTE-051; ROUTE-052; RD-02–RD-07; CS-07; CS-08; OD-14; acceptance criteria 1–4, 11): PG-051 and
// PG-052 per persona and read class, the five concepts kept apart on real facts, one section per company and no total
// across companies, a company filter that never widens, and each report within QB-11's six statements in one snapshot.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->hot = (string) tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hot, 'http://localhost:5173');
    Vite::useHotFile($this->hot);
});

afterEach(function () {
    @unlink($this->hot);
});

/**
 * One company's facts of today: an issued invoice of Rp1,000,000, a payment of Rp400,000 applied to nothing, an expense
 * of Rp300,000 on the project — a cost and no Cash-Out —, then its payment, the Cash-Out, the cost not counted twice.
 *
 * @return array{world: InvoiceWorld, project: int}
 */
function reportFacts(): array
{
    $world = InvoiceWorld::make();
    $project = $world->project();
    $invoice = $world->issued($project, '1000000.00');
    InvoiceWorld::payment($invoice, '400000.00');
    $actor = OtherCashWorld::actor($world->company->id);
    $expense = OtherCashWorld::committed(OtherCashWorld::expense($actor, $world->company->id, $project->id, '300000.00'));

    return ['world' => $world, 'project' => $project->id, 'expense' => $expense, 'actor' => $actor];
}

/** @return list<array<string, mixed>> */
function reportTables(int $userId, string $code, array $keys = []): array
{
    $viewer = app(EffectiveCapabilities::class)->for($userId);
    $report = app(Reports::class)->find($code) ?? throw new RuntimeException($code);

    return Reports::read($report, $viewer, Filters::of($viewer, $keys, BusinessDate::today()));
}

/** @param  list<array<string, mixed>>  $tables */
function reportTable(array $tables, string $key): array
{
    foreach ($tables as $table) {
        if ($table['key'] === $key) {
            return $table;
        }
    }

    throw new RuntimeException("No table {$key}.");
}

it('opens PG-051 to each report\'s read class and PG-052 to the Owner alone (ROUTE-051; ROUTE-052; RD-02–RD-07; OD-14)', function () {
    $company = Company::factory()->createOne();
    $owner = User::query()->findOrFail(Authorization::owner()->id);
    $stock = User::query()->findOrFail(Authorization::admin([$company->id], ['stock.view'])->id);
    $finance = User::query()->findOrFail(Authorization::admin([$company->id], ['finance.view', 'reports.export'])->id);
    $none = User::query()->findOrFail(Authorization::admin([$company->id], ['evidence.view'])->id);

    $this->actingAs($owner)->get('/laporan')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Reporting/Laporan')
        ->where('reports', ['stok', 'pembelian', 'penjualan', 'piutang', 'pembayaran', 'laba', 'arus-kas'])->where('report', 'stok')->where('can.consolidated', true)->missing('tables'));
    $this->actingAs($stock)->get('/laporan?laporan=penjualan')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('reports', ['stok'])->where('report', 'stok')->where('can.export', false));
    $this->actingAs($finance)->get('/laporan?laporan=piutang')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('reports', ['penjualan', 'piutang', 'pembayaran', 'arus-kas'])->where('report', 'piutang')->where('can.export', true));
    // A missing view capability is refused (AZ-11; EN-01); the Owner-only PG-052 answers an Admin as PG-005 (OD-14).
    $this->actingAs($none)->get('/laporan')->assertForbidden();
    $this->actingAs($finance)->get('/laporan/gabungan')->assertNotFound();
    $this->actingAs($owner)->get('/laporan/gabungan')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Reporting/LaporanGabungan')->where('reports', ['gabungan', 'alokasi']));
});

it('keeps sales value, receivable, Cash-In, cost and Cash-Out apart, each on its own date, a cost no Cash-Out (criteria 1, 2; BR-FIN-01; CALC-08–CALC-14)', function () {
    $facts = reportFacts();
    $world = $facts['world'];
    $owner = Authorization::owner()->id;
    $code = $world->company->code;

    $sales = reportTable(reportTables($owner, 'penjualan', ['perusahaan' => $code]), 'penjualan');
    expect($sales['sections'])->toHaveCount(1)->and($sales['sections'][0]['company'])->toBe($code)
        ->and($sales['sections'][0]['total'][4])->toBe('1000000.00')->and($sales['sections'][0]['total'][5])->toBe('0.00');

    $cash = reportTable(reportTables($owner, 'arus-kas', ['perusahaan' => $code]), 'arus-kas');
    // The expense is a cost and moves no cash.
    expect($cash['sections'][0]['total'])->toBe([null, '400000.00', '0.00', '400000.00']);

    $profit = reportTables($owner, 'laba', ['perusahaan' => $code]);
    expect(reportTable($profit, 'proyek')['sections'][0]['rows'][0][2])->toBe('1000000.00')
        ->and(reportTable($profit, 'proyek')['sections'][0]['rows'][0][3])->toBe('300000.00')
        ->and(reportTable($profit, 'proyek')['sections'][0]['rows'][0][4])->toBe('700000.00')
        ->and(reportTable($profit, 'proyek')['sections'][0]['rows'][0][5])->toBe('70.0')
        ->and(reportTable($profit, 'perusahaan')['sections'][0]['rows'][0])->toBe(['1000000.00', '300000.00', '700000.00', '0.00', '0.00', '700000.00']);

    // Its payment is the Cash-Out; the cost is not counted twice.
    OtherCashWorld::committed(OtherCashWorld::disburse($facts['actor'], $world->company->id, 'EXPENSE_PAYMENT', '300000.00', PaymentWorld::account($world->company->id), ['expense' => $facts['expense']]));
    expect(reportTable(reportTables($owner, 'arus-kas', ['perusahaan' => $code]), 'arus-kas')['sections'][0]['total'])->toBe([null, '400000.00', '300000.00', '100000.00'])
        ->and(reportTable(reportTables($owner, 'laba', ['perusahaan' => $code]), 'perusahaan')['sections'][0]['rows'][0][5])->toBe('700000.00');

    // Last month holds none of today's facts (CALC-13).
    expect(reportTable(reportTables($owner, 'penjualan', ['perusahaan' => $code, 'periode' => 'bulan-lalu']), 'penjualan')['sections'][0]['rows'])->toBe([]);
});

it('shows an Admin one section per company in scope, no total across them, and ignores a company outside scope (criteria 3, 4; CS-07; CS-08; DP-02)', function () {
    $facts = reportFacts();
    $world = $facts['world'];
    $other = Company::factory()->createOne(['code' => 'ZZZ']);
    $outside = Company::factory()->createOne(['code' => 'OUT']);
    $admin = Authorization::admin([$world->company->id, $other->id], ['finance.view'])->id;

    $tables = reportTables($admin, 'penjualan');
    $sections = reportTable($tables, 'penjualan')['sections'];
    expect(array_column($sections, 'company'))->toBe([$world->company->code, 'ZZZ'])
        ->and($sections[0]['total'][4])->toBe('1000000.00')->and($sections[1]['rows'])->toBe([])->and($sections[1]['total'])->toBeNull();

    // A company outside the scope widens nothing: the scope stands, as no filter does.
    expect(array_column(reportTable(reportTables($admin, 'penjualan', ['perusahaan' => 'OUT']), 'penjualan')['sections'], 'company'))->toBe([$world->company->code, 'ZZZ'])
        ->and(array_column(reportTable(reportTables($admin, 'penjualan', ['perusahaan' => 'ZZZ']), 'penjualan')['sections'], 'company'))->toBe(['ZZZ']);
    expect($outside->id)->toBeInt();
});

it('gives the Owner the consolidated figures with Gabungan, the sums of the companies (CALC-12; J-FIN-02)', function () {
    $facts = reportFacts();
    Company::factory()->createOne(['code' => 'ZZZ']);
    $owner = Authorization::owner()->id;
    $rows = reportTable(reportTables($owner, 'gabungan'), 'angka')['sections'][0]['rows'];
    $gabungan = $rows[count($rows) - 1];

    expect($rows)->toHaveCount(3)->and(array_column($rows, 0))->toBe([$facts['world']->company->code, 'ZZZ', null])
        ->and($gabungan[1])->toBe('1000000.00')->and($gabungan[5])->toBe('400000.00')->and($gabungan[7])->toBe('0.00');
    expect($facts['project'])->toBeInt();
});

it('reads each report within QB-11: at most six statements in one snapshot (criterion 11; PF-27; TX-10)', function () {
    reportFacts();
    $owner = Authorization::owner()->id;

    foreach (['stok', 'pembelian', 'penjualan', 'piutang', 'pembayaran', 'laba', 'arus-kas', 'gabungan', 'alokasi'] as $code) {
        $viewer = app(EffectiveCapabilities::class)->for($owner);
        $report = app(Reports::class)->find($code) ?? throw new RuntimeException($code);
        $filters = Filters::of($viewer, [], BusinessDate::today());
        DB::flushQueryLog();
        DB::enableQueryLog();
        Reports::read($report, $viewer, $filters);
        $statements = count(array_filter(DB::getQueryLog(), fn (array $query): bool => ! str_starts_with(strtolower($query['query']), 'set transaction')));
        DB::disableQueryLog();

        expect($statements)->toBeLessThanOrEqual(6, $code);
    }
});

it('serves the deferred tables of the page as the read gives them (ROUTE-051; PT-19)', function () {
    $facts = reportFacts();
    $owner = User::query()->findOrFail(Authorization::owner()->id);
    $version = (string) $this->actingAs($owner)->get('/laporan')->viewData('page')['version'];
    $tables = $this->actingAs($owner)->get('/laporan?laporan=penjualan&perusahaan='.$facts['world']->company->code, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'Reporting/Laporan', 'X-Inertia-Partial-Data' => 'tables'])->json('props.tables');

    expect($tables[0]['key'])->toBe('penjualan')->and($tables[0]['sections'][0]['total'][4])->toBe('1000000.00');
});
