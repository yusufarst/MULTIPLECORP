<?php

use App\Http\Controllers\RecordCspReport;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

// TL-04 (TASK-072; SECURITY WS-08, EN-03, LG-04): ROUTE-278's and INT-278's CSP report endpoint — registered on staging
// only, throttled and size-capped, each refusal without a body, the report in the technical log with its addresses
// reduced to their paths and no business record —, and WS-08's report-only stage on staging.

function postCspReport(string $body): TestResponse
{
    return test()->call('POST', '/laporan-csp', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], $body);
}

it('is absent outside staging (INT-278)', function () {
    expect(app('router')->getRoutes()->getByName('csp-report'))->toBeNull();
});

it('records a report with 204, its addresses without their query strings, and declares EN-03\'s exception (INT-278)', function () {
    RecordCspReport::register();
    app('router')->getRoutes()->refreshNameLookups();
    Log::spy();

    $report = json_encode(['csp-report' => [
        'document-uri' => 'https://staging.example.invalid/masuk?token=secret#x',
        'violated-directive' => "script-src 'self'",
        'blocked-uri' => 'inline',
        'source-file' => 'https://staging.example.invalid/build/app.js?v=1',
        'line-number' => 12,
        'original-policy' => 'ignored',
    ]], JSON_THROW_ON_ERROR);

    $response = postCspReport($report);

    expect($response->status())->toBe(204)
        ->and($response->getContent())->toBe('')
        ->and($response->headers->getCookies())->toBe([])
        ->and(app('router')->getRoutes()->getByName('csp-report')?->gatherMiddleware())->toBe(['access:none,csp_report']);
    Log::shouldHaveReceived('warning')->once()->with('CSP report (WS-08).', ['report' => [
        'document-uri' => 'https://staging.example.invalid/masuk',
        'violated-directive' => "script-src 'self'",
        'blocked-uri' => 'inline',
        'source-file' => 'https://staging.example.invalid/build/app.js',
        'line-number' => 12,
    ]]);
});

it('refuses a report over its size without a body (INT-278)', function () {
    RecordCspReport::register();
    Log::spy();

    $response = postCspReport(str_repeat('x', RecordCspReport::MAX_BYTES + 1));

    expect($response->status())->toBe(413)
        ->and($response->getContent())->toBe('');
    Log::shouldNotHaveReceived('warning');
});

it('refuses a client over its rate without a body (INT-278)', function () {
    RecordCspReport::register();

    for ($i = 0; $i < RecordCspReport::PER_MINUTE; $i++) {
        expect(postCspReport('{}')->status())->toBe(204);
    }

    $response = postCspReport('{}');

    expect($response->status())->toBe(429)
        ->and($response->getContent())->toBe('');
});

it('sends the policy report-only with its report address on staging, enforced once switched (WS-08)', function () {
    $environment = app()['env'];
    app()['env'] = 'staging';

    try {
        $answer = fn (): Response => (new SecurityHeaders)->handle(Request::create('/masuk'), fn (): Response => new Response('ok'));

        config(['app.csp_report_only' => true]);
        $reportOnly = $answer();
        config(['app.csp_report_only' => false]);
        $enforced = $answer();
    } finally {
        app()['env'] = $environment;
    }

    expect($reportOnly->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($reportOnly->headers->get('Content-Security-Policy-Report-Only'))->toEndWith('; report-uri /laporan-csp')
        ->and($enforced->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse()
        ->and($enforced->headers->get('Content-Security-Policy'))->toEndWith('; report-uri /laporan-csp');
});
