<?php

declare(strict_types=1);

namespace Sentry\Logs;

use Sentry\Client;
use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;

/**
 * @phpstan-import-type AttributeValue from LogAttribute
 *
 * @internal
 */
final class LogsAggregator
{
    /**
     * @var Log[]
     */
    private $logs = [];

    /**
     * @param string                        $message    see sprintf for a description of format
     * @param array<int, string|int|float>  $values     see sprintf for a description of values
     * @param array<string, AttributeValue> $attributes additional attributes to add to the log
     */
    public function add(
        LogLevel $level,
        string $message,
        array $values = [],
        array $attributes = []
    ): void {
        $timestamp = microtime(true);

        $hub = SentrySdk::getCurrentHub();
        $client = $hub->getClient();

        // There is no need to continue if there is no client or if logs are disabled
        // @TODO: This might needs to be re-evaluated when we send logs to allow logging to start before init'ing the client
        if ($client === null || !$client->getOptions()->getEnableLogs()) {
            return;
        }

        $span = $hub->getSpan();
        $traceId = $span !== null ? $span->getTraceId() : null;

        $options = $client->getOptions();

        // @FIXME The SDK name and version won't work for Laravel & Symfony and other SDKs, needs to be more flexible
        $log = (new Log($timestamp, (string) $traceId, $level, vsprintf($message, $values)))
            ->setAttribute('sentry.message.template', $message)
            ->setAttribute('sentry.environment', $options->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT)
            ->setAttribute('sentry.sdk.name', Client::SDK_IDENTIFIER)
            ->setAttribute('sentry.sdk.version', Client::SDK_VERSION);

        foreach ($values as $key => $value) {
            $log->setAttribute("sentry.message.parameter.{$key}", $value);
        }

        foreach ($attributes as $key => $value) {
            $log->setAttribute($key, $value);
        }

        if ($span !== null) {
            $log->setAttribute('sentry.trace.parent_span_id', (string) $span->getSpanId());
        }

        // @TODO: Do we want to add the following attributes when we send the log rather then when we create the log?
        if ($options->getServerName() !== null) {
            $log->setAttribute('sentry.server.address', $options->getServerName());
        }

        if ($options->getRelease() !== null) {
            $log->setAttribute('sentry.release', $options->getRelease());
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
