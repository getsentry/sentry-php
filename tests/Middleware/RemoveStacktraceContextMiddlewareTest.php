<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Middleware\RemoveStacktraceContextMiddleware;
use Sentry\Stacktrace;

class RemoveStacktraceContextMiddlewareTest extends TestCase
{
    /**
     * @var ClientInterface
     */
    protected $client;

    protected function setUp()
    {
        $this->client = ClientBuilder::create(['auto_log_stacks' => true])
            ->getClient();
    }

    public function testInvoke()
    {
        $exception = new \Exception();

        $event = new Event($this->client->getConfig());
        $event->setStacktrace(Stacktrace::createFromBacktrace($this->client, $exception->getTrace(), $exception->getFile(), $exception->getLine()));

        $callbackInvoked = false;
        $callback = function (Event $eventArg) use (&$callbackInvoked) {
            foreach ($eventArg->getStacktrace()->getFrames() as $frame) {
                $this->assertNull($frame->getPreContext());
                $this->assertNull($frame->getContextLine());
                $this->assertNull($frame->getPostContext());
            }

            $callbackInvoked = true;
        };

        $middleware = new RemoveStacktraceContextMiddleware();
        $middleware($event, $callback);

        $this->assertTrue($callbackInvoked);
    }

    public function testInvokeWithPreviousException()
    {
        $exception1 = new \Exception();
        $exception2 = new \Exception('foo', 0, $exception1);

        $event = new Event($this->client->getConfig());
        $event->setStacktrace(Stacktrace::createFromBacktrace($this->client, $exception2->getTrace(), $exception2->getFile(), $exception2->getLine()));

        $callbackInvoked = false;
        $callback = function (Event $eventArg) use (&$callbackInvoked) {
            foreach ($eventArg->getStacktrace()->getFrames() as $frame) {
                $this->assertNull($frame->getPreContext());
                $this->assertNull($frame->getContextLine());
                $this->assertNull($frame->getPostContext());
            }

            $callbackInvoked = true;
        };

        $middleware = new RemoveStacktraceContextMiddleware();
        $middleware($event, $callback);

        $this->assertTrue($callbackInvoked);
    }
}
