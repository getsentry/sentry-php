<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\HttpClient\Authentication;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Raven\Client;
use Raven\Configuration;
use Raven\HttpClient\Authentication\SentryAuth;

/**
 * @group time-sensitive
 */
class SentryAuthTest extends TestCase
{
    public function testAuthenticate()
    {
        $configuration = new Configuration(['server' => 'http://public:secret@example.com/']);
        $authentication = new SentryAuth($configuration);

        /** @var RequestInterface|\PHPUnit_Framework_MockObject_MockObject $request */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var RequestInterface|\PHPUnit_Framework_MockObject_MockObject $newRequest */
        $newRequest = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $headerValue = sprintf(
            'Sentry sentry_version=%s, sentry_client=%s, sentry_timestamp=%F, sentry_key=public, sentry_secret=secret',
            Client::PROTOCOL,
            Client::USER_AGENT,
            microtime(true)
        );

        $request->expects($this->once())
            ->method('withHeader')
            ->with('X-Sentry-Auth', $headerValue)
            ->willReturn($newRequest);

        $this->assertSame($newRequest, $authentication->authenticate($request));
    }
}
