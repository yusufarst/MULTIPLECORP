<?php

use App\Modules\CAT\Models\Product;
use App\Modules\ORG\Models\Company;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-027; IG-01–IG-05; INT-145–INT-147; CI-05): each control's server side — its success, and one
// `command_id` replayed answering the recorded outcome with nothing written twice; the same identifier with other
// content a CONFLICT.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->admin = Authorization::admin([$this->company->id], ['products.edit', 'cost.view']);
    $this->product = Product::factory()->create()->refresh();
});

afterEach(fn () => @unlink($this->hotFile));

function productsAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** Each INT row's request: its method, path and payload. */
function productsInteraction(object $test, string $row): array
{
    $id = $test->product->public_id;

    return match ($row) {
        'INT-145' => ['POST', '/data-master/produk', ['kind' => 'GOODS', 'name' => 'Pulpen', 'sku' => 'PLP-01', 'unit_code' => 'PCS', 'quantity_scale' => 0]],
        'INT-146 details' => ['PUT', "/data-master/produk/{$id}", ['action' => 'details', 'lock_version' => 0, 'name' => 'Nama Baru']],
        'INT-146 unit' => ['PUT', "/data-master/produk/{$id}", ['action' => 'unit', 'unit_code' => 'LUSIN', 'ratio' => 12]],
        'INT-146 price' => ['PUT', "/data-master/produk/{$id}", ['action' => 'price', 'price_kind' => 'PURCHASE_DEFAULT', 'amount' => '1000']],
        default => ['POST', "/data-master/produk/{$id}/barcode", ['kind' => 'MANUFACTURER', 'code' => '8990001112223']],
    };
}

it('commits each command once, and its command_id replayed answers the recorded outcome writing nothing (CI-05; IG-05)', function (string $row) {
    [$method, $path, $payload] = productsInteraction($this, $row);
    $payload = [...$payload, 'command_id' => (string) Str::uuid()];
    $counts = fn (): array => [DB::table('products')->count(), DB::table('product_units')->count(), DB::table('product_barcodes')->count(),
        DB::table('product_price_history')->count(), DB::table('audit_events')->count()];

    $this->actingAs($this->admin)->call($method, $path, $payload)->assertRedirect();
    expect(productsAnswer()['kind'] ?? null)->toBe(Outcome::COMMITTED);
    $before = $counts();
    $this->actingAs($this->admin)->get('/data-master/produk')->assertOk();

    $this->actingAs($this->admin)->call($method, $path, $payload)->assertRedirect();
    expect(productsAnswer()['kind'] ?? null)->toBe(Outcome::COMMITTED)->and($counts())->toBe($before);

    $this->actingAs($this->admin)->call($method, $path, [...$payload, 'name' => 'Isi Lain', 'amount' => '2000', 'ratio' => 24, 'code' => '8990001112230'])->assertRedirect();
    expect(productsAnswer()['kind'] ?? null)->toBe(Outcome::CONFLICT);
})->with(['INT-145', 'INT-146 details', 'INT-146 unit', 'INT-146 price', 'INT-147']);
