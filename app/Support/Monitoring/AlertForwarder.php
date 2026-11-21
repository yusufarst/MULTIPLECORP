<?php

namespace App\Support\Monitoring;

use Illuminate\Support\Facades\Cache;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * The log channel that raises alerts (TASK-075; INFRASTRUCTURE §6; LG-07; BK-10): every record `AlertSignals` names is
 * sent through `AlertChannel` as its neutral signal name, once per signal per throttle window while it repeats. The
 * framework's logs themselves stay on the VPS; only the signal name leaves it (LG-05). A failure here never breaks the
 * code that logged.
 */
final class AlertForwarder extends AbstractProcessingHandler
{
    public function __construct()
    {
        parent::__construct(Level::Warning, true);
    }

    protected function write(LogRecord $record): void
    {
        if (($record->context[AlertChannel::DELIVERY] ?? false) === true) {
            return;
        }

        $signal = AlertSignals::classify($record->level->value, $record->message, $record->context);

        if ($signal === null) {
            return;
        }

        try {
            if (! Cache::add('alert:'.sha1($signal), 1, now()->addMinutes(max(1, config()->integer('monitoring.throttle_minutes'))))) {
                return;
            }
        } catch (Throwable) {
            // Without the store the alert still goes out: an outage is when it matters most.
        }

        try {
            app(AlertChannel::class)->deliver($signal, $record->datetime);
        } catch (Throwable) {
            // Delivery failures are logged by the channel; nothing propagates to the code that logged.
        }
    }
}
