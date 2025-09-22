<?php

declare(strict_types=1);

namespace Sentry\Tests\Profiles;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\EventId;
use Sentry\Profiles\ProfilesAggregator;
use Sentry\SentrySdk;

final class ProfilesAggregatorTest extends TestCase
{
    private function setupSentryClient(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->method('captureEvent')
            ->willReturn(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));

        SentrySdk::getCurrentHub()->bindClient($client);
    }

    public function testAddExcimerLog(): void
    {
        $this->setupSentryClient();

        $aggregator = new ProfilesAggregator();
        $aggregator->setStartTimeStamp(1734604860.0);
        $aggregator->setProfilerId('550e8400e29b41d4a716446655440000');

        $mockExcimerLog = $this->createMockExcimerLog();

        $aggregator->add($mockExcimerLog);

        $this->assertInstanceOf(EventId::class, $aggregator->flush());
    }

    public function testFlushWithEmptyLogs(): void
    {
        $aggregator = new ProfilesAggregator();

        $result = $aggregator->flush();

        $this->assertNull($result);
    }

    public function testFlushClearsLogs(): void
    {
        $this->setupSentryClient();

        $aggregator = new ProfilesAggregator();
        $aggregator->setStartTimeStamp(1734604860.0);
        $aggregator->setProfilerId('550e8400e29b41d4a716446655440000');

        $mockExcimerLog = $this->createMockExcimerLog();
        $aggregator->add($mockExcimerLog);

        // First flush should return EventId
        $this->assertInstanceOf(EventId::class, $aggregator->flush());

        // Second flush should return null since logs were cleared
        $this->assertNull($aggregator->flush());
    }

    private function createMockExcimerLog(): \Iterator
    {
        return new \ArrayIterator([
            [
                'trace' => [
                    [
                        'file' => '/var/www/html/index.php',
                        'line' => 42,
                    ],
                ],
                'timestamp' => 0.001,
            ],
        ]);
    }
}
