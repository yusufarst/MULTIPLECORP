<?php

use App\Console\Commands\BackupCopy;
use App\Console\Commands\BackupCopyKeys;
use App\Console\Commands\BackupFetch;
use App\Console\Commands\BackupRestore;
use App\Console\Commands\BackupRun;
use App\Console\Commands\BackupStatus;
use Illuminate\Support\Facades\Artisan;

// TL-04 (TASK-074; SX-04, SX-07; BKR-06): the backup, copy, fetch, key and restore commands are the operator's alone —
// registered for the console and refusing to run outside one, so no request reaches a backup —, and the restore
// refuses a run without the operator's stated name.

it('registers each backup command for the operator\'s console', function () {
    $commands = Artisan::all();

    foreach (['backup:run' => BackupRun::class, 'backup:status' => BackupStatus::class, 'backup:copy' => BackupCopy::class, 'backup:fetch' => BackupFetch::class,
        'backup:copy-keys' => BackupCopyKeys::class, 'backup:restore' => BackupRestore::class] as $name => $class) {
        expect($commands[$name] ?? null)->toBeInstanceOf($class);
    }
});

it('refuses each backup command outside a console (SX-04; BKR-06)', function () {
    $console = (fn () => $this->isRunningInConsole)->call(app());
    (fn () => $this->isRunningInConsole = false)->call(app());

    try {
        foreach (['backup:run' => [], 'backup:status' => [], 'backup:copy' => [], 'backup:fetch' => ['copy' => '/x'], 'backup:copy-keys' => [],
            'backup:restore' => ['generation' => 'x', '--operator' => 'Yusuf']] as $name => $arguments) {
            expect(fn () => Artisan::call($name, $arguments))->toThrow(LogicException::class, 'only in a console');
        }
    } finally {
        (fn () => $this->isRunningInConsole = $console)->call(app());
    }
});

it('refuses a restore without the operator\'s stated name, a fetch without its secret key file, and a key pair over an existing file', function () {
    $this->artisan('backup:restore', ['generation' => '20261120T200000Z-abc1234', '--database' => 'baru', '--files-root' => sys_get_temp_dir().'/baru'])
        ->expectsOutputToContain('--operator')->assertExitCode(2);
    $this->artisan('backup:fetch', ['copy' => sys_get_temp_dir()])->expectsOutputToContain('--secret-key-file')->assertExitCode(2);

    $existing = tempnam(sys_get_temp_dir(), 'kunci-');

    try {
        $this->artisan('backup:copy-keys', ['--secret-out' => $existing])->expectsOutputToContain('--secret-out')->assertExitCode(2);
    } finally {
        @unlink((string) $existing);
    }
});
