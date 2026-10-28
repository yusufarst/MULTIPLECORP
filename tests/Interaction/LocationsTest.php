<?php

use App\Modules\CAT\Models\Product;
use App\Modules\ORG\Models\Company;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-028; IG-01–IG-05; INT-148; CI-05): the control's server side — its success, and one `command_id` replayed
// answering the recorded outcome with nothing written twice; the same identifier with other content a CONFLICT.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->manager = Authorization::admin([Company::factory()->create()->id], ['stock.view', 'warehouse.manage']);
    $this->product = Product::factory()->create()->refresh();
});

afterEach(fn () => @unlink($this->hotFile));

function locationsAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('commits once, and its command_id replayed answers the recorded outcome writing nothing (CI-05; IG-05)', function (string $kind) {
    $payload = ['command_id' => (string) Str::uuid(), ...($kind === 'create'
        ? ['kind' => 'create', 'code' => 'INT-1', 'name' => 'Rak Interaksi']
        : ['kind' => 'minimum', 'product' => $this->product->public_id, 'minimum' => '6', 'lock_version' => 0])];
    $counts = fn (): array => [DB::table('locations')->count(), DB::table('audit_events')->count(), DB::table('products')->where('id', $this->product->id)->value('lock_version')];

    $this->actingAs($this->manager)->post('/data-master/lokasi-rak', $payload)->assertRedirect();
    expect(locationsAnswer()['kind'] ?? null)->toBe(Outcome::COMMITTED);
    $before = $counts();
    $this->actingAs($this->manager)->get('/data-master/lokasi-rak')->assertOk();

    $this->actingAs($this->manager)->post('/data-master/lokasi-rak', $payload)->assertRedirect();
    expect(locationsAnswer()['kind'] ?? null)->toBe(Outcome::COMMITTED)->and($counts())->toBe($before);

    $this->actingAs($this->manager)->post('/data-master/lokasi-rak', [...$payload, 'name' => 'Isi Lain', 'minimum' => '7'])->assertRedirect();
    expect(locationsAnswer()['kind'] ?? null)->toBe(Outcome::CONFLICT);
})->with(['create', 'minimum']);
