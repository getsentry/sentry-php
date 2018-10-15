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

use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Breadcrumbs\Recorder;
use Sentry\Client;
use Sentry\Middleware\BreadcrumbInterfaceMiddleware;

class BreadcrumbInterfaceMiddlewareTest extends MiddlewareTestCase
{
    public function testInvoke()
    {
        $breadcrumb = new Breadcrumb(Client::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $breadcrumb2 = new Breadcrumb(Client::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'bar');

        $recorder = new Recorder();
        $recorder->record($breadcrumb);
        $recorder->record($breadcrumb2);

        $middleware = new BreadcrumbInterfaceMiddleware($recorder);

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware);

        $this->assertEquals([$breadcrumb, $breadcrumb2], $returnedEvent->getBreadcrumbs());
    }
}
