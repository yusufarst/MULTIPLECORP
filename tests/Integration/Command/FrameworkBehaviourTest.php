<?php

use App\Modules\CAT\Models\Product;
use App\Modules\FIN\Models\Invoice;
use App\Support\Command\LockClass;
use App\Support\Command\LockSet;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Commands\TestCommands;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-013): the framework behaviours the envelope relies on, recorded as source-only (TEST_STRATEGY §6
// HO-20) — the concurrency-error detector, savepoint nesting and the raw lock string —, each failing on a
// framework version that changes it; and HO-13: the lock order measured from the row locks the command actually
// holds, read with pgrowlocks after each of its statements, in ascending canonical order.

uses(RefreshDatabase::class);

it('detects serialization failures and deadlocks as concurrency errors (HO-20)', function () {
    $detector = new class
    {
        use DetectsConcurrencyErrors;

        public function detects(Throwable $error): bool
        {
            return $this->causedByConcurrencyError($error);
        }
    };
    $error = function (string $state, string $message): PDOException {
        $exception = new PDOException($message);
        (new ReflectionProperty(Exception::class, 'code'))->setValue($exception, $state);

        return $exception;
    };

    // A serialization failure by its code, a deadlock only by the server's message; a lock timeout is neither.
    // The envelope's own mapper retries both by their codes (FailureMapper; SQ-12, SQ-13).
    expect($detector->detects($error('40001', 'could not serialize access due to concurrent update')))->toBeTrue()
        ->and($detector->detects($error('40P01', 'ERROR:  deadlock detected')))->toBeTrue()
        ->and($detector->detects($error('40P01', 'ERROR:  x')))->toBeFalse()
        ->and($detector->detects($error('55P03', 'canceling statement due to lock timeout')))->toBeFalse();
});

it('nests a transaction as a savepoint whose rollback keeps the outer one (HO-20)', function () {
    $product = Product::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $product, 'on_hand_qty' => 5]);
    DB::enableQueryLog();
    DB::flushQueryLog();

    DB::beginTransaction();
    DB::table('stock_product_balances')->where('product_id', $product)->update(['on_hand_qty' => 4]);
    DB::beginTransaction();
    DB::table('stock_product_balances')->where('product_id', $product)->update(['on_hand_qty' => 1]);
    DB::rollBack();
    DB::commit();

    expect((string) DB::table('stock_product_balances')->where('product_id', $product)->value('on_hand_qty'))->toBe('4.000');
});

it('writes the raw lock string as given (HO-20; LR-03)', function () {
    expect(DB::table('users')->where('id', 1)->lock('for no key update')->toSql())->toEndWith('for no key update');
});

it('holds the command\'s row locks in ascending canonical order, implicit ones included (HO-13; LR-01, LR-05)', function () {
    Authorization::catalogue();
    $owner = Authorization::owner()->id;
    [$a, $b] = [Product::factory()->create()->id, Product::factory()->create()->id];
    DB::table('stock_product_balances')->insert([['product_id' => $a, 'on_hand_qty' => 2], ['product_id' => $b, 'on_hand_qty' => 2]]);
    $invoice = Invoice::factory()->create();
    DB::table('invoice_balances')->insert(['invoice_id' => $invoice->id, 'company_id' => $invoice->company_id, 'gross_value' => '100.00', 'sales_value' => '100.00', 'output_tax_value' => '0.00']);

    $tables = ['users', 'stock_product_balances', 'invoice_balances'];
    $seen = [];
    $order = [];
    $observe = function () use ($tables, &$seen, &$order): void {
        foreach ($tables as $table) {
            foreach (DB::select("select locked_row::text as ctid from pgrowlocks('{$table}')") as $lock) {
                // An update's new row version is locked too, under a new ctid: a row is known by its key.
                $key = (int) DB::table($table)->whereRaw('ctid = cast(? as tid)', [$lock->ctid])->value(match ($table) {
                    'users' => 'id', 'stock_product_balances' => 'product_id', default => 'invoice_id',
                });

                if ($key !== 0 && ! isset($seen["{$table}#{$key}"])) {
                    $seen["{$table}#{$key}"] = true;
                    $order[] = [LockClass::of($table)->value, $key, $table];
                }
            }
        }
    };

    // The moves are given out of order on purpose: the envelope sorts them.
    $outcome = TestCommands::move((string) Str::uuid7(), $owner, null, 'stock.dispatch', [
        ['table' => 'invoice_balances', 'key' => ['invoice_id' => $invoice->id], 'deltas' => ['applied_amount' => '10.00']],
        ['table' => 'stock_product_balances', 'key' => ['product_id' => max($a, $b)], 'deltas' => ['on_hand_qty' => -1]],
        ['table' => 'stock_product_balances', 'key' => ['product_id' => min($a, $b)], 'deltas' => ['on_hand_qty' => -1]],
    ], observer: $observe);

    $sorted = $order;
    usort($sorted, fn (array $x, array $y): int => [$x[0], $x[1]] <=> [$y[0], $y[1]]);

    expect($outcome->kind)->toBe('COMMITTED')
        ->and(array_column($order, 2))->toBe(['users', 'stock_product_balances', 'stock_product_balances', 'invoice_balances'])
        ->and($order)->toBe($sorted);
});

it('keeps a lock set\'s statements single-row, so no multi-row plan needs verifying (LR-05)', function () {
    $sql = DB::table('stock_product_balances')->where('product_id', 1)->lock('for no key update')->toSql();

    expect($sql)->toContain('where "product_id" = ?')->not->toContain(' in (')
        ->and((new LockSet)->add('stock_product_balances', ['product_id' => 1])->ordered())->toHaveCount(1);
});
