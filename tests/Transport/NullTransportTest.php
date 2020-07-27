<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\ResponseStatus;
use Sentry\Transport\NullTransport;

final class NullTransportTest extends TestCase
{
    public function testSend(): void
    {
        $transport = new NullTransport();
        $event = new Event();

        $promise = $transport->send($event);
        $promiseResult = $promise->wait();

        $this->assertSame(PromiseInterface::FULFILLED, $promise->getState());
        $this->assertSame(ResponseStatus::skipped(), $promiseResult->getStatus());
        $this->assertSame($event, $promiseResult->getEvent());
    }
}
