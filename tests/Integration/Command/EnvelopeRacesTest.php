<?php

use App\Modules\CAT\Models\Product;
use App\Modules\DOC\Models\BankStatementLine;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\Payment;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\ORG\Models\Company;
use App\Modules\PUR\Models\PurchaseLine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Commands\TestCommands;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;

// TL-05 (TASK-013): the envelope's races under two real sessions (CONCURRENCY_IDEMPOTENCY §18 on test commands;
// TEST_STRATEGY §6): two commands meeting at one lock serialize in the canonical order without deadlock, the
// second judged on the committed row — one COMMITTED, the other REJECTED by its guard's rule (M-01, M-02;
// architecture challenge 2a, 3a, 6a, 6c, 18a) —, and a duplicate of one `command_id` in flight waits on its
// identity row and replays what ran, never running twice (CI-04; 15c, 19a).

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $this->owner = Authorization::owner()->id;
});

/**
 * Two commands of the given moves: the first holds its locks until the second waits on them, then commits.
 *
 * @param  list<array{table: string, key: array<string, int|string>, deltas: array<string, int|string>}>  $moves
 * @return array{first: string, second: string}
 */
function envelopeRace(int $actor, array $moves, string $capability, ?string $sharedId = null): array
{
    $first = $sharedId ?? (string) Str::uuid7();
    $second = $sharedId ?? (string) Str::uuid7();

    return Sessions::run([
        'first' => static fn (): string => TestCommands::text(TestCommands::move($first, $actor, null, $capability, $moves, static function (): void {
            Barrier::await('the first holds its locks');
            Sessions::awaitBlocked();
        })),
        'second' => static function () use ($second, $actor, $capability, $moves): string {
            Barrier::await('the first holds its locks');

            return TestCommands::text(TestCommands::move($second, $actor, null, $capability, $moves));
        },
    ]);
}

it('serializes two commands for the last unit: one COMMITTED, one REJECTED, AVAILABLE never negative (M-01; 3a)', function () {
    $product = Product::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $product, 'on_hand_qty' => 1]);

    $outcomes = envelopeRace($this->owner, [['table' => 'stock_product_balances', 'key' => ['product_id' => $product], 'deltas' => ['on_hand_qty' => -1]]], 'stock.dispatch');

    expect($outcomes)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED C-04'])
        ->and((string) DB::table('stock_product_balances')->where('product_id', $product)->value('on_hand_qty'))->toBe('0.000');
});

it('serializes a reservation and a dispatch of the last unit (M-02; 3a)', function () {
    $product = Product::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $product, 'on_hand_qty' => 1]);
    $key = ['product_id' => $product];
    $owner = $this->owner;

    $outcomes = Sessions::run([
        'reservation' => static fn (): string => TestCommands::text(TestCommands::move((string) Str::uuid7(), $owner, null, 'stock.reserve', [['table' => 'stock_product_balances', 'key' => $key, 'deltas' => ['reserved_qty' => 1]]], static function (): void {
            Barrier::await('held');
            Sessions::awaitBlocked();
        })),
        'dispatch' => static function () use ($owner, $key): string {
            Barrier::await('held');

            return TestCommands::text(TestCommands::move((string) Str::uuid7(), $owner, null, 'stock.dispatch', [['table' => 'stock_product_balances', 'key' => $key, 'deltas' => ['on_hand_qty' => -1]]]));
        },
    ]);

    expect($outcomes)->toBe(['reservation' => 'COMMITTED', 'dispatch' => 'REJECTED C-04']);
});

it('applies one payment twice at once within its money and the invoice\'s gross (C-13; C-15; 6a)', function () {
    $payment = Payment::factory()->create(['amount' => '1000000.00']);
    DB::table('application_source_balances')->insert(['payment_id' => $payment->id, 'company_id' => $payment->company_id, 'client_organization_id' => $payment->client_organization_id, 'gross_amount' => $payment->amount]);
    $invoice = Invoice::factory()->create();
    DB::table('invoice_balances')->insert(['invoice_id' => $invoice->id, 'company_id' => $invoice->company_id, 'gross_value' => '1110000.00', 'sales_value' => '1000000.00', 'output_tax_value' => '110000.00']);

    // LK-25 the invoice, then LK-26 the source.
    $outcomes = envelopeRace($this->owner, [
        ['table' => 'invoice_balances', 'key' => ['invoice_id' => $invoice->id], 'deltas' => ['applied_amount' => '700000.00']],
        ['table' => 'application_source_balances', 'key' => ['payment_id' => $payment->id], 'deltas' => ['invoice_applied_amount' => '700000.00']],
    ], 'payment.apply');

    expect($outcomes['first'])->toBe('COMMITTED')
        ->and($outcomes['second'])->toBeIn(['REJECTED C-13', 'REJECTED C-15'])
        ->and((string) DB::table('application_source_balances')->where('payment_id', $payment->id)->value('invoice_applied_amount'))->toBe('700000.00');
});

it('races a receipt with its line\'s remainder closure: one commits, the line\'s cap intact (C-29; 2a)', function () {
    $line = PurchaseLine::factory()->create()->id;
    DB::table('purchase_line_balances')->insert(['purchase_line_id' => $line, 'ordered_qty_base' => 1]);
    $key = ['purchase_line_id' => $line];
    $owner = $this->owner;

    $outcomes = Sessions::run([
        'receipt' => static fn (): string => TestCommands::text(TestCommands::move((string) Str::uuid7(), $owner, null, 'stock.receive', [['table' => 'purchase_line_balances', 'key' => $key, 'deltas' => ['normal_received_net_qty_base' => 1]]], static function (): void {
            Barrier::await('held');
            Sessions::awaitBlocked();
        })),
        'closure' => static function () use ($owner, $key): string {
            Barrier::await('held');

            return TestCommands::text(TestCommands::move((string) Str::uuid7(), $owner, null, 'purchase.close_remainder', [['table' => 'purchase_line_balances', 'key' => $key, 'deltas' => ['closed_qty_base' => 1]]]));
        },
    ]);

    expect($outcomes)->toBe(['receipt' => 'COMMITTED', 'closure' => 'REJECTED C-29']);
});

it('cites one bank line twice at once within its amount (C-18; C-19; 6c)', function () {
    $line = BankStatementLine::factory()->create()->id;
    DB::table('evidence_balances')->insert(['bank_statement_line_id' => $line, 'proven_amount' => '100000.00']);

    $outcomes = envelopeRace($this->owner, [['table' => 'evidence_balances', 'key' => ['bank_statement_line_id' => $line], 'deltas' => ['cited_amount' => '60000.00']]], 'cashin.record');

    expect($outcomes)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED C-18']);
});

it('draws one lot\'s cost twice at once exactly once per unit (C-26; 18a)', function () {
    $lot = InventoryLot::factory()->create()->id;
    DB::table('lot_cost_balances')->insert(['lot_id' => $lot, 'remaining_qty_base' => 1, 'remaining_cost' => '150000.00']);

    $outcomes = envelopeRace($this->owner, [['table' => 'lot_cost_balances', 'key' => ['lot_id' => $lot], 'deltas' => ['remaining_qty_base' => -1, 'remaining_cost' => '-150000.00']]], 'stock.dispatch');

    expect($outcomes)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED C-26'])
        ->and((string) DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe('0.00');
});

it('runs a duplicate of one command identity in flight once: it waits on LK-00 and replays (CI-04; 15c, 19a)', function () {
    $product = Product::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $product, 'on_hand_qty' => 2]);

    $outcomes = envelopeRace($this->owner, [['table' => 'stock_product_balances', 'key' => ['product_id' => $product], 'deltas' => ['on_hand_qty' => -1]]], 'stock.dispatch', (string) Str::uuid7());

    expect($outcomes)->toBe(['first' => 'COMMITTED', 'second' => 'COMMITTED'])
        ->and((string) DB::table('stock_product_balances')->where('product_id', $product)->value('on_hand_qty'))->toBe('1.000')
        ->and(DB::table('command_log')->count())->toBe(1);
});

it('refuses a command whose grant is revoked while it waits on the actor\'s row: re-authorized in the command, nothing recorded (SQ-22; RV-; 8a)', function () {
    $company = Company::factory()->create();
    $admin = Authorization::admin([$company->id], ['stock.view', 'projects.view', 'stock.dispatch'])->id;
    $companyId = $company->id;
    $capability = DB::table('capabilities')->where('code', 'stock.dispatch')->value('id');
    $product = Product::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $product, 'on_hand_qty' => 1]);
    $id = (string) Str::uuid7();

    $outcomes = Sessions::run([
        // The access change holds the actor's row (LK-03) until the command waits on it, then commits the revocation.
        'revocation' => static function () use ($admin, $capability): string {
            DB::transaction(function () use ($admin, $capability): void {
                DB::table('users')->where('id', $admin)->lockForUpdate()->first();
                DB::table('user_capability_grants')->where('user_id', $admin)->where('capability_id', $capability)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                Barrier::await('the revocation holds the actor');
                Sessions::awaitBlocked();
            });

            return 'COMMITTED';
        },
        'command' => static function () use ($admin, $companyId, $product, $id): string {
            Barrier::await('the revocation holds the actor');

            try {
                return TestCommands::text(TestCommands::move($id, $admin, $companyId, 'stock.dispatch', [['table' => 'stock_product_balances', 'key' => ['product_id' => $product], 'deltas' => ['on_hand_qty' => -1]]]));
            } catch (AuthorizationException) {
                return 'DENIED';
            }
        },
    ]);

    expect($outcomes)->toBe(['revocation' => 'COMMITTED', 'command' => 'DENIED'])
        ->and((string) DB::table('stock_product_balances')->where('product_id', $product)->value('on_hand_qty'))->toBe('1.000')
        ->and(DB::table('command_log')->where('command_id', $id)->exists())->toBeFalse();
});

it('stamps a command once its locks are granted: one that waited is stamped after the one that committed before it (phase C; §9)', function () {
    $product = Product::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $product, 'on_hand_qty' => 2]);
    // The earlier command's actor is held by an access change, so a later command takes the product first.
    $waiting = Authorization::owner()->id;
    $later = $this->owner;
    $laterId = (string) Str::uuid7();

    $stamps = Sessions::run([
        'access change' => static function () use ($waiting, $laterId): string {
            DB::transaction(function () use ($waiting, $laterId): void {
                DB::table('users')->where('id', $waiting)->lockForUpdate()->first();
                Barrier::await('the actor is held');
                Sessions::awaitBlocked();

                for ($deadline = hrtime(true) + 30_000_000_000; ! DB::table('command_log')->where('command_id', $laterId)->where('state', 'COMMITTED')->exists(); usleep(10_000)) {
                    if (hrtime(true) > $deadline) {
                        throw new RuntimeException('The later command never committed.');
                    }
                }
            });

            return 'COMMITTED';
        },
        'earlier' => static function () use ($waiting, $product): string {
            Barrier::await('the actor is held');

            return TestCommands::stamped((string) Str::uuid7(), $waiting, $product);
        },
        'later' => static function () use ($later, $laterId, $product): string {
            Barrier::await('the actor is held');
            Sessions::awaitBlocked();

            return TestCommands::stamped($laterId, $later, $product);
        },
    ]);

    expect($stamps['access change'])->toBe('COMMITTED')
        ->and($stamps['later'])->not->toBe('')
        ->and(strcmp($stamps['earlier'], $stamps['later']))->toBeGreaterThan(0)
        ->and((string) DB::table('stock_product_balances')->where('product_id', $product)->value('on_hand_qty'))->toBe('0.000');
});
