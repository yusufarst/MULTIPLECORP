<?php

namespace App\Modules\DOC\Http;

use App\Http\Middleware\SecurityHeaders;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SECURITY FL-07's two header profiles for a private file streamed by its controller: an uploaded or private
 * file as an attachment — an ASCII fallback name and a UTF-8 `filename*` without CR, LF, quotes or path
 * separators, nosniff, no store and a sandboxed policy. A system rendition's inline profile is ROUTE-225's (TASK-029).
 * `Content-Type` is always the stored, detected type.
 */
final class FileResponses
{
    /** @param resource $stream */
    public static function attachment($stream, string $mimeType, int $size, string $name): StreamedResponse
    {
        return self::stream($stream, $mimeType, $size, [
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, self::utf8($name), self::ascii($name)),
            'Content-Security-Policy' => "default-src 'none'; sandbox; frame-ancestors 'none'",
        ]);
    }

    /**
     * @param  resource  $stream
     * @param  array<string, string>  $headers
     */
    private static function stream($stream, string $mimeType, int $size, array $headers): StreamedResponse
    {
        return new StreamedResponse(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            ...$headers,
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $size,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            SecurityHeaders::FILE_PROFILE => '1',
        ]);
    }

    private static function utf8(string $name): string
    {
        $name = (string) preg_replace('/[\x00-\x1F\x7F"\'\/\\\\]+/u', '', $name);

        return $name === '' ? 'berkas' : $name;
    }

    private static function ascii(string $name): string
    {
        $ascii = (string) preg_replace('/[^A-Za-z0-9._ -]+/', '_', self::utf8($name));

        return trim($ascii) === '' ? 'berkas' : $ascii;
    }
}
