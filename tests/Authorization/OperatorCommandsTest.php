<?php

use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\IAM\Recovery\Installation;
use App\Modules\IAM\Recovery\KeyCompromise;
use App\Modules\IAM\Recovery\OwnerRecoveryLinks;
use App\Modules\IAM\Recovery\OwnerRestoration;
use App\Modules\IAM\Recovery\Reencryption;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\OwnerTrail;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-020; SECURITY SX-04; AU-14, AU-21, AU-25): no route reaches an operator command — every one runs only in
// a console, by the operator —, and the commands add no capability, so the OWNER_ONLY rules stay unchanged.

uses(RefreshDatabase::class);

it('has no route whose action or middleware reaches the recovery layer or an operator command (SX-04)', function () {
    $reached = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
        $uses = $route->getAction('uses');
        $names = [is_string($uses) ? $uses : '', ...array_map(fn ($middleware) => is_string($middleware) ? $middleware : '', $route->gatherMiddleware())];

        return collect($names)->contains(fn (string $name) => Str::contains($name, ['Modules\\IAM\\Recovery', 'Console\\Commands']));
    });

    expect($reached->map->uri()->values()->all())->toBe([]);
});

it('registers each operator command for the console only, adding no capability (AU-14; AU-21; AU-25; OWNER_ONLY unchanged)', function () {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('iam:install-owner', 'iam:owner-recovery-link', 'iam:key-compromise', 'iam:reencrypt-secrets', 'iam:restore-owner', 'iam:end-recovery')
        ->and(collect(CapabilityCatalogue::codes())->filter(fn (string $code) => Str::contains($code, ['recovery', 'incident', 'install', 'restore']))->values()->all())->toBe([]);
});

it('refuses every recovery service called outside a console, writing nothing (SX-04)', function () {
    $operator = OwnerTrail::operator();
    $calls = [
        fn () => app(Installation::class)->install('Ratna', 'ratna@contoh.test', $operator),
        fn () => app(OwnerRecoveryLinks::class)->issue('ratna@contoh.test', 'Uji', $operator),
        fn () => app(KeyCompromise::class)->run('2026-10-01T00:00:00Z', $operator),
        fn () => app(Reencryption::class)->run($operator),
        fn () => app(OwnerRestoration::class)->restore('ratna@contoh.test', '2026-10-01T00:00:00Z', $operator),
        fn () => app(OwnerRestoration::class)->endRecovery('2026-10-01T00:00:00Z', $operator),
    ];
    $console = (fn () => $this->isRunningInConsole)->call(app());
    (fn () => $this->isRunningInConsole = false)->call(app());

    try {
        foreach ($calls as $call) {
            expect($call)->toThrow(LogicException::class, 'only in a console');
        }
    } finally {
        (fn () => $this->isRunningInConsole = $console)->call(app());
    }

    expect(DB::table('audit_events')->count())->toBe(0)->and(DB::table('users')->count())->toBe(0);
});
