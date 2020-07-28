<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Transport\NullTransport;

final class NullTransportTest extends TestCase
{
    public function testSend(): void
    {
        $transport = new NullTransport();
        $event = new Event();

        $this->assertSame((string) $event->getId(false), $transport->send($event));
    }
}
