<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Spool\SpoolInterface;
use Sentry\Transport\SpoolTransport;

final class SpoolTransportTest extends TestCase
{
    /**
     * @var SpoolInterface&MockObject
     */
    protected $spool;

    /**
     * @var SpoolTransport
     */
    protected $transport;

    protected function setUp(): void
    {
        $this->spool = $this->createMock(SpoolInterface::class);
        $this->transport = new SpoolTransport($this->spool);
    }

    public function testGetSpool(): void
    {
        $this->assertSame($this->spool, $this->transport->getSpool());
    }

    /**
     * @dataProvider sendDataProvider
     */
    public function testSend(bool $isSendingSuccessful): void
    {
        $event = new Event();

        $this->spool->expects($this->once())
            ->method('queueEvent')
            ->with($event)
            ->willReturn($isSendingSuccessful);

        $eventId = $this->transport->send($event);

        if ($isSendingSuccessful) {
            $this->assertSame((string) $event->getId(false), $eventId);
        } else {
            $this->assertNull($eventId);
        }
    }

    public function sendDataProvider(): \Generator
    {
        yield [true];
        yield [false];
    }
}
