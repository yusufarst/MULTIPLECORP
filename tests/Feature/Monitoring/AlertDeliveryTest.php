<?php

use App\Support\Monitoring\AlertChannel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// TL-04 (TASK-075; OPI-05; DIR-129; LG-03; SX-01): the delivery adapter against a simulated channel — the verified
// recipients, the token as the bearer header and never in the body, a neutral subject and text —, a failed delivery
// logged without the token, an unconfigured channel sending nothing, and an alert logged anywhere reaching the channel
// once per signal per window.

beforeEach(function () {
    config([
        'monitoring.channel.url' => 'https://email.example.invalid/send',
        'monitoring.channel.token' => 'token-uji-rahasia',
        'monitoring.channel.from' => 'peringatan@example.invalid',
        'monitoring.recipients' => ['operator@example.invalid', 'owner@example.invalid'],
        'monitoring.throttle_minutes' => 60,
    ]);
    Cache::flush();
});

it('sends a neutral signal to the verified recipients with the token as its bearer header alone (OPI-05; LG-03)', function () {
    Http::fake(['email.example.invalid/*' => Http::response(['success' => true])]);

    expect(app(AlertChannel::class)->deliver('HO-25 deadlock (40P01)', new DateTimeImmutable('2026-11-21 02:00:00 UTC')))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://email.example.invalid/send'
        && $request->hasHeader('Authorization', 'Bearer token-uji-rahasia')
        && $request['to'] === ['operator@example.invalid', 'owner@example.invalid']
        && $request['subject'] === 'MULTIPLECORP alert: HO-25 deadlock (40P01)'
        && str_contains((string) $request['text'], '2026-11-21 09:00:00 WIB')
        && ! str_contains($request->body(), 'token-uji-rahasia'));
});

it('logs a failed delivery without its token and sends nothing while the channel is unconfigured (TL-04)', function () {
    Http::fake(['email.example.invalid/*' => Http::response('gagal', 500)]);
    Log::spy();

    expect(app(AlertChannel::class)->deliver('HO-25 deadlock (40P01)', new DateTimeImmutable))->toBeFalse();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'could not be delivered')
        && $context['status'] === 500 && ! str_contains(json_encode($context), 'token-uji-rahasia'))->once();

    config(['monitoring.channel.url' => null]);
    Http::fake();
    expect(app(AlertChannel::class)->deliver('HO-25 deadlock (40P01)', new DateTimeImmutable))->toBeFalse();
    Http::assertNothingSent();
});

it('forwards an alert logged anywhere to the channel once per signal per window (INFRASTRUCTURE §6; LG-07)', function () {
    Http::fake(['email.example.invalid/*' => Http::response(['success' => true])]);

    Log::critical('Backup alert (BK-10): the backup run failed', ['reason' => 'rahasia 1234567']);
    Log::critical('Backup alert (BK-10): the backup run failed', ['reason' => 'rahasia 1234567']);
    Log::alert('A second-factor cooldown started (AU-05; LG-07).', ['user_id' => 918273645]);
    Log::warning('Something ordinary happened.');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request['subject'] === 'MULTIPLECORP alert: Backup alert (BK-10): the backup run failed' && ! str_contains($request->body(), 'rahasia'));
    Http::assertSent(fn (Request $request): bool => $request['subject'] === 'MULTIPLECORP alert: A second-factor cooldown started (AU-05; LG-07).' && ! str_contains($request->body(), '918273645'));
});
