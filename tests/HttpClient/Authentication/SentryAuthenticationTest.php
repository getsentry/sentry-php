<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient\Authentication;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\HttpClient\Authentication\SentryAuthentication;
use Sentry\Options;

final class SentryAuthenticationTest extends TestCase
{
    public function testAuthenticateWithSecretKey(): void
    {
        $configuration = new Options(['dsn' => 'http://public:secret@example.com/sentry/1']);
        $authentication = new SentryAuthentication($configuration, 'sentry.php.test', '1.2.3');
        $request = new Request('POST', 'http://www.example.com', []);
        $expectedHeader = sprintf(
            'Sentry sentry_version=%s, sentry_client=%s, sentry_key=public, sentry_secret=secret',
            Client::PROTOCOL_VERSION,
            'sentry.php.test/1.2.3'
        );

        $this->assertFalse($request->hasHeader('X-Sentry-Auth'));

        $request = $authentication->authenticate($request);

        $this->assertTrue($request->hasHeader('X-Sentry-Auth'));
        $this->assertSame($expectedHeader, $request->getHeaderLine('X-Sentry-Auth'));
    }

    public function testAuthenticateWithoutSecretKey(): void
    {
        $configuration = new Options(['dsn' => 'http://public@example.com/sentry/1']);
        $authentication = new SentryAuthentication($configuration, 'sentry.php.test', '1.2.3');
        $request = new Request('POST', 'http://www.example.com', []);
        $expectedHeader = sprintf(
            'Sentry sentry_version=%s, sentry_client=%s, sentry_key=public',
            Client::PROTOCOL_VERSION,
            'sentry.php.test/1.2.3'
        );

        $this->assertFalse($request->hasHeader('X-Sentry-Auth'));

        $request = $authentication->authenticate($request);

        $this->assertTrue($request->hasHeader('X-Sentry-Auth'));
        $this->assertSame($expectedHeader, $request->getHeaderLine('X-Sentry-Auth'));
    }

    public function testAuthenticateWithoutDsnOptionSet(): void
    {
        $authentication = new SentryAuthentication(new Options(), 'sentry.php.test', '1.2.3');
        $request = new Request('POST', 'http://www.example.com', []);
        $request = $authentication->authenticate($request);

        $this->assertFalse($request->hasHeader('X-Sentry-Auth'));
    }
}
