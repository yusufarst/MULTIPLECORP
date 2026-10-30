<?php

use App\Modules\FIN\Queries\ReceivableList;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-050; QB-07; QB-13; PFM-01; PFM-02; PFM-20): PG-044 reads its page in at most four statements, the same for one receivable and a full page, ageing one group; AX-17's and AX-24's commands within the reference command's budget.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->actor = User::query()->findOrFail($this->world->actorId);
    $this->project = $this->world->project(['SERVICE'], '1000');
    $this->today = BusinessDate::today();
    Queue::fake();
});

afterEach(fn () => @unlink($this->hotFile));

function receivableStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('reads PG-044 in at most four statements, the same for one receivable and a full page (QB-07; PFM-01; PFM-02)', function () {
    $billed = function (): void {
        InvoiceWorld::bill($this->world->issued($this->project, '1000'), $this->today, '2099-12-31');
    };
    $billed();
    $query = app(ReceivableList::class);
    $screen = fn (): array => $query->screen($this->world->actorId, null, 'aktif', null, null);
    app(EffectiveCapabilities::class)->for($this->world->actorId);
    $one = receivableStatements($screen);
    $request = receivableStatements(fn () => $this->actingAs($this->actor)->get('/keuangan/piutang')->assertOk());

    foreach (range(1, 12) as $n) {
        $billed();
    }

    $many = receivableStatements($screen);

    expect($many)->toBe($one)->and($many)->toBeLessThanOrEqual(4)
        ->and(receivableStatements(fn () => $this->actingAs($this->actor)->get('/keuangan/piutang')->assertOk()))->toBe($request);
});

it('bills and holds within the reference command\'s budget (QB-13; PFM-20; AX-17; NX-09)', function () {
    $id = $this->world->issued($this->project, '1000');

    expect(receivableStatements(fn () => expect(InvoiceWorld::billInCommand($this->world->actorId, $id, $this->today, '2099-12-31'))->toBe('COMMITTED')))->toBeLessThanOrEqual(40)
        ->and(receivableStatements(fn () => expect(InvoiceWorld::holdInCommand($this->world->actorId, $id))->toBe('COMMITTED')))->toBeLessThanOrEqual(40);
});

it('writes off and supersedes within the reference command\'s budget, one more per credit source locked only (QB-13; PFM-20; AX-24)', function () {
    $owner = Authorization::owner()->id;
    $id = $this->world->issued($this->project, '1000000');
    InvoiceWorld::bill($id, $this->today, '2099-12-31');
    expect(receivableStatements(fn () => expect(InvoiceWorld::writeOffInCommand($owner, $id, '500000', $this->today))->toBe('COMMITTED')))->toBeLessThanOrEqual(40);

    $writeoff = (int) DB::table('receivable_writeoffs')->where('invoice_id', $id)->value('id');
    $payment = InvoiceWorld::recovery($id, '1000');
    expect(receivableStatements(fn () => expect(InvoiceWorld::supersedeInCommand($owner, $id, $writeoff, $payment->id, '1000', $this->today))->toBe('COMMITTED')))->toBeLessThanOrEqual(40);
});
