<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Transport\NullTransport;
use Sentry\Transport\ResultStatus;

final class NullTransportTest extends TestCase
{
    /**
     * @var NullTransport
     */
    private $transport;

    protected function setUp(): void
    {
        $this->transport = new NullTransport();
    }

    public function testSend(): void
    {
        $event = Event::createEvent();

        $result = $this->transport->send($event);

        $this->assertSame(ResultStatus::skipped(), $result->getStatus());
        $this->assertSame($event, $result->getEvent());
    }

    public function testClose(): void
    {
        $response = $this->transport->close();

        $this->assertSame(ResultStatus::success(), $response->getStatus());
    }
}
