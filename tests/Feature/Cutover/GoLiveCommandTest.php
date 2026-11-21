<?php

use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\ORG\Services\GoLiveRecording;
use App\Modules\ORG\Services\NumberingGoLive;
use App\Modules\ORG\Services\NumberingState;
use Database\Factories\OPS\ImportBatchFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-077; PRQ-07; HO-32; NM-03; INFRASTRUCTURE §4.2, DIR-101 F3): the operator's go-live command records go-live
// once — the store's file created exclusively, then one audit event with the operator's identity and no application
// actor —, refuses a second recording that writes nothing, repairs a recording whose event did not commit from its
// file, refuses before every batch is committed or aborted and outside a console, and no route reaches it; the number
// allocator reads go-live from that file, unrecorded until it exists.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->file = sys_get_temp_dir().'/go-live-'.bin2hex(random_bytes(6)).'/numbering-go-live';
    config(['numbering.go_live_file' => $this->file]);
});

afterEach(function () {
    File::deleteDirectory(dirname($this->file));
});

/** @return list<object> */
function goLiveEvents(): array
{
    return DB::table('audit_events')->where('action', GoLiveRecording::ACTION)->get()->all();
}

it('records go-live once from the console with the operator and no application actor, and a second recording writes nothing (criteria 1, 8)', function () {
    expect(app(NumberingGoLive::class)->day())->toBeNull()->and(app(NumberingState::class)->goLive())->toBeNull();

    $this->artisan('numbering:go-live', ['day' => '2026-02-01', '--operator' => 'Yusuf Operator'])->expectsOutputToContain('Go-live recorded: 2026-02-01')->assertExitCode(0);
    [$event] = goLiveEvents();
    $metadata = json_decode((string) $event->metadata, true);

    expect(file_get_contents($this->file))->toBe("2026-02-01\n")
        ->and(fileperms($this->file) & 0777)->toBe(0600)
        ->and(app(NumberingGoLive::class)->day())->toBe('2026-02-01')
        ->and(app(NumberingState::class)->goLive())->toBe('2026-02-01')
        ->and($event->actor_user_id)->toBeNull()
        ->and($event->authority_class)->toBe('SYSTEM')
        ->and($metadata['operator']['name'] ?? null)->toBe('Yusuf Operator')
        ->and($metadata['go_live'] ?? null)->toBe('2026-02-01')
        ->and($metadata['repaired'] ?? null)->toBeFalse();

    $this->artisan('numbering:go-live', ['day' => '2026-03-01', '--operator' => 'Yusuf Operator'])->expectsOutputToContain('recorded already: 2026-02-01')->assertExitCode(1);

    expect(goLiveEvents())->toHaveCount(1)->and(file_get_contents($this->file))->toBe("2026-02-01\n");
});

it('repairs a recording whose event did not commit by writing it from the file, once (DIR-101 F3)', function () {
    mkdir(dirname($this->file), 0700, true);
    file_put_contents($this->file, "2026-02-01\n");

    $this->artisan('numbering:go-live', ['day' => '2026-02-02', '--operator' => 'Yusuf Operator'])->expectsOutputToContain('holds 2026-02-01')->assertExitCode(1);
    expect(goLiveEvents())->toBe([]);

    $this->artisan('numbering:go-live', ['--operator' => 'Yusuf Operator'])->expectsOutputToContain('written from its file: 2026-02-01')->assertExitCode(0);
    $this->artisan('numbering:go-live', ['--operator' => 'Yusuf Operator'])->expectsOutputToContain('recorded already')->assertExitCode(1);

    expect(goLiveEvents())->toHaveCount(1)
        ->and(json_decode((string) goLiveEvents()[0]->metadata, true)['repaired'] ?? null)->toBeTrue()
        ->and(file_get_contents($this->file))->toBe("2026-02-01\n");
});

it('refuses a day that is not real, a run while a batch is uncommitted, a run without the operator and a run outside a console (criteria 1, 3)', function () {
    $this->artisan('numbering:go-live', ['day' => '2026-02-30', '--operator' => 'Yusuf Operator'])->expectsOutputToContain('real Y-m-d')->assertExitCode(1);
    $this->artisan('numbering:go-live', ['day' => '2026-02-01'])->expectsOutputToContain('--operator')->assertExitCode(1);

    $batch = ImportBatchFactory::new()->create(['state' => 'SIGNED_OFF']);
    $this->artisan('numbering:go-live', ['day' => '2026-02-01', '--operator' => 'Yusuf Operator'])->expectsOutputToContain('neither committed nor aborted')->assertExitCode(1);
    DB::table('import_batches')->where('id', $batch->id)->update(['state' => 'ABORTED']);

    $operator = OperatorIdentity::stated('Yusuf Operator');
    $console = (fn () => $this->isRunningInConsole)->call(app());
    (fn () => $this->isRunningInConsole = false)->call(app());

    try {
        expect(fn () => app(GoLiveRecording::class)->record('2026-02-01', $operator))->toThrow(LogicException::class, 'only in a console');
    } finally {
        (fn () => $this->isRunningInConsole = $console)->call(app());
    }

    expect(file_exists($this->file))->toBeFalse()->and(goLiveEvents())->toBe([]);
});

it('is reached by no route (criterion 1; TL-06)', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($route): string => $route->uri().' '.($route->getActionName()));

    expect($routes->filter(fn (string $route): bool => str_contains($route, 'go-live') || str_contains($route, 'GoLive') || str_contains($route, 'RecordGoLive'))->all())->toBe([])
        ->and(collect(File::allFiles(app_path('Http')))->filter(fn ($file): bool => str_contains((string) file_get_contents($file->getPathname()), 'GoLiveRecording'))->all())->toBe([]);
});
