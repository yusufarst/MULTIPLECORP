<?php

use App\Modules\IAM\Authentication\GoogleIdentities;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-04 (TASK-019; SECURITY AU-23, AU-24; BD-19; C-63, C-64): the Google identity's link and lifecycle — the holder
// links once, the same subject again a no-op, a subject linked elsewhere refused with GOOGLE_LINK_CONFLICT, an
// account linked already refused, an account without active TOTP refused; the e-mail kept for display; an unlink
// recording its reason and actor once, a relink adding a row; each step's audit event in its transaction and its
// security event after the commit, a rollback leaving neither (criterion 5); no role, capability or scope from it
// (AZ-13).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->identities = app(GoogleIdentities::class);
    $this->holder = TwoFactor::enrol(User::factory()->create());
    $this->other = TwoFactor::enrol(User::factory()->create());
});

function linkRows(): array
{
    return DB::table('user_external_identities')->orderBy('id')->get(['user_id', 'provider_subject', 'email_at_link', 'unlink_reason', 'unlinked_by'])->map(fn ($row) => (array) $row)->all();
}

it('links the holder\'s subject once, with its audit event and GOOGLE_LINKED after the commit (AU-23; criterion 5)', function () {
    expect($this->identities->link($this->holder, 'sub-a', 'laras@gmail.example'))->toBe(GoogleIdentities::LINKED)
        ->and($this->identities->link($this->holder, 'sub-a', 'lain@gmail.example'))->toBe(GoogleIdentities::NO_OP)
        ->and(linkRows())->toBe([['user_id' => $this->holder->id, 'provider_subject' => 'sub-a', 'email_at_link' => 'laras@gmail.example', 'unlink_reason' => null, 'unlinked_by' => null]])
        ->and(DB::table('audit_events')->where('action', 'IAM.GoogleLinked')->where('actor_user_id', $this->holder->id)->where('authority_class', 'SYSTEM')->count())->toBe(1)
        ->and(DB::table('security_events')->where('event_kind', 'GOOGLE_LINKED')->count())->toBe(1)
        ->and($this->identities->accountFor('sub-a')?->id)->toBe($this->holder->id);
});

it('refuses a subject linked to another account, and a second identity for one account (AU-23; BD-19; C-64)', function () {
    $this->identities->link($this->holder, 'sub-a', null);

    expect($this->identities->link($this->other, 'sub-a', null))->toBe(GoogleIdentities::CONFLICT)
        ->and($this->identities->link($this->holder, 'sub-b', null))->toBe(GoogleIdentities::ALREADY_LINKED)
        ->and(DB::table('security_events')->where('event_kind', 'GOOGLE_LINK_CONFLICT')->where('user_id', $this->other->id)->count())->toBe(1)
        ->and(count(linkRows()))->toBe(1);
});

it('refuses to link an account without active TOTP, or an inactive one (AU-23; K2)', function () {
    $plain = User::factory()->create();
    $inactive = TwoFactor::enrol(User::factory()->deactivated()->create());

    expect($this->identities->link($plain, 'sub-c', null))->toBe(GoogleIdentities::REFUSED)
        ->and($this->identities->link($inactive, 'sub-d', null))->toBe(GoogleIdentities::REFUSED)
        ->and(linkRows())->toBe([]);
});

it('unlinks with AU-24\'s reason and actor once, the row kept, a relink adding a row (AU-24; C-63; criterion 5)', function () {
    $this->identities->link($this->holder, 'sub-a', null);

    expect($this->identities->unlink($this->holder, 'SELF', $this->holder->id))->toBeTrue()
        ->and($this->identities->unlink($this->holder, 'SELF', $this->holder->id))->toBeFalse()
        ->and($this->identities->accountFor('sub-a'))->toBeNull()
        ->and(DB::table('audit_events')->where('action', 'IAM.GoogleUnlinked')->where('reason', 'SELF')->count())->toBe(1)
        ->and(DB::table('security_events')->where('event_kind', 'GOOGLE_UNLINKED')->where('reason_code', 'SELF')->count())->toBe(1);

    expect($this->identities->link($this->holder, 'sub-a', null))->toBe(GoogleIdentities::LINKED)
        ->and(count(linkRows()))->toBe(2);

    // OWNER_RECOVERY: no application actor.
    expect($this->identities->unlink($this->holder, 'OWNER_RECOVERY', null))->toBeTrue()
        ->and(linkRows()[1]['unlink_reason'])->toBe('OWNER_RECOVERY')
        ->and(linkRows()[1]['unlinked_by'])->toBeNull()
        ->and(fn () => $this->identities->unlink($this->holder, 'FORGOTTEN', null))->toThrow(InvalidArgumentException::class);
});

it('leaves neither the link, its audit event nor its security event when the link rolls back (CI-09; LG-01; criterion 5)', function () {
    DB::listen(function ($query) {
        if (str_contains($query->sql, 'insert into "audit_events"')) {
            throw new RuntimeException('the step fails after its audit row');
        }
    });

    expect(fn () => $this->identities->link($this->holder, 'sub-a', null))->toThrow(RuntimeException::class);

    expect(linkRows())->toBe([])
        ->and(DB::table('audit_events')->where('action', 'IAM.GoogleLinked')->count())->toBe(0)
        ->and(DB::table('security_events')->where('event_kind', 'GOOGLE_LINKED')->count())->toBe(0);
});

it('gives no role, capability or company scope with a link (AZ-13)', function () {
    Authorization::catalogue();
    $before = app(EffectiveCapabilities::class)->fresh($this->holder->id);

    $this->identities->link($this->holder, 'sub-a', 'owner@gmail.example');

    $after = app(EffectiveCapabilities::class)->fresh($this->holder->id);
    expect($after->capabilities)->toBe($before->capabilities)
        ->and($after->scope->isEmpty())->toBe($before->scope->isEmpty())
        ->and($after->ownerRole)->toBeFalse();
});
