<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Spool;

use PHPUnit\Framework\TestCase;
use Raven\Configuration;
use Raven\Event;
use Raven\Spool\MemorySpool;
use Raven\Transport\TransportInterface;

class MemorySpoolTest extends TestCase
{
    /**
     * @var MemorySpool
     */
    protected $spool;

    protected function setUp()
    {
        $this->spool = new MemorySpool();
    }

    public function testQueueEvent()
    {
        $this->assertAttributeEmpty('events', $this->spool);

        $this->spool->queueEvent(new Event(new Configuration()));

        $this->assertAttributeNotEmpty('events', $this->spool);
    }

    public function testFlushQueue()
    {
        $event1 = new Event(new Configuration());
        $event2 = new Event(new Configuration());

        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->exactly(2))
            ->method('send')
            ->withConsecutive($event1, $event2);

        $this->spool->queueEvent($event1);
        $this->spool->queueEvent($event2);

        $this->spool->flushQueue($transport);

        $this->assertAttributeEmpty('events', $this->spool);
    }

    public function testFlushQueueWithEmptyQueue()
    {
        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->never())
            ->method('send');

        $this->spool->flushQueue($transport);
    }
}
