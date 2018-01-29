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

use Http\Client\Exception\HttpException;
use Http\Client\HttpAsyncClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Promise\Promise;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Raven\Configuration;
use Raven\Event;
use Raven\Transport\HttpTransport;
use Raven\Util\JSON;

class HttpTransportTest extends TestCase
{
    public function testDestructor()
    {
        /** @var Promise|\PHPUnit_Framework_MockObject_MockObject $promise */
        $promise = $this->createMock(Promise::class);
        $promise->expects($this->once())
            ->method('wait');

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn($promise);

        $config = new Configuration();
        $transport = new HttpTransport($config, $httpClient, MessageFactoryDiscovery::find());

        $this->assertAttributeEmpty('pendingRequests', $transport);

        $transport->send(new Event($config));

        $this->assertAttributeNotEmpty('pendingRequests', $transport);

        unset($transport);
    }

    public function testSendWithoutCompressedEncoding()
    {
        $config = new Configuration(['encoding' => 'json']);
        $event = new Event($config);

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

        unset($transport);
    }

    public function testSendWithCompressedEncoding()
    {
        $config = new Configuration(['encoding' => 'gzip']);
        $event = new Event($config);

        $promise = $this->createMock(Promise::class);
        $promise->expects($this->once())
            ->method('wait');

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->with($this->callback(function (RequestInterface $request) use ($event) {
                $request->getBody()->rewind();

                return 'application/octet-stream' === $request->getHeaderLine('Content-Type')
                    && base64_encode(gzcompress(JSON::encode($event))) === $request->getBody()->getContents();
            }))
            ->willReturn($promise);

        $transport = new HttpTransport($config, $httpClient, MessageFactoryDiscovery::find());
        $transport->send($event);

        unset($transport);
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

        $config = new Configuration();
        $transport = new HttpTransport($config, $httpClient, MessageFactoryDiscovery::find());

        $transport->send(new Event($config));

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
