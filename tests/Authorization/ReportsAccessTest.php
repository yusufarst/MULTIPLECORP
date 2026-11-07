<?php

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\Reporting\Exports\ExportContext;
use App\Modules\Reporting\Reports\Filters;
use App\Modules\Reporting\Reports\Reports;
use App\Support\BusinessDate;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\CostWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-06, TL-14 (TASK-060; RD-02–RD-07; PJ-05–PJ-07; PJ-22; CS-07; CS-08; SM-25; DP-07; acceptance criteria 3, 5, 10): the
// reports' projections over an inter-company draw — company B's project drawing 6 of company A's lot of 10 at 12,000 —
// for a source-side Admin, a bearer-side Admin and the Owner; a pending quantity as each company's own exposure only;
// and an export's recorded context, narrowed to what was recorded.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->a = CostWorld::make();
    $this->b = CostWorld::make();
    $this->lot = $this->a->receive($this->a->line($this->a->purchase(), '10', '120000.00'), '10');
    $this->project = $this->a->project($this->b->company, 'ACTIVE');
    $this->draw = $this->a->dispatch($this->lot, '6', $this->project, 'Stok A dipakai untuk proyek B');
    $this->lotCode = (string) DB::table('inventory_lots')->where('id', $this->lot)->value('lot_code');
    $this->number = (string) DB::table('projects')->where('id', $this->project->id)->value('project_number');
    $this->actor = (string) DB::table('users')->where('id', $this->a->actorId)->value('name');
});

/** @return array<string, mixed> */
function accessTable(int $userId, string $report, string $key, array $keys = []): array
{
    $viewer = app(EffectiveCapabilities::class)->for($userId);
    $tables = Reports::read(app(Reports::class)->find($report) ?? throw new RuntimeException($report), $viewer, Filters::of($viewer, $keys, BusinessDate::today()));

    foreach ($tables as $table) {
        if ($table['key'] === $key) {
            return $table;
        }
    }

    throw new RuntimeException("No table {$key} in {$report}.");
}

it('shows the source side the draw with the bearer, its project, reason and actor masked (criterion 10; PJ-05)', function () {
    $source = Authorization::admin([$this->a->company->id], ['cost.view'])->id;
    $sections = accessTable($source, 'pembelian', 'alokasi')['sections'];

    expect($sections)->toHaveCount(1)->and($sections[0]['company'])->toBe($this->a->company->code)
        ->and($sections[0]['rows'][0][3])->toBe('6.000')->and($sections[0]['rows'][0][4])->toBeNull()
        ->and(array_slice($sections[0]['rows'][0], 5))->toBe([$this->a->company->code, null, null, 'pages.pg_051.class.hpp', '72000.00', null, null]);
});

it('shows the bearer side the cost in full with the source masked, the lot code with stock.view only (criterion 10; PJ-06)', function () {
    $bearer = Authorization::admin([$this->b->company->id], ['cost.view'])->id;
    $stock = Authorization::admin([$this->b->company->id], ['cost.view', 'stock.view'])->id;
    $row = accessTable($bearer, 'pembelian', 'alokasi')['sections'][0]['rows'][0];

    expect(array_slice($row, 4))->toBe([null, null, $this->b->company->code, $this->number, 'pages.pg_051.class.hpp', '72000.00', 'Stok A dipakai untuk proyek B', $this->actor])
        ->and(accessTable($stock, 'pembelian', 'alokasi')['sections'][0]['rows'][0][4])->toBe($this->lotCode);
});

it('shows the Owner both sides on PG-052 in one section, and an Admin of both each row once under its bearer, no total across companies (criteria 3, 10; RD-07; CS-08)', function () {
    $owner = Authorization::owner()->id;
    $both = Authorization::admin([$this->a->company->id, $this->b->company->id], ['cost.view'])->id;
    $pooled = accessTable($owner, 'alokasi', 'alokasi')['sections'];

    expect($pooled)->toHaveCount(1)->and($pooled[0]['company'])->toBeNull()
        ->and(array_slice($pooled[0]['rows'][0], 5, 3))->toBe([$this->a->company->code, $this->b->company->code, $this->number]);

    $sections = accessTable($both, 'pembelian', 'alokasi')['sections'];
    $byCompany = array_combine(array_column($sections, 'company'), array_map(fn (array $section): int => count($section['rows']), $sections));
    expect($byCompany)->toEqual([$this->a->company->code => 0, $this->b->company->code => 1]);
});

it('keeps a company\'s purchase lines and stock value to holders of cost.view in its own section (RD-05; PJ-22; CALC-03)', function () {
    $source = Authorization::admin([$this->a->company->id], ['cost.view', 'stock.view'])->id;
    $stockOnly = Authorization::admin([$this->a->company->id], ['stock.view'])->id;
    // The world's purchase is dated 2026-10-01 (PurchaseFactory); the period is its own day.
    $receipts = accessTable($source, 'pembelian', 'penerimaan', ['periode' => 'rentang', 'dari' => '2026-10-01', 'sampai' => '2026-10-01'])['sections'][0];

    expect($receipts['company'])->toBe($this->a->company->code)->and($receipts['rows'][0][5])->toBe('10.000')->and($receipts['rows'][0][7])->toBe('0.000')
        ->and(accessTable($source, 'stok', 'nilai')['sections'][0]['rows'][0][0])->toBe('48000.00');

    $viewer = app(EffectiveCapabilities::class)->for($stockOnly);
    $keys = array_column(Reports::read(app(Reports::class)->find('stok') ?? throw new RuntimeException, $viewer, Filters::of($viewer, [], BusinessDate::today())), 'key');
    expect($keys)->toBe(['posisi', 'selisih', 'reservasi'])
        ->and(app(Reports::class)->find('pembelian')?->opens($viewer))->toBe([]);
});

it('records an export\'s context and narrows the job\'s read to it, a capability gained since included (DP-07; RV-07)', function () {
    $user = Authorization::admin([$this->a->company->id, $this->b->company->id], ['cost.view'])->id;
    $viewer = app(EffectiveCapabilities::class)->for($user);
    $report = app(Reports::class)->find('pembelian') ?? throw new RuntimeException;
    $context = ExportContext::of($report, $viewer, Filters::of($viewer, ['perusahaan' => $this->b->company->code], BusinessDate::today()));

    expect($context->companies)->toBe([$this->b->company->id])->and($context->capabilities)->toBe(['cost.view'])->and($context->masking)->toBe([])
        ->and($context->coveredBy($viewer))->toBeTrue();

    // stock.view granted since: the job's read still masks the lot code, as the request recorded (DP-07).
    Authorization::grant(User::query()->findOrFail($user), 'stock.view');
    $now = app(EffectiveCapabilities::class)->fresh($user);
    $narrow = $context->narrow($now);
    expect($narrow->has('stock.view'))->toBeFalse()->and($narrow->scope->includes($this->a->company->id))->toBeFalse()->and($narrow->scope->includes($this->b->company->id))->toBeTrue();

    $stored = ExportContext::stored(...array_values($context->columns()));
    expect($stored->companies)->toBe($context->companies)->and($stored->capabilities)->toBe($context->capabilities)->and($stored->masking)->toBe([]);
});

it('shows a pending quantity as each company\'s own exposure only, never another candidate or a cost (criterion 5; UXS-39; PJ-07; PJ-22)', function () {
    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $world = InvoiceWorld::make();
    $actor = ReceivingWorld::actor($world->company, StockWorld::CAPABILITIES)->id;
    $goods = Projects::product();
    $rack = ReceivingWorld::rack();
    $lot = StockWorld::lot($actor, $world->company, $goods['product']->id, $goods['base']->id, $rack);
    StockWorld::pendingChange($actor, $world->company, $goods['product']->id, $lot, $rack, '2', '10');

    $own = Authorization::admin([$world->company->id], ['cost.view', 'stock.view'])->id;
    $other = Authorization::admin([$this->a->company->id], ['cost.view', 'stock.view'])->id;
    $exposure = accessTable($own, 'stok', 'paparan')['sections'];

    expect($exposure)->toHaveCount(1)->and($exposure[0]['rows'])->toHaveCount(1)->and($exposure[0]['rows'][0][2])->toBe('10.000')
        ->and(accessTable($other, 'stok', 'paparan')['sections'][0]['rows'])->toBe([]);
});
