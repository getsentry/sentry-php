<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests\Transport;

use Http\Client\Exception\HttpException;
use Http\Client\HttpAsyncClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Promise\Promise;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sentry\Event;
use Sentry\Options;
use Sentry\Transport\HttpTransport;
use Sentry\Util\JSON;

class HttpTransportTest extends TestCase
{
    public function testDestructor()
    {
        /** @var Promise|\PHPUnit_Framework_MockObject_MockObject $promise1 */
        $promise = $this->createMock(Promise::class);
        $promise->expects($this->once())
            ->method('wait');

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
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

    public function testCleanupPendingRequests()
    {
        /** @var Promise|\PHPUnit_Framework_MockObject_MockObject $promise1 */
        $promise1 = $this->createMock(Promise::class);
        $promise1->expects($this->once())
            ->method('wait')
            ->willThrowException(new \Exception());

        /** @var Promise|\PHPUnit_Framework_MockObject_MockObject $promise2 */
        $promise2 = $this->createMock(Promise::class);
        $promise2->expects($this->once())
            ->method('wait');

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
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

        $reflectionMethod = new \ReflectionMethod(HttpTransport::class, 'cleanupPendingRequests');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($transport);
        $reflectionMethod->setAccessible(false);
    }

    public function testSendWithoutCompressedEncoding()
    {
        $config = new Options(['encoding' => 'json']);
        $event = new Event();

        $promise = $this->createMock(Promise::class);
        $promise->expects($this->once())
            ->method('wait');

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->with($this->callback(function (RequestInterface $request) use ($event) {
                $request->getBody()->rewind();

                return 'application/json' === $request->getHeaderLine('Content-Type')
                    && JSON::encode($event) === $request->getBody()->getContents();
            }))
            ->willReturn($promise);

        $transport = new HttpTransport($config, $httpClient, MessageFactoryDiscovery::find());
        $transport->send($event);

        $reflectionMethod = new \ReflectionMethod(HttpTransport::class, 'cleanupPendingRequests');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($transport);
        $reflectionMethod->setAccessible(false);
    }

    public function testSendWithCompressedEncoding()
    {
        $config = new Options(['encoding' => 'gzip']);
        $event = new Event();

        $promise = $this->createMock(Promise::class);
        $promise->expects($this->once())
            ->method('wait');

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->with($this->callback(function (RequestInterface $request) use ($event) {
                $request->getBody()->rewind();

                $compressedPayload = gzcompress(JSON::encode($event));

                $this->assertNotFalse($compressedPayload);

                return 'application/octet-stream' === $request->getHeaderLine('Content-Type')
                    && $compressedPayload === $request->getBody()->getContents();
            }))
            ->willReturn($promise);

        $transport = new HttpTransport($config, $httpClient, MessageFactoryDiscovery::find());
        $transport->send($event);

        $reflectionMethod = new \ReflectionMethod(HttpTransport::class, 'cleanupPendingRequests');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($transport);
        $reflectionMethod->setAccessible(false);
    }

    public function testSendFailureCleanupPendingRequests()
    {
        /** @var HttpException|\PHPUnit_Framework_MockObject_MockObject $exception */
        $exception = $this->createMock(HttpException::class);

        $promise = new PromiseMock($exception, PromiseMock::REJECTED);

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
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

class PromiseMock implements Promise
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
