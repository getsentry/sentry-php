<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\CheckIn;
use Sentry\CheckInStatus;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventId;
use Sentry\MonitorConfig;
use Sentry\NoOpClient;
use Sentry\SentrySdk;
use Sentry\Severity;

/**
 * @internal
 */
final class EventRecorder
{
    private function __construct()
    {
    }

    public static function captureMessage(string $message, ?Severity $level = null, ?EventHint $hint = null, ?IsolationScope $isolationScope = null): ?EventId
    {
        $isolationScope = $isolationScope ?? SentrySdk::getIsolationScope();

        return self::captureWithScope(SentrySdk::getClient($isolationScope), $isolationScope, static function (ClientInterface $client, IsolationScope $captureScope) use ($message, $level, $hint): ?EventId {
            return $client->captureMessage($message, $level, $captureScope, $hint);
        });
    }

    public static function captureException(\Throwable $exception, ?EventHint $hint = null, ?IsolationScope $isolationScope = null): ?EventId
    {
        $isolationScope = $isolationScope ?? SentrySdk::getIsolationScope();

        return self::captureWithScope(SentrySdk::getClient($isolationScope), $isolationScope, static function (ClientInterface $client, IsolationScope $captureScope) use ($exception, $hint): ?EventId {
            return $client->captureException($exception, $captureScope, $hint);
        });
    }

    public static function captureEvent(Event $event, ?EventHint $hint = null, ?IsolationScope $isolationScope = null): ?EventId
    {
        $isolationScope = $isolationScope ?? SentrySdk::getIsolationScope();

        return self::captureWithScope(SentrySdk::getClient($isolationScope), $isolationScope, static function (ClientInterface $client, IsolationScope $captureScope) use ($event, $hint): ?EventId {
            return $client->captureEvent($event, $hint, $captureScope);
        });
    }

    public static function captureLastError(?EventHint $hint = null, ?IsolationScope $isolationScope = null): ?EventId
    {
        $isolationScope = $isolationScope ?? SentrySdk::getIsolationScope();

        return self::captureWithScope(SentrySdk::getClient($isolationScope), $isolationScope, static function (ClientInterface $client, IsolationScope $captureScope) use ($hint): ?EventId {
            return $client->captureLastError($captureScope, $hint);
        });
    }

    /**
     * @param int|float|null $duration
     */
    public static function captureCheckIn(string $slug, CheckInStatus $status, $duration = null, ?MonitorConfig $monitorConfig = null, ?string $checkInId = null, ?IsolationScope $isolationScope = null): ?string
    {
        $isolationScope = $isolationScope ?? SentrySdk::getIsolationScope();
        $client = SentrySdk::getClient($isolationScope);

        if ($client instanceof NoOpClient) {
            return null;
        }

        $options = $client->getOptions();
        $event = Event::createCheckIn();
        $checkIn = new CheckIn(
            $slug,
            $status,
            $checkInId,
            $options->getRelease(),
            $options->getEnvironment(),
            $duration,
            $monitorConfig
        );
        $event->setCheckIn($checkIn);

        self::captureWithScope($client, $isolationScope, static function (ClientInterface $client, IsolationScope $captureScope) use ($event): ?EventId {
            return $client->captureEvent($event, null, $captureScope);
        });

        return $checkIn->getId();
    }

    /**
     * @param callable(ClientInterface, IsolationScope): ?EventId $capture
     */
    private static function captureWithScope(ClientInterface $client, IsolationScope $isolationScope, callable $capture): ?EventId
    {
        if ($client instanceof NoOpClient) {
            return null;
        }

        $eventId = $capture($client, $isolationScope);
        SentrySdk::getCurrentRuntimeContext()->setLastEventId($eventId);

        return $eventId;
    }
}
