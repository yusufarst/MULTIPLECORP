<?php

use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Services\FileStore;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\AccountAccess;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\Reporting\Exports\ExportFile;
use App\Modules\Reporting\Exports\ExportSweep;
use App\Modules\Reporting\Exports\GenerateExport;
use App\Modules\Reporting\Reports\Filters;
use App\Modules\Reporting\Reports\Reports;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-05, TL-06, TL-08 (TASK-060; ROUTE-283; ROUTE-284; ROUTE-285; JB-03; JB-09; RV-07; RV-08; H6-04; DP-07; FL-07; FL-09;
// DIR-088; DIR-089 (5); M-23; HO-18; acceptance criteria 6–9, 13, 14): the export end to end — admission under the
// requester's lock with its two-at-once and row bounds, the job under one shared overlap lock re-authorizing before its
// snapshot, the file a private EXPORT file of its own request whose content equals the page's rows, typed and
// neutralized, in Indonesian —; a revocation before or after generation withholding it; the download an ordinary
// request authorized at request time; another account's identifier and file answering as not found; the sweep.

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('files');
    Authorization::catalogue();
    $this->hot = (string) tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hot, 'http://localhost:5173');
    Vite::useHotFile($this->hot);
    $this->world = InvoiceWorld::make();
    $project = $this->world->project();
    $this->invoice = $this->world->issued($project, '1000000.00');
    // A client name carrying a formula, assembled at run time (FL-09; TM-16).
    $this->formula = implode('', ['=', 'HYPER', 'LINK("http://contoh.invalid")']);
    DB::table('client_organizations')->where('id', DB::table('invoices')->where('id', $this->invoice)->value('client_organization_id'))->update(['name' => $this->formula]);
    $this->requester = User::query()->findOrFail(Authorization::admin([$this->world->company->id], ['finance.view', 'reports.export'])->id);
});

afterEach(function () {
    @unlink($this->hot);
});

function exportAsk(object $test, User $user, array $payload = []): TestResponse
{
    return $test->actingAs($user)->post('/laporan/ekspor', ['laporan' => 'penjualan', 'bentuk' => 'xlsx', 'periode' => 'bulan-ini', ...$payload]);
}

/** The in-place answer the request left as flash data (IP-01). */
function exportAnswer(): ?string
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) && is_string($flash['answer']['text'] ?? null) ? $flash['answer']['text'] : null;
}

function latestExport(int $userId): object
{
    return DB::table('export_requests')->where('requested_by', $userId)->orderByDesc('id')->first() ?? throw new RuntimeException('No export request.');
}

/** @return list<list<string>> the first worksheet's rows, each cell's text as written */
function sheetRows(string $bytes): array
{
    $path = (string) tempnam(sys_get_temp_dir(), 'xlsx-');
    file_put_contents($path, $bytes);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    @unlink($path);
    $rows = [];

    foreach ((new SimpleXMLElement($xml))->sheetData->row as $row) {
        $cells = [];

        foreach ($row->c as $cell) {
            $cells[] = (string) $cell['t'] === 'inlineStr' ? (string) $cell->is->t : ((string) $cell['t'] === 'n' ? 'n:'.(string) $cell->v : '');
        }

        $rows[] = $cells;
    }

    return $rows;
}

it('admits a request with its recorded context, generates the page\'s rows as a private EXPORT file and serves it to its requester (criteria 6, 9; JB-03; DP-07; FL-07; FL-09)', function () {
    exportAsk($this, $this->requester)->assertRedirect('/laporan/ekspor');
    $request = latestExport($this->requester->id);

    expect($request->state)->toBe('READY')->and($request->report_code)->toBe('penjualan')->and($request->claim_count)->toBe(1)
        ->and($request->company_ids)->toBe('{'.$this->world->company->id.'}')->and($request->capability_codes)->toBe('{finance.view}')
        ->and(json_decode($request->filters, true))->toMatchArray(['periode' => 'bulan-ini', 'bentuk' => 'xlsx'])
        ->and(CarbonImmutable::parse($request->expires_at)->greaterThan(now()->addHours(23)))->toBeTrue();

    $file = FileObject::query()->findOrFail($request->file_object_id);
    expect($file->purpose)->toBe('EXPORT')->and($file->company_id)->toBeNull()->and($file->uploaded_by)->toBe($this->requester->id);

    $response = $this->actingAs($this->requester)->get("/laporan/ekspor/{$request->id}/unduh");
    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;')->toContain('laporan-penjualan')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox');

    $rows = sheetRows($response->streamedContent());
    // Indonesian whatever the interface language: the title, the header row, then the company's row and its total.
    expect($rows[0])->toBe(['Laporan Penjualan'])
        ->and($rows[3])->toBe(['Perusahaan', 'Nomor', 'Tanggal', 'Klien', 'Proyek', 'Nilai penjualan', 'PPN keluaran', 'Nilai tagihan'])
        ->and($rows[4][0])->toBe($this->world->company->code)
        // FL-09: the formula is text with its quote; the numbers typed numbers.
        ->and($rows[4][3])->toBe("'".$this->formula)
        ->and($rows[4][5])->toBe('n:1000000.00')
        ->and($rows[5][1])->toBe('Jumlah')->and($rows[5][5])->toBe('n:1000000.00');

    // The same rows as the page's read for the same filters and context (criterion 6; ARCHITECTURE §14).
    $viewer = app(EffectiveCapabilities::class)->for($this->requester->id);
    $page = Reports::read(app(Reports::class)->find('penjualan') ?? throw new RuntimeException, $viewer, Filters::of($viewer, ['periode' => 'bulan-ini'], BusinessDate::today()));
    expect(count($page[0]['sections'][0]['rows']))->toBe(count($rows) - 5);

    $this->actingAs($this->requester)->get("/status/ekspor/{$request->id}")->assertOk()->assertJson(['state' => 'READY', 'file' => "/laporan/ekspor/{$request->id}/unduh"]);
    expect(DB::table('security_events')->whereIn('event_kind', ['EXPORT_REQUESTED', 'EXPORT_DOWNLOADED'])->where('user_id', $this->requester->id)->count())->toBe(2);
});

it('writes a PDF through the renderer when asked (JB-03; FL-08)', function () {
    exportAsk($this, $this->requester, ['bentuk' => 'pdf'])->assertRedirect('/laporan/ekspor');
    $request = latestExport($this->requester->id);
    $response = $this->actingAs($this->requester)->get("/laporan/ekspor/{$request->id}/unduh");

    expect($response->streamedContent())->toStartWith('%PDF-')->and($response->headers->get('Content-Type'))->toBe('application/pdf');
});

it('refuses a third request while two are in progress with MSG-22, and one above the row bound with MSG-21, in place (criterion 7; H6-04; PF-28; UXH-11)', function () {
    Queue::fake();
    exportAsk($this, $this->requester)->assertRedirect('/laporan/ekspor');
    exportAsk($this, $this->requester)->assertRedirect('/laporan/ekspor');
    exportAsk($this, $this->requester)->assertRedirect();
    expect(exportAnswer())->toBe('Dua ekspor Anda masih diproses. Tunggu salah satunya selesai.');
    expect(DB::table('export_requests')->where('requested_by', $this->requester->id)->count())->toBe(2);
    Queue::assertPushed(GenerateExport::class, 2);

    // A RUNNING request whose claim is stale no longer counts (JB-09).
    DB::table('export_requests')->where('requested_by', $this->requester->id)->update(['state' => 'RUNNING', 'claimed_at' => now()->subMinutes(16)]);
    exportAsk($this, $this->requester)->assertRedirect('/laporan/ekspor');

    DB::table('export_requests')->where('requested_by', $this->requester->id)->update(['state' => 'READY']);
    config(['exports.row_bound' => 0]);
    exportAsk($this, $this->requester)->assertRedirect();
    expect(exportAnswer())->toBe('Data melebihi batas satu ekspor. Persempit periode, lalu minta lagi.');
});

it('withholds an export whose requester lost a company or capability before or during generation, and refuses its download like a nonexistent one (criterion 8; RV-07; RV-08; M-23; SM-11)', function () {
    Queue::fake();
    exportAsk($this, $this->requester)->assertRedirect('/laporan/ekspor');
    $request = latestExport($this->requester->id);

    // Revoked after the request, before the job: the job withholds it with nothing written (RV-07).
    Authorization::revoke($this->requester, 'finance.view');
    app(EffectiveCapabilities::class)->forget();
    (new GenerateExport($request->id))->handle(app(EffectiveCapabilities::class), app(Reports::class), app(ExportFile::class), app(FileStore::class));

    expect(DB::table('export_requests')->where('id', $request->id)->value('state'))->toBe('WITHHELD')
        ->and(DB::table('export_requests')->where('id', $request->id)->value('file_object_id'))->toBeNull();
    $this->actingAs($this->requester)->get("/laporan/ekspor/{$request->id}/unduh")->assertNotFound();
});

it('refuses a READY export once the requester\'s grants no longer cover its context, marking it withheld (criterion 8; RV-08; M-23)', function () {
    exportAsk($this, $this->requester)->assertRedirect('/laporan/ekspor');
    $request = latestExport($this->requester->id);
    expect($request->state)->toBe('READY');

    // The company grant revoked without the access command's mark — an escaped request is refused all the same.
    DB::table('user_company_grants')->where('user_id', $this->requester->id)->update(['revoked_at' => now(), 'revoked_by' => Authorization::owner()->id]);
    app(EffectiveCapabilities::class)->forget();

    $this->actingAs($this->requester)->get("/laporan/ekspor/{$request->id}/unduh")->assertNotFound();
    expect(DB::table('export_requests')->where('id', $request->id)->value('state'))->toBe('WITHHELD');
    $this->actingAs($this->requester)->get("/status/ekspor/{$request->id}")->assertOk()->assertJson(['state' => 'WITHHELD', 'file' => null]);

    // RV-08's mark by the access-changing command finds nothing more to withhold.
    expect(app(AccountAccess::class)->withholdExports($this->requester->id, CarbonImmutable::now()))->toBe(0);
});

it('marks a READY export WITHHELD in the access change that revokes a view capability it recorded (criterion 8; RV-08; M-23)', function () {
    exportAsk($this, $this->requester)->assertRedirect('/laporan/ekspor');
    $request = latestExport($this->requester->id);

    Authorization::revoke($this->requester, 'finance.view');
    expect(app(AccountAccess::class)->withholdExports($this->requester->id, CarbonImmutable::now()))->toBe(1)
        ->and(DB::table('export_requests')->where('id', $request->id)->value('state'))->toBe('WITHHELD');
    app(EffectiveCapabilities::class)->forget();
    $this->actingAs($this->requester)->get("/laporan/ekspor/{$request->id}/unduh")->assertNotFound();
});

it('finds a request only within its requester\'s own: another account\'s identifier and file answer as not found (criteria 13, 14; DIR-088; DIR-089 (5); H6-04)', function () {
    exportAsk($this, $this->requester)->assertRedirect('/laporan/ekspor');
    $request = latestExport($this->requester->id);
    $other = User::query()->findOrFail(Authorization::admin([$this->world->company->id], ['finance.view', 'reports.export'])->id);
    $owner = User::query()->findOrFail(Authorization::owner()->id);

    foreach ([$other, $owner] as $stranger) {
        $this->actingAs($stranger)->get("/laporan/ekspor/{$request->id}/unduh")->assertNotFound();
        $this->actingAs($stranger)->get("/status/ekspor/{$request->id}")->assertNotFound();
        // The file itself through ROUTE-226 answers the same: an export file is served only through its request.
        $this->actingAs($stranger)->get("/berkas/{$request->file_object_id}/unduh")->assertNotFound();
    }

    $this->actingAs($this->requester)->get('/berkas/'.$request->file_object_id.'/unduh')->assertNotFound();
    $this->actingAs($other)->get('/laporan/ekspor/0190a0a0-0000-7000-8000-000000000000/unduh')->assertNotFound();
    $this->actingAs($other)->get('/laporan/ekspor')->assertOk()->assertInertia(fn ($page) => $page->component('Reporting/EksporSaya')->where('rows', []));
    $this->actingAs($this->requester)->get('/laporan/ekspor')->assertOk()->assertInertia(fn ($page) => $page->where('rows.0.id', $request->id)->where('rows.0.state', 'READY'));
});

it('generates the period the request resolved, not the preset again on the day the job runs (PF-28; criterion 6)', function () {
    Queue::fake();
    exportAsk($this, $this->requester)->assertRedirect('/laporan/ekspor');
    $request = latestExport($this->requester->id);

    $this->travel(40)->days();
    app(EffectiveCapabilities::class)->forget();
    (new GenerateExport($request->id))->handle(app(EffectiveCapabilities::class), app(Reports::class), app(ExportFile::class), app(FileStore::class));
    $response = $this->actingAs($this->requester)->get("/laporan/ekspor/{$request->id}/unduh");

    expect(sheetRows($response->streamedContent())[4][5])->toBe('n:1000000.00');
});

it('runs one export at a time under one shared overlap lock that outlives the timeout (JB-03; PERFORMANCE SZ-02)', function () {
    $job = new GenerateExport('0190a0a0-0000-7000-8000-000000000000');
    $lock = collect($job->middleware())->first(fn (object $middleware): bool => $middleware instanceof WithoutOverlapping);

    expect($lock)->toBeInstanceOf(WithoutOverlapping::class)->and($lock->key)->toBe('exports')->and($lock->shareKey)->toBeTrue()
        ->and($lock->expiresAfter)->toBe(720)->and($lock->releaseAfter)->toBe(30)->and($job->timeout)->toBe(600)->and($job->tries)->toBe(0);
});

it('fails a request claimed three times and sweeps a stale claim and a lost dispatch (JB-03; JB-09; HO-18)', function () {
    Queue::fake();
    exportAsk($this, $this->requester)->assertRedirect('/laporan/ekspor');
    $request = latestExport($this->requester->id);

    DB::table('export_requests')->where('id', $request->id)->update(['state' => 'RUNNING', 'claim_count' => 3, 'claimed_at' => now()->subMinute()]);
    (new GenerateExport($request->id))->handle(app(EffectiveCapabilities::class), app(Reports::class), app(ExportFile::class), app(FileStore::class));
    expect(DB::table('export_requests')->where('id', $request->id)->value('state'))->toBe('FAILED');

    DB::table('export_requests')->where('id', $request->id)->update(['state' => 'RUNNING', 'claim_count' => 1, 'claimed_at' => now()->subMinutes(16)]);
    expect(app(ExportSweep::class)->sweep())->toBe(1)->and(DB::table('export_requests')->where('id', $request->id)->value('state'))->toBe('FAILED');

    // A lost dispatch: PENDING for more than two minutes while none runs (the runtime never moves `recorded_at`).
    DB::table('export_requests')->where('id', $request->id)->update(['state' => 'PENDING']);
    Queue::fake();
    expect(app(ExportSweep::class)->sweep())->toBe(0);
    $this->travel(3)->minutes();
    expect(app(ExportSweep::class)->sweep())->toBe(1);
    Queue::assertPushed(GenerateExport::class, fn (GenerateExport $job): bool => $job->requestId === $request->id);
});
