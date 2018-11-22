<?php

declare(strict_types=1);

namespace Sentry\Tests\Spool;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Spool\MemorySpool;
use Sentry\Transport\TransportInterface;

final class MemorySpoolTest extends TestCase
{
    /**
     * @var MemorySpool
     */
    protected $spool;

    protected function setUp(): void
    {
        $this->spool = new MemorySpool();
    }

    public function testQueueEvent(): void
    {
        $this->assertAttributeEmpty('events', $this->spool);
        $this->assertTrue($this->spool->queueEvent(new Event()));
        $this->assertAttributeNotEmpty('events', $this->spool);
    }

    public function testFlushQueue(): void
    {
        $event1 = new Event();
        $event2 = new Event();

        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->exactly(2))
            ->method('send')
            ->withConsecutive($event1, $event2);

        $this->spool->queueEvent($event1);
        $this->spool->queueEvent($event2);

        $this->spool->flushQueue($transport);

        $this->assertAttributeEmpty('events', $this->spool);
    }

    public function testFlushQueueWithEmptyQueue(): void
    {
        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->never())
            ->method('send');

        $this->spool->flushQueue($transport);
    }
}
