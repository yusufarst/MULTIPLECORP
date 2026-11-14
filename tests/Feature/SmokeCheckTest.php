<?php

use App\Support\Heartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-072; PRQ-03; GAP-013; INFRASTRUCTURE §6; REL-11): the read-only smoke checks report each part of the business
// heartbeat, fail on a job failed in the last day or a scheduled task without a recent success, and never claim the backups.

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (array_keys(Heartbeat::TASKS) as $task) {
        (Heartbeat::stamp($task))();
    }
});

it('passes with every scheduled task recently successful and no failure, the backups NOT CHECKED', function () {
    $this->artisan('ops:smoke', ['--disk-min' => 0])
        ->expectsOutputToContain('PASS database')
        ->expectsOutputToContain('PASS failed jobs')
        ->expectsOutputToContain('PASS renditions')
        ->expectsOutputToContain('PASS scheduler guards:reconcile')
        ->expectsOutputToContain('NOT CHECKED backups')
        ->assertExitCode(0);
});

it('fails on a job failed in the last day', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Test', 'failed_at' => now()->subHour(),
    ]);

    $this->artisan('ops:smoke', ['--disk-min' => 0])
        ->expectsOutputToContain('FAIL failed jobs — 1 in the last 24 hours')
        ->assertExitCode(1);
});

it('fails on a scheduled task without a recorded success', function () {
    Cache::forget('heartbeat:guards:reconcile');

    $this->artisan('ops:smoke', ['--disk-min' => 0])
        ->expectsOutputToContain('FAIL scheduler guards:reconcile — no success recorded')
        ->assertExitCode(1);
});
