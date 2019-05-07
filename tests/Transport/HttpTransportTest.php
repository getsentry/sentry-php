<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use Http\Client\Exception\HttpException;
use Http\Client\HttpAsyncClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Promise\Promise;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Options;
use Sentry\Transport\HttpTransport;

final class HttpTransportTest extends TestCase
{
    public function testDestructor(): void
    {
        /** @var Promise|MockObject $promise1 */
        $promise = $this->createMock(Promise::class);
        $promise->expects($this->once())
            ->method('wait');

        /** @var HttpAsyncClient|MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn($promise);

        $config = new Options();
        $transport = new HttpTransport($config, $httpClient, MessageFactoryDiscovery::find());

        $transport->send(new Event());

        // In PHP calling the destructor manually does not destroy the object,
        // but for testing we will do it anyway because otherwise we could not
        // test the cleanup code of the class if not all references to its
        // instance are released
        $transport->__destruct();
    }

    public function testCleanupPendingRequests(): void
    {
        /** @var Promise|MockObject $promise1 */
        $promise1 = $this->createMock(Promise::class);
        $promise1->expects($this->once())
            ->method('wait')
            ->willThrowException(new \Exception());

        /** @var Promise|MockObject $promise2 */
        $promise2 = $this->createMock(Promise::class);
        $promise2->expects($this->once())
            ->method('wait');

        /** @var HttpAsyncClient|MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);
        $httpClient->expects($this->exactly(2))
            ->method('sendAsyncRequest')
            ->willReturnOnConsecutiveCalls($promise1, $promise2);

        $config = new Options();
        $transport = new HttpTransport($config, $httpClient, MessageFactoryDiscovery::find());

        $this->assertAttributeEmpty('pendingRequests', $transport);

        $transport->send(new Event());
        $transport->send(new Event());

        $this->assertAttributeNotEmpty('pendingRequests', $transport);

        $transport->flush();
    }

    public function testSendFailureCleanupPendingRequests(): void
    {
        /** @var HttpException|MockObject $exception */
        $exception = $this->createMock(HttpException::class);

        $promise = new PromiseMock($exception, PromiseMock::REJECTED);

        /** @var HttpAsyncClient|MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn($promise);

        $config = new Options();
        $transport = new HttpTransport($config, $httpClient, MessageFactoryDiscovery::find());

        $transport->send(new Event());

        $this->assertAttributeNotEmpty('pendingRequests', $transport);
        $this->assertSame($exception, $promise->wait(true));
        $this->assertAttributeEmpty('pendingRequests', $transport);
    }
}

final class PromiseMock implements Promise
{
    private $result;

    private $state;

    private $onFullfilledCallbacks = [];

    private $onRejectedCallbacks = [];

    public function __construct($result, $state = self::FULFILLED)
    {
        $this->result = $result;
        $this->state = $state;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $onFulfilled) {
            $this->onFullfilledCallbacks[] = $onFulfilled;
        }

        if (null !== $onRejected) {
            $this->onRejectedCallbacks[] = $onRejected;
        }

        return $this;
    }

    public function getState()
    {
        return $this->state;
    }

    public function wait($unwrap = true)
    {
        switch ($this->state) {
            case self::FULFILLED:
                foreach ($this->onFullfilledCallbacks as $onFullfilledCallback) {
                    $this->result = $onFullfilledCallback($this->result);
                }

                break;
            case self::REJECTED:
                foreach ($this->onRejectedCallbacks as $onRejectedCallback) {
                    $this->result = $onRejectedCallback($this->result);
                }

                break;
        }

        return $unwrap ? $this->result : null;
    }
}
