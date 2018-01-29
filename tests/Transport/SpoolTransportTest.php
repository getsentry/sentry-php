<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Raven\Configuration;
use Raven\Event;
use Raven\Spool\SpoolInterface;
use Raven\Transport\SpoolTransport;

class SpoolTransportTest extends TestCase
{
    /**
     * @var SpoolInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $spool;

    /**
     * @var SpoolTransport
     */
    protected $transport;

    protected function setUp()
    {
        $this->spool = $this->createMock(SpoolInterface::class);
        $this->transport = new SpoolTransport($this->spool);
    }

    public function testGetSpool()
    {
        $this->assertSame($this->spool, $this->transport->getSpool());
    }

    public function testSend()
    {
        $event = new Event(new Configuration());

        $this->spool->expects($this->once())
            ->method('queueEvent')
            ->with($event);

        $this->transport->send($event);
    }
}
