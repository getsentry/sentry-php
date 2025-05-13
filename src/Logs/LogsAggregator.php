<?php

declare(strict_types=1);

namespace Sentry\Logs;

use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;
use Sentry\State\Scope;

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
     * @param string                       $message    see sprintf for a description of format
     * @param array<int, string|int|float> $values     see sprintf for a description of values
     * @param array<string, mixed>         $attributes additional attributes to add to the log
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
        if ($client === null || !$client->getOptions()->getEnableLogs()) {
            return;
        }

        $scope = null;

        // This we push and pop a scope to get access to it because there is no accessor for the scope
        $hub->configureScope(function (Scope $hubScope) use (&$scope) {
            $scope = $hubScope;
        });

        \assert($scope !== null, 'The scope comes from the hub and cannot be null at this point.');

        $traceId = $scope->getPropagationContext()->getTraceId();

        // @FIXME The SDK name and version won't work for Laravel & Symfony and other SDKs, needs to be more flexible
        $log = (new Log($timestamp, (string) $traceId, $level, vsprintf($message, $values)))
            ->setAttribute('sentry.message.template', $message)
            ->setAttribute('sentry.trace.parent_span_id', $hub->getSpan() ? $hub->getSpan()->getSpanId() : null);

        foreach ($values as $key => $value) {
            $log->setAttribute("sentry.message.parameter.{$key}", $value);
        }

        foreach ($attributes as $key => $value) {
            $attribute = LogAttribute::tryFromValue($value);

            if ($attribute === null) {
                $client->getOptions()->getLoggerOrNullLogger()->info(
                    \sprintf("Dropping log attribute {$key} with value of type '%s' because it is not serializable or an unsupported type.", \gettype($value))
                );
            } else {
                $log->setAttribute($key, $attribute);
            }
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
