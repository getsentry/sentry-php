<?php

declare(strict_types=1);

namespace Sentry\Logs;

use Sentry\Client;
use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;
use Sentry\State\Scope;

/**
 * @internal
 */
final class LogsAggregator
{
    private $logs = [];

    public function add(
        LogLevel $level,
        string $message,
    ): void {
        $timestamp = time();
        $traceId = null;

        $hub = SentrySdk::getCurrentHub();
        $span = $hub->getSpan();
        if ($span !== null) {
            $traceId = $span->getTraceId();
        }

        $log = [
            'timestamp' => $timestamp,
            'trace_id' => (string) $traceId,
            'level' => (string) $level,
            'body' => $message,
            'attributes' => [],
        ];

        $hub = SentrySdk::getCurrentHub();
        $client = $hub->getClient();

        $hub = SentrySdk::getCurrentHub();
        $client = $hub->getClient();

        if ($client !== null) {
            $options = $client->getOptions();

            // @TODO add a proper attributes abstraction we can later re-use for spans
            $defaultAttributes = [
                'sentry.environment' => [
                    'type' => 'string',
                    'value' => $options->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT,
                ],
                'sentry.sdk.name' => [
                    'type' => 'string',
                    // @FIXME Won't work for Laravel & Symfony
                    'value' => Client::SDK_IDENTIFIER,
                ],
                'sentry.sdk.version' => [
                    'type' => 'string',
                    // @FIXME Won't work for Laravel & Symfony
                    'value' => Client::SDK_VERSION,
                ],
                // @TODO Add a `server.address` attribute, same value as `server_name` on errors
            ];

            $release = $options->getRelease();
            if ($release !== null) {
                $defaultAttributes['sentry.release'] = [
                    'type' => 'string',
                    'value' => $options->getRelease(),
                ];
            }

            $hub->configureScope(function (Scope $scope) use (&$defaultAttributes) {
                $span = $scope->getSpan();
                if ($span !== null) {
                    $defaultAttributes['sentry.trace.parent_span_id'] = [
                        'type' => 'string',
                        'value' => (string) $span->getSpanId(),
                    ];
                }
            });

            $log['attributes'] = $defaultAttributes;
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
