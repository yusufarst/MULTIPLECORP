<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * The browser's CSP report on staging, ROUTE-278 and INT-278 (TASK-072; SECURITY WS-08, EN-03): registered on staging
 * only, outside the web group — no session, no cookie, no CSRF token a browser's report could carry —, throttled per
 * client and size-capped, each refusal without a body; the report goes to the technical log with its addresses reduced
 * to their paths, so no query string reaches the log (LG-04), and no business record is written.
 */
class RecordCspReport
{
    /** A browser's report is a few hundred bytes; anything larger is refused unread. */
    public const MAX_BYTES = 8192;

    /** Reports per client and minute. */
    public const PER_MINUTE = 30;

    /** The report's fields kept in the log. */
    private const FIELDS = ['document-uri', 'violated-directive', 'effective-directive', 'blocked-uri', 'source-file', 'line-number', 'disposition'];

    public static function register(): void
    {
        Route::post('/laporan-csp', self::class)->middleware('access:none,csp_report')->name('csp-report');
    }

    public function __invoke(Request $request): Response
    {
        $key = 'csp-report:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            return response('', 429);
        }

        RateLimiter::hit($key, 60);
        $body = $request->getContent();

        if (strlen($body) > self::MAX_BYTES) {
            return response('', 413);
        }

        $decoded = json_decode($body, true);
        $report = is_array($decoded) && is_array($decoded['csp-report'] ?? null) ? $decoded['csp-report'] : (is_array($decoded) ? $decoded : []);
        $kept = [];

        foreach (self::FIELDS as $field) {
            $value = $report[$field] ?? null;

            if (is_string($value) || is_int($value)) {
                $kept[$field] = is_string($value) && (str_contains($field, 'uri') || $field === 'source-file') ? self::path($value) : $value;
            }
        }

        Log::warning('CSP report (WS-08).', ['report' => $kept]);

        return response('', 204);
    }

    /** An address without its query string or fragment; a keyword such as `inline` stays as it is. */
    private static function path(string $address): string
    {
        $parts = parse_url($address);

        if ($parts === false || ! isset($parts['host'])) {
            return strtok($address, '?#') ?: '';
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '');
    }
}
