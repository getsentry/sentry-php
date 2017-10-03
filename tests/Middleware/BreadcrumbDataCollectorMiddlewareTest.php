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
use Raven\Breadcrumbs\Breadcrumb;
use Raven\Breadcrumbs\Recorder;
use Raven\Client;
use Raven\Configuration;
use Raven\Event;
use Raven\Middleware\BreadcrumbDataCollectorMiddleware;

class BreadcrumbDataCollectorMiddlewareTest extends TestCase
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

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, $breadcrumb, $breadcrumb2, &$invokationCount) {
            $this->assertNotSame($event, $eventArg);
            $this->assertEquals([$breadcrumb, $breadcrumb2], $eventArg->getBreadcrumbs());

            ++$invokationCount;
        };

        $middleware = new BreadcrumbDataCollectorMiddleware($recorder);
        $middleware($event, $callback);

        $this->assertEquals(1, $invokationCount);
    }
}
