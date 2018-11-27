<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient\Authentication;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sentry\Client;
use Sentry\HttpClient\Authentication\SentryAuth;
use Sentry\Options;

/**
 * @group time-sensitive
 */
final class SentryAuthTest extends TestCase
{
    public function testAuthenticate(): void
    {
        $options = new Options(['dsn' => 'http://public:secret@example.com/']);
        $authentication = new SentryAuth(Client::getUserAgent(), $options);

        /** @var RequestInterface|MockObject $request */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var RequestInterface|MockObject $newRequest */
        $newRequest = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $headerValue = sprintf(
            'Sentry sentry_version=%s, sentry_client=%s, sentry_timestamp=%F, sentry_key=public, sentry_secret=secret',
            SentryAuth::PROTOCOL_VERSION,
            Client::getUserAgent(),
            microtime(true)
        );

        $request->expects($this->once())
            ->method('withHeader')
            ->with('X-Sentry-Auth', $headerValue)
            ->willReturn($newRequest);

        $this->assertSame($newRequest, $authentication->authenticate($request));
    }

    public function testAuthenticateWithNoSecretKey(): void
    {
        $options = new Options(['dsn' => 'http://public@example.com/']);
        $authentication = new SentryAuth(Client::getUserAgent(), $options);

        /** @var RequestInterface|MockObject $request */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var RequestInterface|MockObject $newRequest */
        $newRequest = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $headerValue = sprintf(
            'Sentry sentry_version=%s, sentry_client=%s, sentry_timestamp=%F, sentry_key=public',
            SentryAuth::PROTOCOL_VERSION,
            Client::getUserAgent(),
            microtime(true)
        );

        $request->expects($this->once())
            ->method('withHeader')
            ->with('X-Sentry-Auth', $headerValue)
            ->willReturn($newRequest);

        $this->assertSame($newRequest, $authentication->authenticate($request));
    }
}
