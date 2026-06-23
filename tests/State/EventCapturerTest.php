<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\TestCase;
use Sentry\CheckInStatus;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventId;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;
use Sentry\NoOpClient;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\EventCapturer;
use Sentry\State\Scope;
use Sentry\Util\SentryUid;

final class EventCapturerTest extends TestCase
{
    public function testCaptureMessagePassesMergedScopeAndStoresLastEventIdOnIsolationScope(): void
    {
        $eventId = EventId::generate();
        $hint = new EventHint();
        $client = $this->createMock(ClientInterface::class);
        $isolationScope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('captureMessage')
            ->with('foo', Severity::debug(), $this->callback(function (Scope $scope) use ($isolationScope): bool {
                return $this->isMergedCaptureScope($scope, $isolationScope);
            }), $hint)
            ->willReturn($eventId);

        $this->assertSame($eventId, EventCapturer::captureMessage('foo', Severity::debug(), $hint));
        $this->assertSame($eventId, $isolationScope->getLastEventId());
    }

    public function testCaptureExceptionPassesMergedScopeAndStoresLastEventIdOnIsolationScope(): void
    {
        $eventId = EventId::generate();
        $exception = new \RuntimeException('foo');
        $hint = new EventHint();
        $client = $this->createMock(ClientInterface::class);
        $isolationScope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('captureException')
            ->with($exception, $this->callback(function (Scope $scope) use ($isolationScope): bool {
                return $this->isMergedCaptureScope($scope, $isolationScope);
            }), $hint)
            ->willReturn($eventId);

        $this->assertSame($eventId, EventCapturer::captureException($exception, $hint));
        $this->assertSame($eventId, $isolationScope->getLastEventId());
    }

    public function testCaptureEventPassesMergedScopeAndStoresLastEventIdOnIsolationScope(): void
    {
        $event = Event::createEvent();
        $hint = new EventHint();
        $client = $this->createMock(ClientInterface::class);
        $isolationScope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($event, $hint, $this->callback(function (Scope $scope) use ($isolationScope): bool {
                return $this->isMergedCaptureScope($scope, $isolationScope);
            }))
            ->willReturn($event->getId());

        $this->assertSame($event->getId(), EventCapturer::captureEvent($event, $hint));
        $this->assertSame($event->getId(), $isolationScope->getLastEventId());
    }

    public function testCaptureLastErrorPassesMergedScopeAndStoresLastEventIdOnIsolationScope(): void
    {
        $eventId = EventId::generate();
        $hint = new EventHint();
        $client = $this->createMock(ClientInterface::class);
        $isolationScope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('captureLastError')
            ->with($this->callback(function (Scope $scope) use ($isolationScope): bool {
                return $this->isMergedCaptureScope($scope, $isolationScope);
            }), $hint)
            ->willReturn($eventId);

        $this->assertSame($eventId, EventCapturer::captureLastError($hint));
        $this->assertSame($eventId, $isolationScope->getLastEventId());
    }

    public function testCaptureEventClearsLastEventIdWhenClientReturnsNull(): void
    {
        $event = Event::createEvent();
        $client = $this->createMock(ClientInterface::class);
        $isolationScope = $this->setClientAndIsolationScope($client);
        $isolationScope->setLastEventId(EventId::generate());

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($event, null, $this->callback(function (Scope $scope) use ($isolationScope): bool {
                return $this->isMergedCaptureScope($scope, $isolationScope);
            }))
            ->willReturn(null);

        $this->assertNull(EventCapturer::captureEvent($event));
        $this->assertNull($isolationScope->getLastEventId());
    }

    public function testCaptureCheckInCreatesEventAndStoresLastEventId(): void
    {
        $checkInId = SentryUid::generate();
        $eventId = EventId::generate();
        $monitorConfig = new MonitorConfig(
            MonitorSchedule::crontab('*/5 * * * *'),
            5,
            30,
            'UTC'
        );
        $client = $this->createMock(ClientInterface::class);
        $isolationScope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'environment' => Event::DEFAULT_ENVIRONMENT,
                'release' => '1.0.0',
            ]));
        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(static function (Event $event) use ($checkInId, $monitorConfig): bool {
                $checkIn = $event->getCheckIn();

                return $checkIn !== null
                    && $checkIn->getId() === $checkInId
                    && $checkIn->getMonitorSlug() === 'test-crontab'
                    && $checkIn->getStatus() == CheckInStatus::ok()
                    && $checkIn->getRelease() === '1.0.0'
                    && $checkIn->getEnvironment() === Event::DEFAULT_ENVIRONMENT
                    && $checkIn->getDuration() === 10
                    && $checkIn->getMonitorConfig() === $monitorConfig;
            }), null, $this->callback(function (Scope $scope) use ($isolationScope): bool {
                return $this->isMergedCaptureScope($scope, $isolationScope);
            }))
            ->willReturn($eventId);

        $this->assertSame($checkInId, EventCapturer::captureCheckIn(
            'test-crontab',
            CheckInStatus::ok(),
            10,
            $monitorConfig,
            $checkInId
        ));
        $this->assertSame($eventId, $isolationScope->getLastEventId());
    }

    public function testCaptureCheckInReturnsNullForNoOpClient(): void
    {
        SentrySdk::init(new NoOpClient());
        $eventId = EventId::generate();
        SentrySdk::getIsolationScope()->setLastEventId($eventId);

        $this->assertNull(EventCapturer::captureCheckIn('test-crontab', CheckInStatus::ok()));
        $this->assertSame($eventId, SentrySdk::getIsolationScope()->getLastEventId());
    }

    private function setClientAndIsolationScope(ClientInterface $client): Scope
    {
        SentrySdk::init();

        SentrySdk::getGlobalScope()->clear();
        SentrySdk::getGlobalScope()->setTag('scope', 'global');
        SentrySdk::getGlobalScope()->setTag('global', 'yes');
        SentrySdk::getGlobalScope()->setClient($client);

        $scope = new Scope();
        $scope->setTag('scope', 'isolation');
        $scope->setTag('isolation', 'yes');
        SentrySdk::getCurrentRuntimeContext()->setIsolationScope($scope);

        return $scope;
    }

    private function isMergedCaptureScope(Scope $captureScope, Scope $isolationScope): bool
    {
        $this->assertNotSame($isolationScope, $captureScope);

        $event = $captureScope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame([
            'scope' => 'isolation',
            'global' => 'yes',
            'isolation' => 'yes',
        ], $event->getTags());

        return true;
    }
}
