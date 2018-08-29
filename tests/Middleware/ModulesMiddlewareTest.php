<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Raven\Configuration;
use Raven\Event;
use Raven\Middleware\ModulesMiddleware;

class ModulesMiddlewareTest extends TestCase
{
    public function testInvoke()
    {
        $configuration = new Configuration(['project_root' => __DIR__ . '/../Fixtures']);
        $event = new Event($configuration);

        $callbackInvoked = false;
        $callback = function (Event $eventArg) use (&$callbackInvoked) {
            $this->assertEquals(['foo/bar' => '1.2.3.0', 'foo/baz' => '4.5.6.0'], $eventArg->getModules());

            $callbackInvoked = true;
        };

        $middleware = new ModulesMiddleware($configuration);
        $middleware($event, $callback);

        $this->assertTrue($callbackInvoked);
    }
}
