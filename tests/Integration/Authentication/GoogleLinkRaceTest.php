<?php

use App\Modules\IAM\Authentication\GoogleIdentities;
use App\Modules\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\TwoFactor;

// TL-05 (TASK-019; BD-19; H6-14; C-64; M-41): two accounts linking one Google subject together, each in a real
// PostgreSQL session of its own with a barrier before the insert — no common row lock orders them, so the partial
// UNIQUE key does: one link, the other GOOGLE_LINK_CONFLICT.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

it('links one subject to one account when two accounts race for it (BD-19; C-64)', function () {
    $first = TwoFactor::enrol(User::factory()->create())->id;
    $second = TwoFactor::enrol(User::factory()->create())->id;
    $results = Sessions::run([
        'first' => static function () use ($first): string {
            $user = User::query()->findOrFail($first);
            Barrier::await('both accounts are ready');

            return app(GoogleIdentities::class)->link($user, 'sub-sama', null);
        },
        'second' => static function () use ($second): string {
            $user = User::query()->findOrFail($second);
            Barrier::await('both accounts are ready');

            return app(GoogleIdentities::class)->link($user, 'sub-sama', null);
        },
    ]);

    expect(collect($results)->sort()->values()->all())->toBe([GoogleIdentities::CONFLICT, GoogleIdentities::LINKED])
        ->and(DB::table('user_external_identities')->where('provider_subject', 'sub-sama')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'IAM.GoogleLinked')->count())->toBe(1);
});

it('answers the unique key\'s refusal as the conflict when the other link commits between the read and the insert (C-64; LR-08)', function () {
    $first = TwoFactor::enrol(User::factory()->create())->id;
    $second = TwoFactor::enrol(User::factory()->create())->id;

    $results = Sessions::run([
        'first' => static function () use ($first): string {
            Barrier::await('the second has read');
            $outcome = app(GoogleIdentities::class)->link(User::query()->findOrFail($first), 'sub-sama', null);
            Barrier::await('the first has committed');

            return $outcome;
        },
        'second' => static function () use ($second): string {
            $read = false;
            DB::listen(static function ($query) use (&$read): void {
                if (! $read && str_contains($query->sql, 'from "user_external_identities"') && str_contains($query->sql, '"provider_subject"')) {
                    $read = true;
                    Barrier::await('the second has read');
                    Barrier::await('the first has committed');
                }
            });

            return app(GoogleIdentities::class)->link(User::query()->findOrFail($second), 'sub-sama', null);
        },
    ]);

    expect($results)->toBe(['first' => GoogleIdentities::LINKED, 'second' => GoogleIdentities::CONFLICT])
        ->and(DB::table('user_external_identities')->where('provider_subject', 'sub-sama')->count())->toBe(1);
});
