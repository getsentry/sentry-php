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
     * @var SpoolInterface|MockObject
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

    public function testSend(): void
    {
        $event = new Event('sentry.sdk.identifier');

        $this->spool->expects($this->once())
            ->method('queueEvent')
            ->with($event);

        $this->transport->send($event);
    }
}
