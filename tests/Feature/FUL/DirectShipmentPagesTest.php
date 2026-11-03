<?php

use App\Modules\ORG\Enums\SequenceType;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\DropshipWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;

// TL-04, TL-07 (TASK-046; ROUTE-032; ROUTE-205–ROUTE-207; ROUTE-216; NAV-032; WF-DOC-01; BR-DOC-04; criteria 1, 3, 5–8, 11): PG-032
// with the lines' figures and no receiving or dispatch control, a confirmation answering on PG-011, each refusal in place
// with its figure, the reversal and the returns in place, the direct shipment's *Surat Jalan* drafted from the recorded
// facts and issued through ROUTE-216 with no stock, cash or obligation changed; a wholly reversed confirmation has none.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    DocumentTypeFactory::ensure('DOC-03');
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = DropshipWorld::actor($this->company);
    $this->w = DropshipWorld::make($this->world, '10', '6');
    $this->page = "/proyek/{$this->w['project']->publicId}/kirim-langsung";
    $this->proof = (string) DB::table('evidence_documents')->where('id', DropshipWorld::proof($this->company->id))->value('public_id');
});

afterEach(fn () => @unlink($this->hotFile));

function dropshipBody(object $test, string $qty, string $reference = 'SJ-PMS-1', array $more = []): array
{
    return ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'pembelian' => $test->w['purchase']->public_id, 'referensi' => $reference, 'bukti' => $test->proof,
        'baris' => [['baris' => RowHandle::of('purchase_lines', $test->w['purchase_line']), 'jumlah' => $qty]], ...$more];
}

function dropshipAnswer(object $test, string $url, array $body): array
{
    $answer = $test->actingAs($test->actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
    Auth::forgetGuards();

    return $answer;
}

it('shows PG-032 with the line\'s figures and no receiving or dispatch control, then confirms through ROUTE-205 back to PG-011 (NAV-032; BR-INV-11; criteria 1, 3)', function () {
    $this->actingAs($this->actor)->get($this->page)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('FUL/DirectShipment')
        ->where('page.lines.0.ordered', '6')->where('page.lines.0.remainder', '6')->where('page.confirmations', [])->where('page.can.confirm', true)->missing('page.can.receive'));

    $this->actingAs($this->actor)->post($this->page, dropshipBody($this, '4'))->assertRedirect("/proyek/{$this->w['project']->publicId}");
    Auth::forgetGuards();

    $this->actingAs($this->actor)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('page.lines.0.confirmed', '4')->where('page.lines.0.remainder', '2')->where('page.confirmations.0.reference', 'SJ-PMS-1')->where('page.confirmations.0.lines.0.cost', '400000.00'));
});

it('answers each refusal in place with its figure: above the line\'s remainder, a shipment reference already recorded, the delivery without its recipient (C-29; BD-04; MSG-02; MSG-04; NV-04; criterion 3)', function () {
    expect(dropshipAnswer($this, $this->page, dropshipBody($this, '4'))['kind'])->toBe('COMMITTED');

    $over = dropshipAnswer($this, $this->page, dropshipBody($this, '3', 'SJ-PMS-2'));
    $again = dropshipAnswer($this, $this->page, dropshipBody($this, '1'));

    expect($over['code'])->toBe('C-29')->and($over['text'])->toContain('2')
        ->and($again['kind'])->toBe('CONFLICT')->and($again['text'])->toBe(__('messages.msg_04'));

    $this->actingAs($this->actor)->post($this->page, dropshipBody($this, '1', 'SJ-PMS-3', ['bukti_klien' => $this->proof]))->assertSessionHasErrors('penerima');
    $this->actingAs($this->actor)->post($this->page, dropshipBody($this, '0', 'SJ-PMS-4'))->assertSessionHasErrors('baris');

    expect(DB::table('dropship_confirmations')->count())->toBe(1);
});

it('records the delivery with the confirmation when the client\'s proof comes with it (L-40; criterion 5)', function () {
    expect(dropshipAnswer($this, $this->page, dropshipBody($this, '2', 'SJ-PMS-1', ['penerima' => 'Bu Sari', 'bukti_klien' => $this->proof]))['kind'])->toBe('COMMITTED');

    $this->actingAs($this->actor)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page->where('page.confirmations.0.delivery.recipient', 'Bu Sari')->where('page.confirmations.0.lines.0.delivered', '2'));
});

it('reverses and returns through ROUTE-206 and ROUTE-207 in place, each with its reason; a return to the warehouse needs its rack (CM-14; CM-15; CM-27; IP-09; criteria 6–8)', function () {
    dropshipAnswer($this, $this->page, dropshipBody($this, '4'));
    $confirmation = DropshipWorld::last();
    $url = "{$this->page}/{$confirmation->public_id}";
    $line = RowHandle::of('dropship_confirmation_lines', $confirmation->lines[0]);
    $correction = fn (string $qty, array $more = []): array => ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'alasan' => 'Salah catat', 'baris' => [['baris' => $line, 'jumlah' => $qty]], ...$more];

    expect(dropshipAnswer($this, "{$url}/balikkan", $correction('1'))['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->post("{$url}/retur", $correction('1', ['tujuan' => 'GUDANG']))->assertSessionHasErrors('rak');
    Auth::forgetGuards();

    expect(dropshipAnswer($this, "{$url}/retur", $correction('1', ['tujuan' => 'GUDANG', 'rak' => RowHandle::of('locations', ReceivingWorld::rack()), 'kondisi' => 'USABLE']))['kind'])->toBe('COMMITTED')
        ->and(dropshipAnswer($this, "{$url}/retur", $correction('1', ['tujuan' => 'PEMASOK']))['kind'])->toBe('COMMITTED')
        ->and(dropshipAnswer($this, "{$url}/balikkan", $correction('9'))['code'])->toBe('C-27');

    $this->actingAs($this->actor)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page->where('page.confirmations.0.lines.0.open', '1')->has('page.confirmations.0.corrections', 3));
    expect(DropshipWorld::demand($this->w['line'])['confirmed'])->toBe('1.000');
});

it('drafts the direct shipment\'s Surat Jalan from its recorded facts and issues it through ROUTE-216 once, no stock, cash or obligation changed; a wholly reversed confirmation has none (DOC-03; WF-DOC-01; BR-DOC-04; DIR-100; criterion 11)', function () {
    $year = substr(BusinessDate::today(), 0, 4);
    Numbering::goLive("{$year}-01-01");
    Numbering::scheme($this->company, SequenceType::Doc03, "{$year}-01-01", 'YEAR', 'UJI/SJ/{tahun}/{nomor:4}');
    Numbering::started($this->company, SequenceType::Doc03, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
    expect(DropshipWorld::confirm($this->actor->id, $this->w, '2', 'SJ-1'))->toBe('COMMITTED');
    $confirmation = DropshipWorld::last();
    $movements = DB::table('stock_movements')->count();
    $guard = DropshipWorld::guard($this->w['purchase_line']);

    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-03', 'subjek' => ['dropship_confirmation_id' => $confirmation->public_id]])->assertRedirect();

    $document = DB::table('documents')->where('dropship_confirmation_id', $confirmation->id)->sole();
    $draft = DB::table('document_versions')->where('document_id', $document->id)->sole();
    Queue::fake();
    $issue = ['command_id' => (string) Str::uuid(), 'lock_version' => $draft->lock_version, 'draf' => $draft->public_id, 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true];
    $this->actingAs($this->actor)->post("{$this->page}/{$document->public_id}/surat-jalan", $issue)->assertRedirect();
    $this->actingAs($this->actor)->post("{$this->page}/{$document->public_id}/surat-jalan", $issue)->assertRedirect();

    expect(DB::table('document_versions')->where('document_id', $document->id)->pluck('state')->all())->toBe(['ISSUED'])
        ->and(DB::table('document_versions')->where('document_id', $document->id)->value('formatted_number'))->toStartWith('UJI/SJ/')
        ->and(DB::table('stock_movements')->count())->toBe($movements)->and(DB::table('deliveries')->count())->toBe(0)
        ->and(DropshipWorld::guard($this->w['purchase_line']))->toEqual($guard);

    // Wholly reversed, the confirmation leaves nothing shipped: no paper is drafted for it.
    DropshipWorld::confirm($this->actor->id, $this->w, '1', 'SJ-2');
    $reversed = DropshipWorld::last();
    expect(DropshipWorld::reverse($this->actor->id, $this->w['project'], $reversed->id, [['line' => $reversed->lines[0], 'quantity' => '1']]))->toBe('COMMITTED');
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-03', 'subjek' => ['dropship_confirmation_id' => $reversed->public_id]]);

    expect(DB::table('documents')->where('dropship_confirmation_id', $reversed->id)->count())->toBe(0);
});
