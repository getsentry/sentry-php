<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Breadcrumbs;

use PHPUnit\Framework\TestCase;
use Raven\ClientBuilder;
use Raven\Event;
use Raven\Middleware\StacktraceInterfaceMiddleware;
use Raven\Stacktrace;

class StacktraceInterfaceMiddlewareTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke($clientConfig, $expectExceptionCaptured)
    {
        $client = ClientBuilder::create($clientConfig)->getClient();
        $event = new Event($client->getConfig());

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, $expectExceptionCaptured, &$invokationCount) {
            if ($expectExceptionCaptured) {
                $this->assertNotSame($event, $eventArg);
                $this->assertInstanceOf(Stacktrace::class, $eventArg->getStacktrace());
            } else {
                $this->assertSame($event, $eventArg);
                $this->assertNull($eventArg->getStacktrace());
            }

            ++$invokationCount;
        };

        $middleware = new StacktraceInterfaceMiddleware($client);
        $middleware($event, $callback, null, new \Exception());

        $this->assertEquals(1, $invokationCount);
    }

    public function invokeDataProvider()
    {
        return [
            [
                [
                    'auto_log_stacks' => true,
                ],
                true,
            ],
            [
                [
                    'auto_log_stacks' => false,
                ],
                false,
            ],
            [
                [
                    'excluded_exceptions' => [\Exception::class],
                ],
                false,
            ],
            [
                [
                    'excluded_exceptions' => [\Exception::class],
                    'auto_log_stacks' => true,
                ],
                false,
            ],
        ];
    }
}
