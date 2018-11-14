<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\State\Hub;
use function Sentry\addBreadcrumb;
use function Sentry\captureEvent;
use function Sentry\captureException;
use function Sentry\captureLastError;
use function Sentry\captureMessage;
use function Sentry\configureScope;
use function Sentry\init;
use function Sentry\withScope;

class SdkTest extends TestCase
{
    protected function setUp(): void
    {
        init();
    }

    public function testInit(): void
    {
        $this->assertNotNull(Hub::getCurrent()->getClient());
    }

    public function testCaptureMessage(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->willReturn('92db40a886c0458288c7c83935a350ef');

        Hub::getCurrent()->bindClient($client);
        $this->assertEquals($client, Hub::getCurrent()->getClient());
        $this->assertEquals('92db40a886c0458288c7c83935a350ef', captureMessage('foo'));
    }

    public function testCaptureException(): void
    {
        $exception = new \RuntimeException('foo');

        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureException')
            ->with($exception)
            ->willReturn('2b867534eead412cbdb882fd5d441690');

        Hub::getCurrent()->bindClient($client);

        $this->assertEquals('2b867534eead412cbdb882fd5d441690', captureException($exception));
    }

    public function testCaptureEvent(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with(['message' => 'test'])
            ->willReturn('2b867534eead412cbdb882fd5d441690');

        Hub::getCurrent()->bindClient($client);

        $this->assertEquals('2b867534eead412cbdb882fd5d441690', captureEvent(['message' => 'test']));
    }

    public function testCaptureLastError()
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureLastError');

        Hub::getCurrent()->bindClient($client);

        @trigger_error('foo', E_USER_NOTICE);

        captureLastError();
    }

    public function testAddBreadcrumb(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('addBreadcrumb')
            ->with($breadcrumb, Hub::getCurrent()->getScope());

        Hub::getCurrent()->bindClient($client);
        addBreadcrumb($breadcrumb);
    }

    public function testWithScope(): void
    {
        $callbackInvoked = false;

        withScope(function () use (&$callbackInvoked): void {
            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
    }

    public function configureScope(): void
    {
        $callbackInvoked = false;

        configureScope(function () use (&$callbackInvoked): void {
            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
    }
}
