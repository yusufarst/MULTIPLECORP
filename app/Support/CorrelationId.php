<?php

namespace App\Support;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * The correlation id of ARCHITECTURE §17 (SECURITY LG-01, LG-04): one per request, started by the global
 * middleware, and one per console run; it is kept in the framework's context, so it flows into the jobs a
 * request dispatches, into every technical log line and into the audit and security events.
 */
final class CorrelationId
{
    public const CONTEXT_KEY = 'correlation_id';

    /** Starts a new correlation id for the request or run, replacing any earlier one. */
    public static function start(): string
    {
        $id = (string) Str::uuid7();

        Context::add(self::CONTEXT_KEY, $id);

        return $id;
    }

    /** The current correlation id, started on first use outside a request. */
    public static function current(): string
    {
        $id = Context::get(self::CONTEXT_KEY);

        return is_string($id) && $id !== '' ? $id : self::start();
    }
}
