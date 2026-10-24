<?php

namespace App\Support\SecurityLog;

/**
 * The user-agent summary of a security event (SECURITY LG-02, LG-03): the browser family with its major
 * version and the platform family — enough to tell one device from another in the log, never the full
 * header, which can fingerprint a person or carry injected text.
 */
final class UserAgentSummary
{
    /** Browser families, the first match winning: Edge and Opera also announce Chrome, Chrome announces Safari. */
    private const BROWSERS = [
        'Edge' => '/\bEdg(?:e|A|iOS)?\/(\d+)/',
        'Opera' => '/\b(?:OPR|Opera)\/(\d+)/',
        'Samsung Internet' => '/\bSamsungBrowser\/(\d+)/',
        'Firefox' => '/\b(?:Firefox|FxiOS)\/(\d+)/',
        'Chrome' => '/\b(?:Chrome|CriOS)\/(\d+)/',
        'Safari' => '/\bVersion\/(\d+)[\d.]* (?:Mobile\/\S+ )?Safari\//',
    ];

    /** Platform families, the first match winning: Android announces Linux, iOS announces Mac OS X. */
    private const PLATFORMS = [
        'Android' => '/\bAndroid\b/',
        'iOS' => '/\b(?:iPhone|iPad|iPod)\b/',
        'Windows' => '/\bWindows\b/',
        'macOS' => '/\bMacintosh\b/',
        'ChromeOS' => '/\bCrOS\b/',
        'Linux' => '/\bLinux\b/',
    ];

    public static function of(?string $userAgent): ?string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return null;
        }

        $browser = 'Other';

        foreach (self::BROWSERS as $family => $pattern) {
            if (preg_match($pattern, $userAgent, $match) === 1) {
                $browser = "{$family} {$match[1]}";

                break;
            }
        }

        $platform = 'Other';

        foreach (self::PLATFORMS as $family => $pattern) {
            if (preg_match($pattern, $userAgent) === 1) {
                $platform = $family;

                break;
            }
        }

        return "{$browser} on {$platform}";
    }
}
