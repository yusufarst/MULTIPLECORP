<?php

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-032; ROUTE-010, ROUTE-011, ROUTE-013, ROUTE-151–ROUTE-153; WF-PRJ-01; criteria 1–7, 11, 12): a project
// created in the chosen company drawing that company's PROJECT number, ACTIVE once its parties are set; a period without
// numbering REJECTED with MSG-17, the Owner's path to start it; a reference outside the actor's scope answered as a
// nonexistent one; SIPLAH values stored as entered for a SIPLAH project only; the channel locked by the first
// channel-dependent commit; the company changed only in a DRAFT, as a new project and a retired number; commercial
// changes refused after confirmation; a DRAFT discarded with its number's tombstone; a stale version CONFLICT.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Projects::company();
    $this->client = Projects::client();
    $this->goods = Projects::product();
    $this->service = Projects::product('SERVICE', '250000.00');
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view']);
});

afterEach(fn () => @unlink($this->hotFile));

function projectAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** The message at a field after a refused request (IP-14). */
function fieldError(string $field): mixed
{
    $errors = session('errors');

    return $errors instanceof ViewErrorBag ? $errors->getBag('default')->get($field) : (is_array($errors) ? ($errors[$field] ?? null) : null);
}

function newest(): Project
{
    return Project::query()->orderByDesc('id')->firstOrFail();
}

it('creates the project in the chosen company with its number drawn there, ACTIVE once its parties are set (criterion 1; NM-08; SM:Project)', function () {
    $response = $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods, $this->service]));
    $project = newest();

    $response->assertRedirect("/proyek/{$project->public_id}");
    expect(projectAnswer()['kind'] ?? null)->toBe('COMMITTED')
        ->and($project->company_id)->toBe($this->company->id)
        ->and($project->project_number)->toBe('UJI/PRJ/'.substr(BusinessDate::today(), 0, 4).'/0001')
        ->and($project->state)->toBe('ACTIVE')
        ->and(DB::table('project_lines')->where('project_id', $project->id)->orderBy('line_no')->pluck('fulfillment_mode')->all())->toBe(['WAREHOUSE', 'SERVICE'])
        ->and(DB::table('project_lines')->where('project_id', $project->id)->value('unit_price'))->toBe('15000.00')
        ->and(DB::table('project_state_transitions')->where('project_id', $project->id)->sole()->transition_kind)->toBe('ACTIVATE')
        ->and(DB::table('audit_events')->where('entity_type', 'project')->where('entity_id', $project->id)->orderBy('id')->pluck('action')->all())
        ->toBe(['PRJ.ProjectCreated', 'PRJ.ProjectActivated']);
});

it('keeps a project without its unit or PIC a DRAFT, and activates it once they are set (SM:Project)', function () {
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['pic' => null]));
    $project = newest();
    expect($project->state)->toBe('DRAFT');

    $this->actingAs($this->admin)->put("/proyek/{$project->public_id}", Projects::payload($this->company, $this->client, [], [
        'lock_version' => $project->lock_version,
        'baris' => [['baris' => RowHandle::of('project_lines', (int) DB::table('project_lines')->where('project_id', $project->id)->value('id')), 'produk' => $this->goods['product']->public_id, 'satuan' => $this->goods['box']?->code, 'jumlah' => '2', 'harga' => '180000', 'mode' => 'DROPSHIP']],
    ]));

    expect(projectAnswer()['kind'] ?? null)->toBe('COMMITTED')
        ->and($project->fresh()?->state)->toBe('ACTIVE')
        ->and(DB::table('project_lines')->where('project_id', $project->id)->sole())
        ->toMatchObject(['entered_qty' => '2.000', 'ratio_to_base' => 12, 'qty_base' => '24.000', 'unit_price' => '180000.00', 'fulfillment_mode' => 'DROPSHIP']);
});

it('refuses a creation in a period whose numbering has not started with MSG-17 for an Admin, the Owner\'s path for the Owner, and creates once started (criterion 1; C-10; UXS-28)', function () {
    $fresh = Company::factory()->create(['legal_name' => 'PT Contoh Baru']);
    Authorization::grantCompany($this->admin, $fresh->id);
    $payload = Projects::payload($fresh, $this->client, [$this->goods]);

    $this->actingAs($this->admin)->from('/proyek/baru')->post('/proyek', $payload)->assertRedirect('/proyek/baru');
    expect(projectAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-10', 'link' => null])
        ->and(projectAnswer()['text'])->toContain('belum dimulai')->toContain('PT Contoh Baru')->toContain('Hubungi Owner');

    $this->actingAs(Authorization::owner())->post('/proyek', [...$payload, 'command_id' => (string) Str::uuid()]);
    expect(projectAnswer()['link'])->toStartWith('/pengaturan/penomoran?perusahaan='.$fresh->public_id);

    $year = substr(BusinessDate::today(), 0, 4);
    Numbering::scheme($fresh, SequenceType::Project, "{$year}-01-01", 'YEAR', 'BARU/PRJ/{tahun}/{nomor:4}');
    Numbering::started($fresh, SequenceType::Project, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
    $this->actingAs($this->admin)->post('/proyek', [...$payload, 'command_id' => (string) Str::uuid()]);

    expect(projectAnswer()['kind'] ?? null)->toBe('COMMITTED')->and(newest()->project_number)->toBe("BARU/PRJ/{$year}/0001");
});

it('answers a company, a client row or a linked project outside the actor\'s scope exactly like a nonexistent one (criterion 2; CS-04; XL-4)', function () {
    $other = Projects::company();
    $foreign = ProjectFactory::new()->createOne(['company_id' => $other->id]);
    $stranger = Projects::client();
    $cases = [
        'perusahaan' => [$other->public_id, (string) Str::uuid()],
        'tautan' => [$foreign->public_id, (string) Str::uuid()],
        'unit' => [RowHandle::of('client_units', $stranger['unit']->id), RowHandle::of('client_units', 999999)],
    ];

    foreach ($cases as $field => [$outside, $missing]) {
        $answers = [];

        foreach ([$outside, $missing] as $value) {
            $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], [$field => $value, 'alasan_tautan' => 'Pengadaan bersama']));
            $answers[] = fieldError($field);
        }

        expect($answers[0])->toBe($answers[1])->and($answers[0])->not->toBe([]);
    }

    expect(Project::query()->count())->toBe(1);
});

it('links a project of another company with its reason when both companies are in scope, never one of the same company (criterion 2; XL-4)', function () {
    $other = Projects::company();
    $partner = ProjectFactory::new()->createOne(['company_id' => $other->id]);
    // A project of an earlier period, so this year's first draw finds no number of its period (FU-07).
    $own = ProjectFactory::new()->createOne(['company_id' => $this->company->id, 'period_key' => '2019']);
    Authorization::grantCompany($this->admin, $other->id);

    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['tautan' => $own->public_id, 'alasan_tautan' => 'Proyek yang sama']))
        ->assertSessionHasErrors('tautan');
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['tautan' => $partner->public_id, 'alasan_tautan' => 'Pengadaan bersama dua sekolah']));

    expect(newest()->only(['linked_project_id', 'link_reason']))->toBe(['linked_project_id' => $partner->id, 'link_reason' => 'Pengadaan bersama dua sekolah']);
});

it('stores SIPLAH values as entered for a SIPLAH project only, its amounts only from an actor with finance.view (criteria 3, 12; BR-FIN-10)', function () {
    $siplah = ['external_reference' => 'SPL-2026-77', 'program_code' => 'BOS', 'account_code' => '5.2.2', 'notes' => 'Catatan', 'siplah_value' => '12500000', 'fee_amount' => '125000'];
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['kanal' => 'SIPLAH', 'siplah' => $siplah]));
    $first = newest();
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['siplah' => $siplah]));
    $regular = newest();
    $plain = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage']);
    $this->actingAs($plain)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['kanal' => 'SIPLAH', 'siplah' => $siplah]));
    $unpriced = newest();

    expect(DB::table('project_siplah_details')->where('project_id', $first->id)->first(['external_reference', 'siplah_value', 'fee_amount', 'refund_amount']))
        ->toEqual((object) ['external_reference' => 'SPL-2026-77', 'siplah_value' => '12500000.00', 'fee_amount' => '125000.00', 'refund_amount' => null])
        ->and(DB::table('project_siplah_details')->where('project_id', $regular->id)->exists())->toBeFalse()
        ->and(DB::table('project_siplah_details')->where('project_id', $unpriced->id)->first(['external_reference', 'siplah_value']))
        ->toEqual((object) ['external_reference' => 'SPL-2026-77', 'siplah_value' => null])
        ->and(DB::table('project_lines')->where('project_id', $unpriced->id)->value('unit_price'))->toBeNull();
});

it('changes the channel before the first channel-dependent commit and refuses it after, the trigger the backstop (criterion 4; BR-PRJ-05; C-51)', function () {
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['kanal' => 'SIPLAH', 'siplah' => ['external_reference' => 'SPL-1']]));
    $project = newest();
    $this->actingAs($this->admin)->put("/proyek/{$project->public_id}", Projects::payload($this->company, $this->client, [], ['lock_version' => $project->fresh()?->lock_version, 'baris' => []]));
    expect(projectAnswer()['kind'] ?? null)->toBe('COMMITTED')->and($project->fresh()?->channel)->toBe('REGULAR')
        ->and(DB::table('project_siplah_details')->where('project_id', $project->id)->value('external_reference'))->toBeNull();

    DB::table('project_siplah_details')->where('project_id', $project->id)->update(['locked_at' => now()]);
    $this->actingAs($this->admin)->put("/proyek/{$project->public_id}", Projects::payload($this->company, $this->client, [], ['lock_version' => $project->fresh()?->lock_version, 'baris' => [], 'kanal' => 'SIPLAH']));

    expect(projectAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'BR-PRJ-05'])->and($project->fresh()?->channel)->toBe('REGULAR')
        ->and(fn () => DB::table('project_siplah_details')->where('project_id', $project->id)->update(['notes' => 'x']))->toThrow(QueryException::class, 'project_siplah_details_c51_locked');
});

it('changes a DRAFT\'s company as a new project of that company, the draft\'s number retired, and refuses it for an ACTIVE project (criterion 4; L-21; DIR-090 (6))', function () {
    $other = Projects::company();
    Authorization::grantCompany($this->admin, $other->id);
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['pic' => null]));
    $draft = newest();
    $this->actingAs($this->admin)->put("/proyek/{$draft->public_id}", Projects::payload($other, $this->client, [$this->goods], ['lock_version' => $draft->lock_version, 'pic' => null]));
    $moved = newest();

    expect(projectAnswer()['kind'] ?? null)->toBe('COMMITTED')
        ->and(Project::query()->find($draft->id))->toBeNull()
        ->and($moved->company_id)->toBe($other->id)
        ->and(DB::table('retired_numbers')->sole())->toMatchObject(['company_id' => $this->company->id, 'formatted_number' => $draft->project_number, 'source_public_id' => $draft->public_id]);

    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods]));
    $active = newest();
    $this->actingAs($this->admin)->put("/proyek/{$active->public_id}", Projects::payload($other, $this->client, [$this->goods], ['lock_version' => $active->lock_version]));

    expect(projectAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'L-21'])->and($active->fresh()?->company_id)->toBe($this->company->id)
        ->and($active->project_number)->not->toBe($draft->project_number);
});

it('refuses commercial changes after confirmation with the path to a revision, keeping the other fields editable (criterion 5; L-20)', function () {
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods]));
    $project = newest();
    DB::table('commercial_confirmations')->insert(['project_id' => $project->id, 'company_id' => $this->company->id, 'kind' => 'NO_QUOTATION', 'business_date' => BusinessDate::today(),
        'decided_by' => $this->admin->id, 'reason' => 'SPK klien', 'recorded_at' => now(), 'recorded_by' => $this->admin->id]);
    $line = RowHandle::of('project_lines', (int) DB::table('project_lines')->where('project_id', $project->id)->value('id'));
    $kept = ['baris' => $line, 'produk' => $this->goods['product']->public_id, 'satuan' => $this->goods['base']->code, 'jumlah' => '10', 'harga' => '15000', 'mode' => 'WAREHOUSE'];

    $this->actingAs($this->admin)->put("/proyek/{$project->public_id}", Projects::payload($this->company, $this->client, [], ['lock_version' => $project->fresh()?->lock_version, 'baris' => [[...$kept, 'jumlah' => '12']]]));
    expect(projectAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'L-20'])->and(projectAnswer()['text'])->toContain('revisi penawaran');

    $this->actingAs($this->admin)->put("/proyek/{$project->public_id}", Projects::payload($this->company, $this->client, [], ['lock_version' => $project->fresh()?->lock_version, 'baris' => [$kept], 'judul' => 'Judul baru']));
    expect(projectAnswer()['kind'] ?? null)->toBe('COMMITTED')->and($project->fresh()?->title)->toBe('Judul baru');
});

it('discards a DRAFT with its number kept as a tombstone and never drawn again, and refuses an ACTIVE project or one a committed record names (criterion 6; L-37; C-10)', function () {
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['pic' => null]));
    $draft = newest();
    $this->actingAs($this->admin)->post("/proyek/{$draft->public_id}/hapus-draf", ['command_id' => (string) Str::uuid()])->assertRedirect('/proyek');

    expect(projectAnswer()['kind'] ?? null)->toBe('COMMITTED')
        ->and(Project::query()->find($draft->id))->toBeNull()
        ->and(DB::table('project_lines')->where('project_id', $draft->id)->exists())->toBeFalse()
        ->and(DB::table('retired_numbers')->sole())->toMatchObject(['formatted_number' => $draft->project_number, 'retired_kind' => 'DISCARDED_DRAFT_PROJECT'])
        ->and(DB::table('audit_events')->where('action', 'PRJ.DraftProjectDiscarded')->sole()->entity_public_id)->toBe($draft->public_id);

    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['pic' => null]));
    $next = newest();
    expect($next->sequence_no)->toBe($draft->sequence_no + 1);

    DB::table('quotation_revisions')->insert(['project_id' => $next->id, 'company_id' => $this->company->id, 'revision_no' => 1, 'state' => 'DRAFT', 'recorded_at' => now(), 'recorded_by' => $this->admin->id]);
    $this->actingAs($this->admin)->post("/proyek/{$next->public_id}/hapus-draf", ['command_id' => (string) Str::uuid()]);
    expect(projectAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'L-37'])->and(projectAnswer()['text'])->toContain('sudah dipakai')
        ->and(DB::table('retired_numbers')->count())->toBe(1);

    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods]));
    $active = newest();
    $this->actingAs($this->admin)->post("/proyek/{$active->public_id}/hapus-draf", ['command_id' => (string) Str::uuid()]);
    expect(projectAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'L-37'])->and($active->fresh())->not->toBeNull();
});

it('answers the later save of one draft CONFLICT with the input kept, whichever lines each changed (criterion 7; ST-01; PX-07)', function () {
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods, $this->service], ['pic' => null]));
    $project = newest();
    $lines = DB::table('project_lines')->where('project_id', $project->id)->orderBy('line_no')->pluck('id')->all();
    $line = fn (int $index, string $quantity): array => ['baris' => RowHandle::of('project_lines', (int) $lines[$index]), 'produk' => [$this->goods, $this->service][$index]['product']->public_id,
        'satuan' => [$this->goods, $this->service][$index]['base']->code, 'jumlah' => $quantity, 'harga' => '15000', 'mode' => $index === 0 ? 'WAREHOUSE' : 'SERVICE'];
    $version = $project->fresh()?->lock_version;
    $other = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view']);

    $this->actingAs($this->admin)->put("/proyek/{$project->public_id}", Projects::payload($this->company, $this->client, [], ['pic' => null, 'lock_version' => $version, 'baris' => [$line(0, '11'), $line(1, '10')]]));
    expect(projectAnswer()['kind'] ?? null)->toBe('COMMITTED');
    $this->actingAs($other)->from("/proyek/{$project->public_id}/ubah")->put("/proyek/{$project->public_id}", Projects::payload($this->company, $this->client, [], ['pic' => null, 'lock_version' => $version, 'baris' => [$line(0, '10'), $line(1, '3')]]))
        ->assertRedirect("/proyek/{$project->public_id}/ubah");

    expect(projectAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'ST-01', 'cause' => 'stale'])
        ->and(DB::table('project_lines')->where('project_id', $project->id)->orderBy('line_no')->pluck('entered_qty')->all())->toBe(['11.000', '10.000']);
});

it('answers a prefilled date that went stale CONFLICT with both dates, and a future date at its field (ST-08; C-50; MSG-16)', function () {
    $yesterday = CarbonImmutable::parse(BusinessDate::today())->subDay()->toDateString();
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['tanggal' => $yesterday, 'tanggal_otomatis' => true]));
    expect(projectAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'ST-08'])->and(projectAnswer()['text'])->toContain(BusinessDate::today());

    $tomorrow = CarbonImmutable::parse(BusinessDate::today())->addDay()->toDateString();
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['tanggal' => $tomorrow, 'tanggal_otomatis' => false]))->assertSessionHasErrors('tanggal');
    expect(Project::query()->count())->toBe(0);
});

it('refuses a new project in an inactive company (BR-ACC-03; C-54)', function () {
    DB::table('companies')->where('id', $this->company->id)->update(['is_active' => false]);
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods]));

    expect(projectAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'C-54'])->and(Project::query()->count())->toBe(0);
});

it('refuses a line whose quantity the product\'s scale cannot hold, or whose mode its kind forbids, at the line (C-09; BR-XD-03; IP-14)', function () {
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods, $this->service], ['baris' => [
        ['produk' => $this->goods['product']->public_id, 'satuan' => $this->goods['base']->code, 'jumlah' => '1.5', 'mode' => 'WAREHOUSE'],
        ['produk' => $this->service['product']->public_id, 'satuan' => $this->service['base']->code, 'jumlah' => '1', 'mode' => 'WAREHOUSE'],
    ]]))->assertSessionHasErrors(['baris.0.jumlah', 'baris.1.mode']);
});

it('renders PG-013 with the companies in scope, the chosen client\'s rows and the chosen product, prices to finance.view only (ROUTE-013; criterion 12)', function () {
    $this->actingAs($this->admin)->get('/proyek/baru?klien='.$this->client['organization']->public_id.'&produk='.$this->goods['product']->public_id)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('PRJ/ProjectForm', false)
            ->where('companies.0.id', $this->company->public_id)->where('companies.0.money', true)
            ->where('client.units.0.handle', RowHandle::of('client_units', $this->client['unit']->id))
            ->where('product.price', '15000.00')
            ->where('project', null));

    $plain = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage']);
    $this->actingAs($plain)->get('/proyek/baru?produk='.$this->goods['product']->public_id)
        ->assertInertia(fn (AssertableInertia $page) => $page->where('companies.0.money', false)->missing('product.price'));
});

it('keeps a link the editor cannot see when an amendment leaves it as it was, and refuses none for it (XL-4; ER-06)', function () {
    $other = Projects::company();
    $partner = ProjectFactory::new()->createOne(['company_id' => $other->id, 'period_key' => '2019']);
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods]));
    $project = newest();
    DB::table('projects')->where('id', $project->id)->update(['linked_project_id' => $partner->id, 'link_reason' => 'Pengadaan bersama']);
    $line = RowHandle::of('project_lines', (int) DB::table('project_lines')->where('project_id', $project->id)->value('id'));

    $this->actingAs($this->admin)->put("/proyek/{$project->public_id}", Projects::payload($this->company, $this->client, [], ['lock_version' => $project->fresh()?->lock_version, 'judul' => 'Judul baru', 'tautan_tetap' => true,
        'baris' => [['baris' => $line, 'produk' => $this->goods['product']->public_id, 'satuan' => $this->goods['base']->code, 'jumlah' => '10', 'harga' => '15000', 'mode' => 'WAREHOUSE']]]));

    expect(projectAnswer()['kind'] ?? null)->toBe('COMMITTED')
        ->and($project->fresh()?->only(['title', 'linked_project_id', 'link_reason']))->toBe(['title' => 'Judul baru', 'linked_project_id' => $partner->id, 'link_reason' => 'Pengadaan bersama']);
});

it('refuses removing a line a quotation revision cites, in words, with the path to a revision (L-20; ER-06)', function () {
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods, $this->service]));
    $project = newest();
    $lines = DB::table('project_lines')->where('project_id', $project->id)->orderBy('line_no')->get();
    $revision = DB::table('quotation_revisions')->insertGetId(['public_id' => (string) Str::uuid(), 'project_id' => $project->id, 'company_id' => $this->company->id, 'revision_no' => 1, 'state' => 'DRAFT', 'recorded_at' => now(), 'recorded_by' => $this->admin->id]);
    $quoted = $lines[0];
    DB::table('quotation_revision_lines')->insert(['quotation_revision_id' => $revision, 'company_id' => $this->company->id, 'project_line_id' => $quoted->id, 'product_id' => $quoted->product_id,
        'quantity_scale' => $quoted->quantity_scale, 'description' => 'Buku', 'entered_qty' => '10', 'entered_unit_id' => $quoted->entered_unit_id, 'ratio_to_base' => 1, 'qty_base' => '10',
        'unit_price' => '15000', 'sales_value' => '150000', 'recorded_at' => now(), 'recorded_by' => $this->admin->id]);
    $kept = $lines[1];

    $this->actingAs($this->admin)->put("/proyek/{$project->public_id}", Projects::payload($this->company, $this->client, [], ['lock_version' => $project->fresh()?->lock_version,
        'baris' => [['baris' => RowHandle::of('project_lines', (int) $kept->id), 'produk' => $this->service['product']->public_id, 'satuan' => $this->service['base']->code, 'jumlah' => '10', 'harga' => '15000', 'mode' => 'SERVICE']]]));

    expect(projectAnswer())->toMatchArray(['kind' => 'REJECTED', 'code' => 'L-20'])->and(projectAnswer()['text'])->toContain('revisi penawaran')
        ->and(DB::table('project_lines')->where('project_id', $project->id)->count())->toBe(2);
});

it('keeps an ACTIVE project\'s unit and PIC, refusing an amendment without them at the fields (SM:Project; ER-06)', function () {
    $this->actingAs($this->admin)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods]));
    $project = newest();

    $this->actingAs($this->admin)->put("/proyek/{$project->public_id}", Projects::payload($this->company, $this->client, [], ['lock_version' => $project->fresh()?->lock_version, 'unit' => null, 'pic' => null, 'baris' => []]))
        ->assertSessionHasErrors(['unit', 'pic']);
    expect($project->fresh()?->client_contact_id)->not->toBeNull();
});
