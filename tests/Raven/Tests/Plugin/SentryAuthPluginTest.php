<?php

namespace Raven\Tests\Plugin;

use Guzzle\Common\Event;
use Hautelook\Frankenstein\TestCase;
use Raven\Client;
use Raven\Plugin\SentryAuthPlugin;

class SentryAuthPluginTest extends TestCase
{
    public function test()
    {
        $plugin = new SentryAuthPlugin(
            'public',
            'secret',
            '4',
            'agent'
        );

        $expectedHeader = sprintf(
            'Sentry sentry_version=%s, sentry_client=%s, sentry_timestamp=%s, sentry_key=%s, sentry_secret=%s',
            '4',
            'agent',
            '7777777',
            'public',
            'secret'
        );

        $requestProphecy = $this->prophesize('Guzzle\Http\Message\Request');
        $requestProphecy
            ->setHeader('X-Sentry-Auth', $expectedHeader)
            ->willReturn()
        ;

        $plugin->onRequestBeforeSend(new Event(array(
            'request' => $requestProphecy->reveal(),
            'timestamp' => 7777777
        )));
    }
}
