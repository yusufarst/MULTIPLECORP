<?php

use App\Modules\ORG\Models\Company;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\Supplier;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-026; IG-01–IG-05; INT-141–INT-144; CI-05; IP-19): each control's server side — present with
// `parties.manage` and absent without it; its success, and one `command_id` replayed answering the recorded outcome
// with nothing written twice; the same identifier with other content a CONFLICT —; an inline creation answering the
// form it came from with the new values chosen.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'parties.manage']);
    $this->client = ClientOrganization::factory()->create()->refresh();
    $this->supplier = Supplier::factory()->create()->refresh();
});

afterEach(fn () => @unlink($this->hotFile));

function partiesAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** Each INT row's request: its method, path and payload. */
function partiesInteraction(object $test, string $row): array
{
    return match ($row) {
        'INT-141' => ['POST', '/data-master/klien', ['kind' => 'organization', 'name' => 'Klien Interaksi', 'unit_name' => 'Unit Satu', 'address_lines' => 'Jl. Satu']],
        'INT-142' => ['PUT', "/data-master/klien/{$test->client->public_id}", ['action' => 'change', 'kind' => 'organization', 'lock_version' => 0, 'name' => 'Klien Diubah']],
        'INT-143' => ['POST', '/data-master/pemasok', ['name' => 'Pemasok Interaksi']],
        default => ['PUT', "/data-master/pemasok/{$test->supplier->public_id}", ['action' => 'archive', 'lock_version' => 0, 'reason' => 'Tutup']],
    };
}

it('commits each command once, and its command_id replayed answers the recorded outcome writing nothing (CI-05; IG-05)', function (string $row) {
    [$method, $path, $payload] = partiesInteraction($this, $row);
    $payload = [...$payload, 'command_id' => (string) Str::uuid()];
    $counts = fn (): array => [DB::table('client_organizations')->count(), DB::table('client_units')->count(), DB::table('client_addresses')->count(),
        DB::table('suppliers')->count(), DB::table('audit_events')->count()];

    $this->actingAs($this->admin)->call($method, $path, $payload)->assertRedirect();
    expect(partiesAnswer()['kind'] ?? null)->toBe(Outcome::COMMITTED);
    $before = $counts();
    $this->actingAs($this->admin)->get('/data-master/klien')->assertOk();

    $this->actingAs($this->admin)->call($method, $path, $payload)->assertRedirect();
    expect(partiesAnswer()['kind'] ?? null)->toBe(Outcome::COMMITTED)->and($counts())->toBe($before);

    $this->actingAs($this->admin)->call($method, $path, [...$payload, 'name' => 'Isi Lain', 'reason' => 'Alasan lain'])->assertRedirect();
    expect(partiesAnswer()['kind'] ?? null)->toBe(Outcome::CONFLICT);
})->with(['INT-141', 'INT-142', 'INT-143', 'INT-144']);

it('shows the controls with parties.manage and not without it (IG-01; AZ-01 as a hint)', function () {
    $viewer = Authorization::admin([$this->company->id], ['projects.view']);

    $this->actingAs($this->admin)->get('/data-master/klien')->assertInertia(fn (AssertableInertia $page) => $page->where('can.manage', true));
    $this->actingAs($viewer)->get('/data-master/klien')->assertInertia(fn (AssertableInertia $page) => $page->where('can.manage', false));
    $this->actingAs($viewer)->get('/data-master/pemasok')->assertInertia(fn (AssertableInertia $page) => $page->where('can.manage', false));
});

it('answers an inline creation on the form it came from with the new values chosen (criterion 8; ROUTE-141; ROUTE-143; IP-19)', function () {
    $form = '/__fixtures/formulir-pihak';

    $this->actingAs($this->admin)->from($form)->post('/data-master/klien', ['command_id' => (string) Str::uuid(), 'kind' => 'organization', 'inline' => true,
        'name' => 'Klien Sebaris', 'unit_name' => 'Unit Sebaris', 'address_label' => 'Gedung A', 'address_lines' => 'Jl. Sebaris', 'contact_name' => 'Bu Sebaris'])->assertRedirect($form);
    $created = partiesAnswer()['state']['created'];
    expect($created['organization']['name'])->toBe('Klien Sebaris')->and($created['unit']['label'])->toBe('Unit Sebaris')
        ->and($created['address']['label'])->toBe('Gedung A')->and($created['contact']['label'])->toBe('Bu Sebaris');

    $this->actingAs($this->admin)->from($form)->post('/data-master/pemasok', ['command_id' => (string) Str::uuid(), 'inline' => true, 'name' => 'Pemasok Sebaris'])->assertRedirect($form);
    expect(partiesAnswer()['state']['created']['supplier']['name'])->toBe('Pemasok Sebaris');

    $this->actingAs($this->admin)->from('https://elsewhere.example/form')->post('/data-master/pemasok', ['command_id' => (string) Str::uuid(), 'inline' => true, 'name' => 'Pemasok Luar'])
        ->assertRedirect('/data-master/pemasok');
});
