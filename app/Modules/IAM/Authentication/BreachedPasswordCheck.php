<?php

namespace App\Modules\IAM\Authentication;

use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * SECURITY AU-03 (2): the breached-password range check — the k-Anonymity query of the framework's `uncompromised`
 * rule, only the first five characters of the password's SHA-1 leaving the server, with response padding — under a
 * timeout of 5 s. When it fails or does not answer in time, the remote check is skipped and recorded as
 * PASSWORD_CHECK_SKIPPED, never a refusal (DIR-044 R-07). It runs during validation, so it always finishes before
 * the transaction that stores the password opens (H6-15).
 */
final class BreachedPasswordCheck implements UncompromisedVerifier
{
    public const ENDPOINT = 'https://api.pwnedpasswords.com/range/';

    public const TIMEOUT_SECONDS = 5;

    public function __construct(private readonly SecurityEventRecorder $events) {}

    /**
     * @param  array{value: string, threshold: int}  $data
     */
    public function verify($data): bool
    {
        $hash = strtoupper(sha1($data['value']));
        [$prefix, $suffix] = [substr($hash, 0, 5), substr($hash, 5)];

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->withHeaders(['Add-Padding' => 'true'])->get(self::ENDPOINT.$prefix);

            if (! $response->successful()) {
                return $this->skipped('STATUS_'.$response->status());
            }
        } catch (Throwable $e) {
            return $this->skipped(class_basename($e));
        }

        foreach (preg_split('/\R/', $response->body()) ?: [] as $line) {
            [$candidate, $count] = array_pad(explode(':', trim($line)), 2, '0');

            if ($candidate === $suffix && (int) $count > $data['threshold']) {
                return false;
            }
        }

        return true;
    }

    private function skipped(string $reason): bool
    {
        $this->events->record(SecurityEventKind::PasswordCheckSkipped, reasonCode: 'BREACHED_RANGE_'.strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '_', $reason) ?? 'UNAVAILABLE', 0, 40)));

        return true;
    }
}
