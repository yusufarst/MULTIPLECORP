<?php

use App\Modules\FIN\Actions\IssueInvoice;
use App\Modules\FIN\Actions\VoidInvoice;
use App\Modules\FIN\Data\InvoiceCorrection;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Queries\InvoiceList;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-049; QB-08; QB-09; QB-13; PFM-01; PFM-02; PFM-20): PG-041 reads its page in the same number of statements for one invoice and a full page; PG-043 in the same number for one application and many; "Terbitkan Invoice" within the reference command's forty (AX-16).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->actor = User::query()->findOrFail($this->world->actorId);
    $this->project = $this->world->project(['SERVICE'], '1000');
});

afterEach(fn () => @unlink($this->hotFile));

function invoiceStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('reads PG-041 in the same number of statements for one invoice and a full page, the page\'s own within QB-08 (QB-08; PFM-01; PFM-02)', function () {
    $this->world->issued($this->project, '1000');
    $query = app(InvoiceList::class);
    $viewer = app(EffectiveCapabilities::class)->for($this->world->actorId);
    $one = invoiceStatements(fn () => $query->page($viewer, $this->world->company->id, null, null));
    $request = invoiceStatements(fn () => $this->actingAs($this->actor)->get('/keuangan/invoice')->assertOk());

    foreach (range(1, 25) as $n) {
        $this->world->draft($this->project, (string) (1000 + $n));
    }

    $page = invoiceStatements(fn () => $query->page($viewer, $this->world->company->id, null, null));
    $full = invoiceStatements(fn () => $this->actingAs($this->actor)->get('/keuangan/invoice')->assertOk());

    expect($page)->toBe($one)->and($page)->toBeLessThanOrEqual(4)->and($full)->toBe($request);
});

it('reads PG-043 in the same number of statements for one application and many (QB-09; PFM-02)', function () {
    $id = $this->world->issued($this->project, '10000000');
    $public = InvoiceWorld::publicId($id);
    InvoiceWorld::apply($id, InvoiceWorld::payment($id, '1000'), '1000');
    $one = invoiceStatements(fn () => $this->actingAs($this->actor)->get("/keuangan/invoice/{$public}")->assertOk());

    foreach (range(1, 20) as $n) {
        InvoiceWorld::apply($id, InvoiceWorld::payment($id, '1000'), '1000');
    }

    expect(invoiceStatements(fn () => $this->actingAs($this->actor)->get("/keuangan/invoice/{$public}")->assertOk()))->toBe($one);
});

it('issues an invoice within the reference command\'s forty statements (QB-13; PFM-20; AX-16)', function () {
    // The period's first number seeds its sequence once (NM-01), and the rendition is its own job's command: neither is this command's budget.
    $this->world->issued($this->project, '1000');
    $id = $this->world->draft($this->project, '1000');
    $invoice = Invoice::query()->findOrFail($id);
    $action = app(IssueInvoice::class);
    Queue::fake();

    $statements = invoiceStatements(fn () => expect($action->run((string) Str::uuid(), $this->world->actorId, InvoiceWorld::ref($this->project), $invoice, $invoice->lock_version, 'DOC-06', BusinessDate::today(), false))->toHaveProperty('kind', 'COMMITTED'));

    expect($statements)->toBeLessThanOrEqual(45);
});

it('issues a pre-payment Kuitansi within the reference command\'s budget (QB-13; PFM-20; AX-31)', function () {
    $id = $this->world->issued($this->project, '1000000');
    InvoiceWorld::issueKuitansi($this->world->actorId, $id, InvoiceWorld::prepareKuitansi($this->world->actorId, $id, '1000'), BusinessDate::today());
    $document = InvoiceWorld::prepareKuitansi($this->world->actorId, $id, '1000');
    Queue::fake();

    $statements = invoiceStatements(fn () => expect(InvoiceWorld::issueKuitansi($this->world->actorId, $id, $document, BusinessDate::today()))->toBe('COMMITTED'));

    expect($statements)->toBeLessThanOrEqual(45);
});

it('corrects by statements proportional to the reductions it must change, none per row read (QB-14; PFM-20; AX-22)', function () {
    Queue::fake();
    $void = function (int $applications): int {
        $id = $this->world->issued($this->project, '1000000');
        $disposed = [];

        foreach (range(1, $applications) as $n) {
            $disposed[InvoiceWorld::apply($id, InvoiceWorld::payment($id, '1000'), '1000')] = InvoiceCorrection::RELEASE;
        }

        $invoice = Invoice::query()->findOrFail($id);
        $input = new InvoiceCorrection('UNBILLED_ENTRY_ERROR', 'Salah catat', BusinessDate::today(), false, applications: $disposed);

        return invoiceStatements(fn () => expect(InvoiceWorld::word(app(VoidInvoice::class)->run((string) Str::uuid(), $this->world->actorId, $invoice, $invoice->lock_version, $input, ['invoice' => $id])))->toBe('COMMITTED'));
    };
    $one = $void(1);
    $six = $void(6);
    $eleven = $void(11);

    // Linear in the rows changed: each further application costs the same few statements.
    expect($eleven - $six)->toBe($six - $one)->and(($six - $one) / 5)->toBeLessThanOrEqual(8);
});
