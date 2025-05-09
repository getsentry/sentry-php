<?php

declare(strict_types=1);

namespace Sentry\Logs;

use Sentry\Client;
use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;

/**
 * @internal
 */
final class LogsAggregator
{
    /**
     * @var Log[]
     */
    private $logs = [];

    public function add(
        LogLevel $level,
        string $message
    ): void {
        $timestamp = microtime(true);

        $hub = SentrySdk::getCurrentHub();
        $client = $hub->getClient();

        // There is no need to continue if there is no client or if logs are disabled
        // @TODO: This might needs to be re-evaluated when we send logs to allow loggin to start before init'ing the client
        if ($client === null || !$client->getOptions()->getEnableLogs()) {
            return;
        }

        $span = $hub->getSpan();
        $traceId = $span !== null ? $span->getTraceId() : null;

        $options = $client->getOptions();

        // @TODO add a proper attributes abstraction we can later re-use for spans
        // @TODO Add a `server.address` attribute, same value as `server_name` on errors
        // @FIXME The SDK name and version won't work for Laravel & Symfony and other SDKs, needs to be more flexible
        $log = (new Log($timestamp, (string) $traceId, $level, $message))
            ->setAttribute('sentry.environment', $options->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT)
            ->setAttribute('sentry.sdk.name', Client::SDK_IDENTIFIER)
            ->setAttribute('sentry.sdk.version', Client::SDK_VERSION);

        $release = $options->getRelease();
        if ($release !== null) {
            $log->setAttribute('sentry.release', $release);
        }

        if ($span !== null) {
            $log->setAttribute('sentry.trace.parent_span_id', (string) $span->getSpanId());
        }

        $this->logs[] = $log;
    }

    public function flush(): ?EventId
    {
        if (empty($this->logs)) {
            return null;
        }

        $hub = SentrySdk::getCurrentHub();
        $event = Event::createLogs()->setLogs($this->logs);

        $this->logs = [];

        return $hub->captureEvent($event);
    }
}
