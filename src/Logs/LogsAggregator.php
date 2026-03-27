<?php

declare(strict_types=1);

namespace Sentry\Logs;

use Sentry\Attributes\Attribute;
use Sentry\Client;
use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Util\Arr;
use Sentry\Util\Str;

/**
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

        // There is no need to continue if there is no client
        if ($client === null) {
            return;
        }

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

        $traceData = $this->getTraceData($hub);
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

        $hub->configureScope(static function (Scope $scope) use ($log) {
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
        });

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

        $this->logs[] = $log;

        $logFlushThreshold = $options->getLogFlushThreshold();

        if ($logFlushThreshold !== null && \count($this->logs) >= $logFlushThreshold) {
            $this->flush($hub);
        }
    }

    public function flush(?HubInterface $hub = null): ?EventId
    {
        if (empty($this->logs)) {
            return null;
        }

        $hub = $hub ?? SentrySdk::getCurrentHub();
        $event = Event::createLogs()->setLogs($this->logs);

        $this->logs = [];

        return $hub->captureEvent($event);
    }

    /**
     * @return Log[]
     */
    public function all(): array
    {
        return $this->logs;
    }

    /**
     * @return array{trace_id: string, parent_span_id: string|null}
     */
    private function getTraceData(HubInterface $hub): array
    {
        $span = $hub->getSpan();

        if ($span !== null) {
            return [
                'trace_id' => (string) $span->getTraceId(),
                'parent_span_id' => (string) $span->getSpanId(),
            ];
        }

        $traceData = null;

        $hub->configureScope(static function (Scope $scope) use (&$traceData): void {
            $externalPropagationContext = Scope::getExternalPropagationContext();

            if ($externalPropagationContext !== null) {
                $traceData = [
                    'trace_id' => $externalPropagationContext['trace_id'],
                    'parent_span_id' => $externalPropagationContext['span_id'],
                ];

                return;
            }

            $traceData = [
                'trace_id' => (string) $scope->getPropagationContext()->getTraceId(),
                'parent_span_id' => null,
            ];
        });

        /** @var array{trace_id: string, parent_span_id: string|null} $traceData */
        return $traceData;
    }
}
