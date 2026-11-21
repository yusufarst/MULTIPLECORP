<?php

namespace App\Support\Monitoring;

use App\Support\Redaction;

/**
 * Which log records are alerts, and the neutral signal name each one sends (TASK-075; INFRASTRUCTURE §6; HO-25; LG-03;
 * LG-07; H9-10; H9-15). Every record at CRITICAL or above — a backup, copy or cleanup failure (BK-10), an exhausted
 * rendition (JB-01), a missing guard (GU-02), a security signal of LG-07 — is one, by its own static sentence; below it,
 * HO-25's signals are named here: a deadlock, a reconciliation difference, an unverified guard family and a FAILED
 * access change. The name carries no context value — no id, address, amount or secret (S3, S4) —, only the sentence,
 * neutralized and bounded.
 */
final class AlertSignals
{
    public const CRITICAL = 500;

    /** HO-25's signals below CRITICAL: the record's sentence prefix => its signal name. */
    public const NAMED = [
        'A command met a deadlock' => 'HO-25 deadlock (40P01)',
        'A guard differs from its facts' => 'HO-25 guard reconciliation difference',
        'A guard family could not be verified' => 'HO-25 guard family not verified',
    ];

    /** A FAILED command of these types is an access change (HO-25). */
    public const ACCESS_CHANGES = '/^(IAM|ADM)\.\w*(Role|Grant|Capabilit|Permission|Access|Activate|Deactivate|Owner|User)/';

    /** @param  array<array-key, mixed>  $context */
    public static function classify(int $level, string $message, array $context): ?string
    {
        foreach (self::NAMED as $prefix => $signal) {
            if (str_starts_with($message, $prefix)) {
                return $signal;
            }
        }

        if (str_starts_with($message, 'A command failed') && is_string($context['command_type'] ?? null) && preg_match(self::ACCESS_CHANGES, $context['command_type']) === 1) {
            return 'HO-25 failed access change';
        }

        return $level >= self::CRITICAL ? self::neutral($message) : null;
    }

    /** The sentence alone, neutralized and bounded: never a value from the record's context. */
    public static function neutral(string $message): string
    {
        // A sentence is static, but one built with a value still sends none: addresses and long numbers are masked.
        $text = (string) preg_replace(['/[^\s@]+@[^\s@]+/u', '/\d{6,}/'], ['[address]', '[number]'], trim(Redaction::neutralize($message)));

        return mb_strlen($text) > 160 ? mb_substr($text, 0, 157).'...' : $text;
    }
}
