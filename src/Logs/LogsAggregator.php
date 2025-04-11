<?php

declare(strict_types=1);

namespace Sentry\Logs;

use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\Tracing\TransactionSource;

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

        $this->logs[] = [
            'timestamp' => $timestamp,
            'trace_id' => (string) $traceId,
            'level' => (string) $level,
            'body' => $message,
        ];
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

    /**
     * @param array<string, string> $tags
     *
     * @return array<string, string>
     */
    private function serializeTags(array $tags): array
    {
        $hub = SentrySdk::getCurrentHub();
        $client = $hub->getClient();

        if ($client !== null) {
            $options = $client->getOptions();

            $defaultTags = [
                'environment' => $options->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT,
            ];

            $release = $options->getRelease();
            if ($release !== null) {
                $defaultTags['release'] = $release;
            }

            $hub->configureScope(function (Scope $scope) use (&$defaultTags) {
                $transaction = $scope->getTransaction();
                if (
                    $transaction !== null
                    // Only include the transaction name if it has good quality
                    && $transaction->getMetadata()->getSource() !== TransactionSource::url()
                ) {
                    $defaultTags['transaction'] = $transaction->getName();
                }
            });

            $tags = array_merge($defaultTags, $tags);
        }

        // It's very important to sort the tags in order to obtain the same bucket key.
        ksort($tags);

        return $tags;
    }
}
