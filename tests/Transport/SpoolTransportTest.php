<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectionException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\ResponseStatus;
use Sentry\Spool\SpoolInterface;
use Sentry\Transport\SpoolTransport;

final class SpoolTransportTest extends TestCase
{
    /**
     * @var SpoolInterface&MockObject
     */
    private $spool;

    /**
     * @var SpoolTransport
     */
    private $transport;

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
    public function testSend(bool $shouldQueueEvent, string $expectedPromiseStatus, ResponseStatus $expectedResponseStatus): void
    {
        $event = new Event();

        $this->spool->expects($this->once())
            ->method('queueEvent')
            ->with($event)
            ->willReturn($shouldQueueEvent);

        $promise = $this->transport->send($event);

        try {
            $promiseResult = $promise->wait();
        } catch (RejectionException $exception) {
            $promiseResult = $exception->getReason();
        }

        $this->assertSame($expectedPromiseStatus, $promise->getState());
        $this->assertSame($expectedResponseStatus, $promiseResult->getStatus());
        $this->assertSame($event, $promiseResult->getEvent());
    }

    public function sendDataProvider(): iterable
    {
        yield [
            true,
            PromiseInterface::FULFILLED,
            ResponseStatus::success(),
        ];

        yield [
            false,
            PromiseInterface::REJECTED,
            ResponseStatus::skipped(),
        ];
    }

    public function testClose(): void
    {
        $promise = $this->transport->close();

        $this->assertSame(PromiseInterface::FULFILLED, $promise->getState());
        $this->assertTrue($promise->wait());
    }
}
