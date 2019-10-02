<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient\Authentication;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sentry\Client;
use Sentry\Exception\MissingPublicKeyCredentialException;
use Sentry\HttpClient\Authentication\SentryAuthentication;
use Sentry\Options;

/**
 * @group time-sensitive
 */
final class SentryAuthenticationTest extends TestCase
{
    public function testAuthenticate(): void
    {
        $configuration = new Options(['dsn' => 'http://public:secret@example.com/']);
        $authentication = new SentryAuthentication($configuration, 'sentry.php.test', '1.2.3');

        /** @var RequestInterface|MockObject $request */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var RequestInterface|MockObject $newRequest */
        $newRequest = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $headerValue = sprintf(
            'Sentry sentry_version=%s, sentry_client=%s, sentry_timestamp=%F, sentry_key=public, sentry_secret=secret',
            Client::PROTOCOL_VERSION,
            'sentry.php.test/1.2.3',
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
        $configuration = new Options(['dsn' => 'http://public@example.com/']);
        $authentication = new SentryAuthentication($configuration, 'sentry.php.test', '2.0.0');

        /** @var RequestInterface|MockObject $request */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var RequestInterface|MockObject $newRequest */
        $newRequest = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $headerValue = sprintf(
            'Sentry sentry_version=%s, sentry_client=%s, sentry_timestamp=%F, sentry_key=public',
            Client::PROTOCOL_VERSION,
            'sentry.php.test/2.0.0',
            microtime(true)
        );

        $request->expects($this->once())
            ->method('withHeader')
            ->with('X-Sentry-Auth', $headerValue)
            ->willReturn($newRequest);

        $this->assertSame($newRequest, $authentication->authenticate($request));
    }

    public function testAuthenticateWithNoPublicKeyThrowsException(): void
    {
        $this->expectException(MissingPublicKeyCredentialException::class);

        $authentication = new SentryAuthentication(new Options(), 'sentry.php.test', '2.0.0');

        /** @var RequestInterface&MockObject $request */
        $request = $this->createMock(RequestInterface::class);

        $authentication->authenticate($request);
    }
}
