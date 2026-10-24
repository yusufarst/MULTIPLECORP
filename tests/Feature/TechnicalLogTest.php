<?php

use App\Support\CorrelationId;
use App\Support\Redaction;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// TL-14 (TASK-010): the technical logs of SECURITY LG-03, LG-04 and H9-08 — structured lines with the
// correlation id, no secret, no forged line, a file readable by its owner only, a bounded rotation — and
// the correlation id every request starts (ARCHITECTURE §17).

beforeEach(function () {
    $this->logs = sys_get_temp_dir().'/multiplecorp-logs-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->logs);
});

afterEach(function () {
    File::deleteDirectory($this->logs);
});

/**
 * The lines the given file channel writes for the callback.
 *
 * @return list<string>
 */
function linesOf(string $channel, string $directory, Closure $write): array
{
    config(["logging.channels.{$channel}.path" => "{$directory}/laravel.log"]);
    Log::forgetChannel($channel);

    $write(Log::channel($channel));

    $files = glob("{$directory}/laravel*.log");

    expect($files)->toHaveCount(1);

    return array_values(array_filter(explode("\n", (string) file_get_contents($files[0]))));
}

it('writes one JSON line per record with the correlation id and without secrets or forged lines (LG-03, LG-04)', function (string $channel) {
    $correlationId = CorrelationId::start();

    $lines = linesOf($channel, $this->logs, fn ($log) => $log->warning("Login failed\nINFO forged entry", [
        'password' => 'kata-sandi-salah',
        'remember_token' => 'remember-me-token',
        'headers' => ['authorization' => 'Bearer abc.def', 'cookie' => 'multiplecorp_session=s3ss10n'],
        'note' => "line one\r\nline two",
    ]));

    expect($lines)->toHaveCount(1);

    $record = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);

    expect($record['message'])->toBe('Login failed\nINFO forged entry')
        ->and($record['context'])->toBe([
            'password' => Redaction::MARK,
            'remember_token' => Redaction::MARK,
            'headers' => ['authorization' => Redaction::MARK, 'cookie' => Redaction::MARK],
            'note' => 'line one\r\nline two',
        ])
        ->and($record['extra'][CorrelationId::CONTEXT_KEY])->toBe($correlationId)
        ->and($lines[0])->not->toContain('kata-sandi-salah', 'remember-me-token', 'Bearer abc', 's3ss10n');
})->with(['daily', 'single']);

it('creates the log file readable and writable by its owner only (H9-08)', function (string $channel) {
    linesOf($channel, $this->logs, fn ($log) => $log->info('permission check'));

    expect(fileperms(glob("{$this->logs}/laravel*.log")[0]) & 0777)->toBe(0600);
})->with(['daily', 'single']);

it('keeps a bounded daily rotation in the environment template (LG-06)', function () {
    $template = (string) file_get_contents(base_path('.env.example'));

    expect($template)->toMatch('/^LOG_CHANNEL=stack$/m')
        ->and($template)->toMatch('/^LOG_STACK=daily$/m')
        ->and($template)->toMatch('/^LOG_DAILY_DAYS=14$/m')
        ->and(config('logging.channels.daily.max_files'))->toBeGreaterThan(0);
});

it('starts a new correlation id for every request and never takes the client\'s', function () {
    Route::get('/__test/correlation', fn () => Context::get(CorrelationId::CONTEXT_KEY));

    $first = $this->get('/__test/correlation', ['X-Correlation-Id' => 'forged'])->getContent();
    $second = $this->get('/__test/correlation')->getContent();

    expect($first)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and($second)->not->toBe($first);
});
