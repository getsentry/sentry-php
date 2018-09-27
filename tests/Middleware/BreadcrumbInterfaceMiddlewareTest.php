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
use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Breadcrumbs\Recorder;
use Sentry\Client;
use Sentry\Configuration;
use Sentry\Event;
use Sentry\Middleware\BreadcrumbInterfaceMiddleware;

class BreadcrumbInterfaceMiddlewareTest extends TestCase
{
    public function testInvoke()
    {
        $breadcrumb = new Breadcrumb(Client::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $breadcrumb2 = new Breadcrumb(Client::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'bar');

        $recorder = new Recorder();
        $recorder->record($breadcrumb);
        $recorder->record($breadcrumb2);

        $configuration = new Configuration();
        $event = new Event($configuration);

        $callbackInvoked = false;
        $callback = function (Event $eventArg) use ($breadcrumb, $breadcrumb2, &$callbackInvoked) {
            $this->assertEquals([$breadcrumb, $breadcrumb2], $eventArg->getBreadcrumbs());

            $callbackInvoked = true;
        };

        $middleware = new BreadcrumbInterfaceMiddleware($recorder);
        $middleware($event, $callback);

        $this->assertTrue($callbackInvoked);
    }
}
