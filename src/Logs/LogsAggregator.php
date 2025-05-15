<?php

declare(strict_types=1);

namespace Sentry\Logs;

use Sentry\Attributes\Attribute;
use Sentry\Client;
use Sentry\Event;
use Sentry\EventId;
use Sentry\Logger\LogsLogger;
use Sentry\SentrySdk;
use Sentry\State\Scope;

/**
 * @phpstan-import-type AttributeValue from Attribute
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

        $options = $client->getOptions();

        $log = (new Log($timestamp, (string) $traceId, $level, vsprintf($message, $values)))
            ->setAttribute('sentry.release', $options->getRelease())
            ->setAttribute('sentry.environment', $options->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT)
            ->setAttribute('sentry.server.address', $options->getServerName())
            ->setAttribute('sentry.message.template', $message)
            ->setAttribute('sentry.trace.parent_span_id', $hub->getSpan() ? $hub->getSpan()->getSpanId() : null);

        if ($client instanceof Client) {
            $log->setAttribute('sentry.sdk.name', $client->getSdkIdentifier());
            $log->setAttribute('sentry.sdk.version', $client->getSdkVersion());
        }

        foreach ($values as $key => $value) {
            $log->setAttribute("sentry.message.parameter.{$key}", $value);
        }

        $logger = $options->getLogger();

        foreach ($attributes as $key => $value) {
            $attribute = Attribute::tryFromValue($value);

            if ($attribute === null) {
                if ($logger !== null) {
                    $logger->info(
                        \sprintf("Dropping log attribute {$key} with value of type '%s' because it is not serializable or an unsupported type.", \gettype($value))
                    );
                }
            } else {
                $log->setAttribute($key, $attribute);
            }
        }

        $log = ($options->getBeforeSendLogCallback())($log);

        if ($log === null) {
            if ($logger !== null) {
                $logger->info(
                    'Log will be discarded because the "before_send_log" callback returned "null".',
                    ['log' => $log]
                );
            }

            return;
        }

        if ($logger !== null) {
            $logger->log((string) $log->getLevel(), $log->getBody(), $log->getAttributes());
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
