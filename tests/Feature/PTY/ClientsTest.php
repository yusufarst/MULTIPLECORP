<?php

use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Models\Company;
use App\Modules\PTY\Models\ClientAddress;
use App\Modules\PTY\Models\ClientContact;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use App\Modules\PTY\Queries\PartyLookup;
use App\Support\Command\Outcome;
use App\Support\RowHandle;
use App\Support\Search\Term;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-026; ROUTE-054, ROUTE-141, ROUTE-142; criteria 1, 2, 4, 7): PG-054 search-first — a creation whose name
// an active organization equals or begins with answers with that list, and the second submission naming each with its
// reason creates it —; UNY, UGM and any other client through the same organization → unit → PIC flow, the first unit,
// its address and a PIC in the creation's one command; a unit's name its organization holds refused at its field;
// edits at the version read, a stale one CONFLICT; an archive that keeps the row and is no longer offered.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'parties.manage']);
});

afterEach(fn () => @unlink($this->hotFile));

function partyAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** ROUTE-141's body for an organization, with its first unit, address and PIC when given. */
function newClient(string $name, array $more = []): array
{
    return ['command_id' => (string) Str::uuid(), 'kind' => 'organization', 'name' => $name, ...$more];
}

it('creates UNY, UGM and another client through the one organization → unit → PIC flow (criterion 2; AC-02; REF-009)', function () {
    foreach (['Universitas Negeri Yogyakarta', 'Universitas Gadjah Mada', 'SMA Negeri 1 Sleman'] as $index => $name) {
        $this->actingAs($this->admin)->post('/data-master/klien', newClient($name, [
            'npwp' => null, 'unit_name' => "Fakultas  Teknik {$index}", 'address_label' => 'Kampus', 'address_lines' => "Jl. Uji No. {$index}", 'city' => 'Yogyakarta',
            'contact_name' => 'Bu Laras', 'contact_position' => 'Bendahara', 'contact_phone' => '0812 3456 789', 'contact_email' => 'laras@example.test',
        ]))->assertRedirect();
        expect(partyAnswer()['kind'])->toBe(Outcome::COMMITTED);
    }

    $uny = ClientOrganization::query()->where('name', 'Universitas Negeri Yogyakarta')->sole();
    $unit = ClientUnit::query()->where('client_organization_id', $uny->id)->sole();
    expect(ClientOrganization::query()->count())->toBe(3)
        ->and($unit->name)->toBe('Fakultas Teknik 0')
        ->and(ClientAddress::query()->where('client_unit_id', $unit->id)->sole()->address_lines)->toBe('Jl. Uji No. 0')
        ->and(ClientContact::query()->where('client_unit_id', $unit->id)->sole()->name)->toBe('Bu Laras')
        ->and(DB::table('audit_events')->where('action', 'PTY.ClientOrganizationCreated')->pluck('authority_class')->unique()->all())->toBe(['ADM'])
        ->and(DB::table('audit_events')->where('action', 'PTY.ClientOrganizationCreated')->whereNotNull('company_id')->exists())->toBeFalse();

    $this->actingAs($this->admin)->get("/data-master/klien?klien={$uny->public_id}")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('PTY/Clients', false)->where('client.name', 'Universitas Negeri Yogyakarta')
            ->where('client.units.0.name', 'Fakultas Teknik 0')->where('client.units.0.addresses.0.city', 'Yogyakarta')->where('client.units.0.contacts.0.position', 'Bendahara')
            ->where('can.manage', true));
});

it('answers a creation whose name an organization equals or begins with by that list, then creates it with the reason (criterion 1; PF-21; IP-05)', function () {
    $equal = ClientOrganization::factory()->create(['name' => 'SMAN 1 Yogyakarta']);
    $longer = ClientOrganization::factory()->create(['name' => 'sman 1 yogyakarta selatan']);
    ClientOrganization::factory()->create(['name' => 'SMAN 1 Yogyakarta Lama', 'is_active' => false, 'deactivated_at' => now()]);
    ClientOrganization::factory()->create(['name' => 'SMA Negeri 2 Yogyakarta']);

    $this->actingAs($this->admin)->post('/data-master/klien', newClient('SMAN 1  Yogyakarta'))->assertRedirect();
    $answer = partyAnswer();
    expect($answer['kind'])->toBe(Outcome::REJECTED)->and($answer['code'])->toBe('PF-21')
        ->and(array_column($answer['state']['matches'], 'id'))->toEqualCanonicalizing([$equal->public_id, $longer->public_id])
        ->and($answer['text'])->toContain('SMAN 1 Yogyakarta');

    $this->actingAs($this->admin)->post('/data-master/klien', newClient('SMAN 1 Yogyakarta', ['acknowledged' => [$equal->public_id], 'reason' => 'Sekolah lain']))->assertRedirect();
    expect(partyAnswer()['kind'])->toBe(Outcome::REJECTED);

    $this->actingAs($this->admin)->post('/data-master/klien', newClient('SMAN 1 Yogyakarta', ['acknowledged' => [$equal->public_id, $longer->public_id], 'reason' => 'Sekolah di kota lain']))->assertRedirect();
    expect(partyAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(ClientOrganization::query()->whereRaw('lower(name) = ?', ['sman 1 yogyakarta'])->count())->toBe(2)
        ->and(DB::table('audit_events')->where('action', 'PTY.ClientOrganizationCreated')->value('reason'))->toBe('Sekolah di kota lain');
});

it('lists the equal and prefix names first, then the ranked ones, in the search-first lookup (criterion 1; PF-21; FX-07)', function () {
    foreach (['SMA Negeri 1 Sleman', 'SMAN 1', 'Yayasan SMAN 1 Alumni', 'SMAN 10 Yogyakarta', 'SMAN 1 Depok', 'UGM'] as $name) {
        ClientOrganization::factory()->create(['name' => $name]);
    }

    $found = app(PartyLookup::class)->clients(new Term('sman 1'), 20);
    $labels = array_column($found['rows'], 'label');

    expect($labels[0])->toBe('SMAN 1')
        ->and(array_slice($labels, 1, 2))->toEqualCanonicalizing(['SMAN 1 Depok', 'SMAN 10 Yogyakarta'])
        ->and(array_search('Yayasan SMAN 1 Alumni', $labels, true))->toBeGreaterThan(2)
        ->and($labels)->not->toContain('UGM');
});

it('adds a unit with its address, an address and a PIC, and refuses a unit name the organization holds at its field (criterion 7; BD-16)', function () {
    $organization = ClientOrganization::factory()->create();
    $body = ['kind' => 'unit', 'klien' => $organization->public_id];

    $this->actingAs($this->admin)->post('/data-master/klien', [...$body, 'command_id' => (string) Str::uuid(), 'unit_name' => 'Tata Usaha', 'address_lines' => 'Jl. Kaliurang 1'])->assertRedirect();
    $unit = ClientUnit::query()->where('client_organization_id', $organization->id)->sole();
    $handle = RowHandle::of('client_units', $unit->id);

    $this->actingAs($this->admin)->post('/data-master/klien', [...$body, 'command_id' => (string) Str::uuid(), 'unit_name' => 'TATA  usaha'])->assertSessionHasErrors('unit_name');
    $this->actingAs($this->admin)->post('/data-master/klien', ['command_id' => (string) Str::uuid(), 'kind' => 'address', 'klien' => $organization->public_id, 'unit' => $handle, 'address_lines' => 'Gedung B'])->assertRedirect();
    $this->actingAs($this->admin)->post('/data-master/klien', ['command_id' => (string) Str::uuid(), 'kind' => 'contact', 'klien' => $organization->public_id, 'unit' => $handle, 'contact_name' => 'Pak Budi'])->assertRedirect();
    $this->actingAs($this->admin)->post('/data-master/klien', ['command_id' => (string) Str::uuid(), 'kind' => 'contact', 'klien' => $organization->public_id, 'unit' => 'f'.substr($handle, 1)])->assertSessionHasErrors('contact_name');
    $this->actingAs($this->admin)->post('/data-master/klien', ['command_id' => (string) Str::uuid(), 'kind' => 'contact', 'klien' => $organization->public_id, 'unit' => str_repeat('a', 32), 'contact_name' => 'X'])->assertNotFound();

    expect(ClientUnit::query()->where('client_organization_id', $organization->id)->count())->toBe(1)
        ->and(ClientAddress::query()->where('client_unit_id', $unit->id)->count())->toBe(2)
        ->and(ClientContact::query()->where('client_unit_id', $unit->id)->value('name'))->toBe('Pak Budi');
});

it('edits each client row at the version read, a stale edit answering CONFLICT (ROUTE-142; ST-01)', function () {
    $organization = ClientOrganization::factory()->create(['name' => 'Dinas Pendidikan'])->refresh();
    $unit = ClientUnit::factory()->for($organization, 'organization')->create(['name' => 'Bidang SD'])->refresh();
    ClientUnit::factory()->for($organization, 'organization')->create(['name' => 'Bidang SMP']);
    $edit = fn (array $body) => $this->actingAs($this->admin)->put("/data-master/klien/{$organization->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'change', ...$body]);

    $edit(['kind' => 'organization', 'lock_version' => 0, 'name' => 'Dinas Pendidikan Kota', 'npwp' => '01.234.567.8-901.000'])->assertRedirect();
    expect(partyAnswer()['kind'])->toBe(Outcome::COMMITTED)->and($organization->refresh()->name)->toBe('Dinas Pendidikan Kota');

    $edit(['kind' => 'organization', 'lock_version' => 0, 'name' => 'Nama Lama'])->assertRedirect();
    expect(partyAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'cause' => 'stale'])->and($organization->refresh()->name)->toBe('Dinas Pendidikan Kota');

    $edit(['kind' => 'unit', 'row' => RowHandle::of('client_units', $unit->id), 'lock_version' => 0, 'unit_name' => 'bidang smp'])->assertSessionHasErrors('unit_name');
    $edit(['kind' => 'unit', 'row' => RowHandle::of('client_units', $unit->id), 'lock_version' => 0, 'unit_name' => 'Bidang Sekolah Dasar'])->assertRedirect();
    expect($unit->refresh()->name)->toBe('Bidang Sekolah Dasar')
        ->and(DB::table('audit_events')->where('action', 'PTY.ClientUpdated')->where('entity_type', 'client_unit')->value('after'))->toContain('Bidang Sekolah Dasar');

    $other = ClientUnit::factory()->create();
    $edit(['kind' => 'unit', 'row' => RowHandle::of('client_units', $other->id), 'lock_version' => 0, 'unit_name' => 'Bukan miliknya'])->assertNotFound();
});

it('keeps an issued document\'s client as issued while a later draft reads the changed unit, address and PIC (criterion 3; BR-SN-01; C-11)', function () {
    $organization = ClientOrganization::factory()->create(['name' => 'Sekolah Snapshot'])->refresh();
    $unit = ClientUnit::factory()->for($organization, 'organization')->create(['name' => 'Unit Lama'])->refresh();
    $address = ClientAddress::factory()->for($unit, 'unit')->create(['address_lines' => 'Jl. Lama 1'])->refresh();
    $contact = ClientContact::factory()->for($unit, 'unit')->create(['name' => 'PIC Lama'])->refresh();
    $snapshot = ['klien' => 'Sekolah Snapshot', 'unit' => 'Unit Lama', 'alamat' => 'Jl. Lama 1', 'pic' => 'PIC Lama'];
    $issued = DocumentVersion::factory()->issued()
        ->for(Document::factory()->state(['company_id' => $this->company->id]), 'document')
        ->create(['payload' => $snapshot, 'payload_sha256' => hash('sha256', (string) json_encode($snapshot))]);
    $payload = DB::table('document_versions')->where('id', $issued->id)->value('payload');
    $edit = fn (string $kind, string $row, int $version, array $fields) => $this->actingAs($this->admin)->put("/data-master/klien/{$organization->public_id}",
        ['command_id' => (string) Str::uuid(), 'action' => 'change', 'kind' => $kind, 'row' => $row, 'lock_version' => $version, ...$fields])->assertRedirect();

    $edit('unit', RowHandle::of('client_units', $unit->id), $unit->lock_version, ['unit_name' => 'Unit Baru']);
    $edit('address', RowHandle::of('client_addresses', $address->id), $address->lock_version, ['address_lines' => 'Jl. Baru 2']);
    $edit('contact', RowHandle::of('client_contacts', $contact->id), $contact->lock_version, ['contact_name' => 'PIC Baru']);
    $choices = app(PartyLookup::class)->choices(app(EffectiveCapabilities::class)->fresh($this->admin->id), $organization->public_id);

    expect(DB::table('document_versions')->where('id', $issued->id)->value('payload'))->toBe($payload)
        ->and($choices[0]['label'])->toBe('Unit Baru')
        ->and($choices[0]['addresses'][0]['label'])->toBe('Jl. Baru 2')
        ->and($choices[0]['contacts'][0]['label'])->toBe('PIC Baru');
});

it('archives an organization or a unit, keeping it and its history, no longer offered for a new project (criterion 4; WF-MD-02)', function () {
    $organization = ClientOrganization::factory()->create(['name' => 'Arsip Sekolah Uji'])->refresh();
    $kept = ClientUnit::factory()->for($organization, 'organization')->create(['name' => 'Unit Tetap']);
    $unit = ClientUnit::factory()->for($organization, 'organization')->create(['name' => 'Unit Lama'])->refresh();
    $viewer = app(EffectiveCapabilities::class)->fresh($this->admin->id);
    $archive = fn (array $body) => $this->actingAs($this->admin)->put("/data-master/klien/{$organization->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'archive', 'reason' => 'Tidak aktif', ...$body]);

    $archive(['kind' => 'unit', 'row' => RowHandle::of('client_units', $unit->id), 'lock_version' => $unit->lock_version])->assertRedirect();
    expect(partyAnswer()['kind'])->toBe(Outcome::COMMITTED)->and($unit->refresh()->is_active)->toBeFalse()
        ->and(array_column(app(PartyLookup::class)->choices($viewer, $organization->public_id), 'label'))->toBe(['Unit Tetap']);

    $this->actingAs($this->admin)->post('/data-master/klien', ['command_id' => (string) Str::uuid(), 'kind' => 'address', 'klien' => $organization->public_id,
        'unit' => RowHandle::of('client_units', $unit->id), 'address_lines' => 'Jl. Baru'])->assertRedirect();
    expect(partyAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'WF-MD-02']);

    $archive(['kind' => 'organization', 'lock_version' => $organization->lock_version])->assertRedirect();
    expect($organization->refresh()->is_active)->toBeFalse()->and(ClientOrganization::query()->whereKey($organization->id)->exists())->toBeTrue()
        ->and(app(PartyLookup::class)->clients(new Term('Arsip Sekolah'), 20)['rows'])->toBe([])
        ->and(app(PartyLookup::class)->choices($viewer, $organization->public_id))->toBe([])
        ->and($kept->refresh()->is_active)->toBeTrue();

    $archive(['kind' => 'organization', 'lock_version' => $organization->lock_version])->assertRedirect();
    expect(partyAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'WF-MD-02']);
});

it('validates each field at its field, the input named only (IP-14; WS-01)', function () {
    $this->actingAs($this->admin)->post('/data-master/klien', ['command_id' => (string) Str::uuid(), 'kind' => 'organization', 'name' => '', 'npwp' => 'abc'])
        ->assertSessionHasErrors(['name', 'npwp']);
    $this->actingAs($this->admin)->post('/data-master/klien', ['command_id' => 'x', 'kind' => 'other'])->assertSessionHasErrors(['command_id', 'kind']);
    $this->actingAs($this->admin)->post('/data-master/klien', newClient('Sekolah Uji', ['unit_name' => 'Unit', 'contact_email' => 'bukan-email']))->assertSessionHasErrors('contact_email');

    expect(ClientOrganization::query()->count())->toBe(0);
});
