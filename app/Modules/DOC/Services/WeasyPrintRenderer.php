<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Contracts\DocumentRenderer;
use App\Modules\DOC\Contracts\RenderFailed;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * FL-08's renderer (DIR-099 C1): WeasyPrint run through `resources/renderer/render.py` as an external process
 * started with an argument array — never a shell string —, with a time limit here and an address-space limit
 * set by the script, its environment emptied of the application's secrets. The script refuses the network and
 * every file outside the bundled assets and this render's asset directory.
 */
final class WeasyPrintRenderer implements DocumentRenderer
{
    public function render(string $html, ?string $assetDirectory = null): string
    {
        $assets = config()->string('files.renderer.assets');
        $arguments = [config()->string('files.renderer.python'), config()->string('files.renderer.script'), $assets];

        if ($assetDirectory !== null) {
            $arguments[] = $assetDirectory;
        }

        $home = sys_get_temp_dir();
        $process = new Process($arguments, null, [
            // Every inherited variable removed — no database, cache or key value of the application reaches it —,
            // then only what the process needs.
            ...array_fill_keys(array_filter(array_keys(getenv() + $_SERVER + $_ENV), is_string(...)), false),
            'PATH' => '/usr/local/bin:/usr/bin:/bin',
            'HOME' => $home,
            'XDG_CACHE_HOME' => $home,
            'LANG' => 'C.UTF-8',
            'RENDERER_MEMORY_BYTES' => (string) (config()->integer('files.renderer.memory_megabytes') * 1024 * 1024),
            'RENDERER_CPU_SECONDS' => (string) config()->integer('files.renderer.timeout_seconds'),
        ], $html, config()->integer('files.renderer.timeout_seconds'));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new RenderFailed('The renderer exceeded its time limit.');
        }

        $pdf = $process->getOutput();

        if (! $process->isSuccessful() || ! str_starts_with($pdf, '%PDF-')) {
            throw new RenderFailed('The renderer failed: '.self::summary($process->getErrorOutput()));
        }

        return $pdf;
    }

    public function version(): string
    {
        return 'weasyprint-68.1';
    }

    /** The error's last line, without paths: enough to tell a refused resource from a crash, never content. */
    private static function summary(string $error): string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $error))));
        $last = $lines === [] ? 'no output' : $lines[count($lines) - 1];

        return mb_substr((string) preg_replace('~(/[^\s:]+)+~', '[path]', $last), 0, 200);
    }
}
