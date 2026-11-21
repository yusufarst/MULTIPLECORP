<?php

namespace App\Support\Monitoring;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one delivery adapter of OPI-05's channel (TASK-075; DIR-129): e-mail through the Owner's existing Cloudflare Email
 * Service to the verified recipients alone, each message a neutral signal name with its time in WIB — no S3 or S4 value
 * or secret (LG-03) —, its token a secret of SX-01 read from the environment. Unconfigured, nothing is sent and the
 * floor stays the in-app notice and the operator's daily look (DIR-079 CB-06). A failed delivery is logged, without its
 * token, and never raises another alert.
 */
final class AlertChannel
{
    public const DELIVERY = 'alert_delivery';

    public function configured(): bool
    {
        return is_string(config('monitoring.channel.url')) && config('monitoring.channel.url') !== '' && (array) config('monitoring.recipients') !== [];
    }

    public function deliver(string $signal, DateTimeInterface $at): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $time = CarbonImmutable::instance($at)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s').' WIB';

        try {
            $response = Http::timeout(config()->integer('monitoring.channel.timeout_seconds'))
                ->withToken(self::text(config('monitoring.channel.token')))
                ->acceptJson()
                ->post(self::text(config('monitoring.channel.url')), [
                    'from' => self::text(config('monitoring.channel.from')),
                    'to' => array_values((array) config('monitoring.recipients')),
                    'subject' => 'MULTIPLECORP alert: '.$signal,
                    'text' => "{$signal}\n{$time}\nMULTIPLECORP — see the heartbeat and the logs through the operator's access.",
                ]);
        } catch (Throwable $failure) {
            Log::warning('An alert could not be delivered (OPI-05).', ['signal' => $signal, 'error' => class_basename($failure), self::DELIVERY => true]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('An alert could not be delivered (OPI-05).', ['signal' => $signal, 'status' => $response->status(), self::DELIVERY => true]);

            return false;
        }

        return true;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
