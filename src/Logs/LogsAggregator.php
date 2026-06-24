<?php

declare(strict_types=1);

namespace Sentry\Logs;

use Sentry\Attributes\Attribute;
use Sentry\Client;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;
use Sentry\State\IsolationScope;
use Sentry\State\MergedScope;
use Sentry\State\Scope;
use Sentry\Util\Arr;
use Sentry\Util\Str;
use Sentry\Util\TelemetryStorage;

/**
 * @internal
 */
final class LogsAggregator
{
    private const LOGS_BUFFER_SIZE = 1000;

    /**
     * @var TelemetryStorage<Log>|null
     */
    private $logs;

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

        $isolationScope = SentrySdk::getIsolationScope();
        $client = SentrySdk::getClient($isolationScope);
        $scope = SentrySdk::getGlobalScope()->merge($isolationScope);

        $options = $client->getOptions();
        $sdkLogger = $options->getLogger();

        if (!$options->getEnableLogs()) {
            if ($sdkLogger !== null) {
                $sdkLogger->info(
                    'Log will be discarded because "enable_logs" is "false".'
                );
            }

            return;
        }

        $formattedMessage = Str::vsprintfOrNull($message, $values);

        if ($formattedMessage === null) {
            // If formatting fails we don't format the message and log the error
            if ($sdkLogger !== null) {
                $sdkLogger->warning('Failed to format log message with values.', [
                    'message' => $message,
                    'values' => $values,
                ]);
            }

            $formattedMessage = $message;
        }

        $traceData = $this->getTraceData($scope);
        $traceId = $traceData['trace_id'];
        $parentSpanId = $traceData['parent_span_id'];

        $log = (new Log($timestamp, $traceId, $level, $formattedMessage))
            ->setAttribute('sentry.release', $options->getRelease())
            ->setAttribute('sentry.environment', $options->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT)
            ->setAttribute('server.address', $options->getServerName())
            ->setAttribute('sentry.trace.parent_span_id', $parentSpanId);

        if ($client instanceof Client) {
            $log->setAttribute('sentry.sdk.name', $client->getSdkIdentifier());
            $log->setAttribute('sentry.sdk.version', $client->getSdkVersion());
        }

        $user = $scope->getUser();
        if ($user !== null) {
            if ($user->getId() !== null) {
                $log->setAttribute('user.id', $user->getId());
            }
            if ($user->getEmail() !== null) {
                $log->setAttribute('user.email', $user->getEmail());
            }
            if ($user->getUsername() !== null) {
                $log->setAttribute('user.name', $user->getUsername());
            }
        }

        if (\count($values)) {
            $log->setAttribute('sentry.message.template', $message);

            foreach ($values as $key => $value) {
                $log->setAttribute("sentry.message.parameter.{$key}", $value);
            }
        }

        $attributes = Arr::simpleDot($attributes);

        foreach ($attributes as $key => $value) {
            if (!\is_string($key)) {
                if ($sdkLogger !== null) {
                    $sdkLogger->info(
                        \sprintf("Dropping log attribute with non-string key '%s' and value of type '%s'.", $key, \gettype($value))
                    );
                }

                continue;
            }

            $attribute = Attribute::tryFromValue($value);

            if ($attribute === null) {
                if ($sdkLogger !== null) {
                    $sdkLogger->info(
                        \sprintf("Dropping log attribute {$key} with value of type '%s' because it is not serializable or an unsupported type.", \gettype($value))
                    );
                }

                continue;
            }

            $log->setAttribute($key, $attribute);
        }

        $log = ($options->getBeforeSendLogCallback())($log);

        if ($log === null) {
            if ($sdkLogger !== null) {
                $sdkLogger->info(
                    'Log will be discarded because the "before_send_log" callback returned "null".',
                    ['log' => $log]
                );
            }

            return;
        }

        if ($sdkLogger !== null) {
            $sdkLogger->log($log->getPsrLevel(), "Logs item: {$log->getBody()}", $log->attributes()->toSimpleArray());
        }

        $logFlushThreshold = $options->getLogFlushThreshold();
        $logs = $this->getStorage($logFlushThreshold);

        $logs->push($log);

        if ($logFlushThreshold !== null && \count($logs) >= $logFlushThreshold) {
            $this->flush($client);
        }
    }

    public function flush(?ClientInterface $client = null, ?IsolationScope $isolationScope = null): ?EventId
    {
        $logs = $this->logs;

        if ($logs === null || $logs->isEmpty()) {
            return null;
        }

        $isolationScope = $isolationScope ?? SentrySdk::getIsolationScope();
        $client = $client ?? SentrySdk::getClient($isolationScope);
        $event = Event::createLogs()->setLogs($logs->drain());

        return $client->captureEvent($event, null, SentrySdk::getGlobalScope()->merge($isolationScope));
    }

    /**
     * @return Log[]
     */
    public function all(): array
    {
        return $this->logs !== null ? $this->logs->toArray() : [];
    }

    /**
     * @return array{trace_id: string, parent_span_id: string|null}
     */
    private function getTraceData(MergedScope $scope): array
    {
        $span = $scope->getSpan();

        if ($span !== null) {
            return [
                'trace_id' => (string) $span->getTraceId(),
                'parent_span_id' => (string) $span->getSpanId(),
            ];
        }

        $externalPropagationContext = Scope::getExternalPropagationContext();
        if ($externalPropagationContext !== null) {
            return [
                'trace_id' => $externalPropagationContext['trace_id'],
                'parent_span_id' => $externalPropagationContext['span_id'],
            ];
        }

        return [
            'trace_id' => (string) $scope->getPropagationContext()->getTraceId(),
            'parent_span_id' => null,
        ];
    }

    /**
     * @return TelemetryStorage<Log>
     */
    private function getStorage(?int $logFlushThreshold = null): TelemetryStorage
    {
        if ($this->logs === null) {
            /** @var TelemetryStorage<Log> $logs */
            $logs = $logFlushThreshold !== null
                ? TelemetryStorage::unbounded()
                : TelemetryStorage::bounded(self::LOGS_BUFFER_SIZE);

            $this->logs = $logs;
        }

        return $this->logs;
    }
}
