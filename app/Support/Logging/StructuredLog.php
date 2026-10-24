<?php

namespace App\Support\Logging;

use App\Support\Redaction;
use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;
use Throwable;

/**
 * The `tap` of the file log channels (SECURITY LG-03, LG-04): each line one JSON record carrying the
 * correlation id the framework's context adds, sensitive keys redacted and line breaks neutralized in
 * the message and in every logged value. It acts on the channel's handlers, so a stack channel that
 * gathers them keeps both.
 */
final class StructuredLog
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof Monolog) {
            return;
        }

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter(new JsonFormatter);
            }

            if ($handler instanceof ProcessableHandlerInterface) {
                $handler->pushProcessor(static function (LogRecord $record): LogRecord {
                    // A reported exception's message is the record's message too.
                    $exception = $record->context['exception'] ?? null;

                    return $record->with(
                        message: $exception instanceof Throwable && $record->message === $exception->getMessage()
                            ? Redaction::exceptionMessage($exception)
                            : Redaction::neutralize($record->message),
                        context: Redaction::forLog($record->context),
                        extra: Redaction::forLog($record->extra),
                    );
                });
            }
        }
    }
}
