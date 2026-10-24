<?php

use App\Modules\FIN\Models\Invoice;
use App\Modules\IAM\Queries\ScopedRecords;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-012): a payment application naming another company's invoice fails at the scope resolver,
// exactly as a missing one, before any command could cite it (CS-03, CS-04; DP-03; architecture challenge
// 6b); another client's invoice of the same company fails on C-14's composite keys
// (tests/Integration/Schema/MoneyConstraintsTest.php), so applications stay within one company and client.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->own = Invoice::factory()->create();
    $this->foreign = Invoice::factory()->create();
    $this->authority = app(EffectiveCapabilities::class)->for(Authorization::admin([$this->own->company_id], ['finance.view', 'payment.apply']));
});

it('resolves the actor\'s own company\'s invoice', function () {
    expect(app(ScopedRecords::class)->find(Invoice::query(), $this->own->public_id, $this->authority)->is($this->own))->toBeTrue();
});

it('refuses another company\'s invoice exactly as a missing one', function (Closure $publicId) {
    app(ScopedRecords::class)->find(Invoice::query(), $publicId->call($this), $this->authority);
})->with([
    'another company\'s invoice' => [fn () => $this->foreign->public_id],
    'a missing invoice' => [fn () => '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b'],
])->throws(ModelNotFoundException::class);
