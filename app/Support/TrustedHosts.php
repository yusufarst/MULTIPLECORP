<?php

namespace App\Support;

/**
 * WS-11 (TASK-072; TM-01; TM-07): the Host header trusted only for the configured domains, each matched whole — never a
 * subdomain nor a look-alike —, the application URL's own host when none is configured, so throttling keys and absolute
 * links cannot be spoofed by a forged Host.
 */
final class TrustedHosts
{
    /**
     * @param  array<mixed>  $hosts  the configured domains; anything but a non-empty string is ignored
     * @return list<string> the patterns Symfony's request matches the Host header against
     */
    public static function patterns(array $hosts, string $applicationUrl): array
    {
        $named = array_values(array_filter(array_map(fn (mixed $host): string => is_string($host) ? trim($host) : '', $hosts), fn (string $host): bool => $host !== ''));

        if ($named === []) {
            $own = parse_url($applicationUrl, PHP_URL_HOST);
            $named = is_string($own) && $own !== '' ? [$own] : [];
        }

        return array_map(fn (string $host): string => '^'.preg_quote(strtolower($host), '/').'$', $named);
    }
}
