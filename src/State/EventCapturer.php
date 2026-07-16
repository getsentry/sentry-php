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
final class EventCapturer
{
    private function __construct()
    {
    }

    public static function captureMessage(string $message, ?Severity $level = null, ?EventHint $hint = null): ?EventId
    {
        return self::capture(static function (ClientInterface $client, Scope $captureScope) use ($message, $level, $hint): ?EventId {
            return $client->captureMessage($message, $level, $captureScope, $hint);
        });
    }

    public static function captureException(\Throwable $exception, ?EventHint $hint = null): ?EventId
    {
        return self::capture(static function (ClientInterface $client, Scope $captureScope) use ($exception, $hint): ?EventId {
            return $client->captureException($exception, $captureScope, $hint);
        });
    }

    public static function captureEvent(Event $event, ?EventHint $hint = null): ?EventId
    {
        return self::capture(static function (ClientInterface $client, Scope $captureScope) use ($event, $hint): ?EventId {
            return $client->captureEvent($event, $hint, $captureScope);
        });
    }

    public static function captureLastError(?EventHint $hint = null): ?EventId
    {
        return self::capture(static function (ClientInterface $client, Scope $captureScope) use ($hint): ?EventId {
            return $client->captureLastError($captureScope, $hint);
        });
    }

    /**
     * @param int|float|null $duration
     */
    public static function captureCheckIn(string $slug, CheckInStatus $status, $duration = null, ?MonitorConfig $monitorConfig = null, ?string $checkInId = null): ?string
    {
        $isolationScope = SentrySdk::getIsolationScope();
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

        self::captureWithScope($client, $isolationScope, static function (ClientInterface $client, Scope $captureScope) use ($event): ?EventId {
            return $client->captureEvent($event, null, $captureScope);
        });

        return $checkIn->getId();
    }

    /**
     * @param callable(ClientInterface, Scope): ?EventId $capture
     */
    private static function capture(callable $capture): ?EventId
    {
        $isolationScope = SentrySdk::getIsolationScope();

        return self::captureWithScope(SentrySdk::getClient($isolationScope), $isolationScope, $capture);
    }

    /**
     * @param callable(ClientInterface, Scope): ?EventId $capture
     */
    private static function captureWithScope(ClientInterface $client, Scope $isolationScope, callable $capture): ?EventId
    {
        $eventId = $capture($client, Scope::mergeScopes(SentrySdk::getGlobalScope(), $isolationScope));
        $isolationScope->setLastEventId($eventId);

        return $eventId;
    }
}
