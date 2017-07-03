<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Raven\ClientBuilder;

class Raven_Tests_ErrorHandlerBreadcrumbHandlerTest extends PHPUnit_Framework_TestCase
{
    public function testSimple()
    {
        $client = $client = ClientBuilder::create([
            'install_default_breadcrumb_handlers' => false,
        ])->getClient();

        $handler = new \Raven\Breadcrumbs\ErrorHandler($client);
        $handler->handleError(E_WARNING, 'message');

        $crumbs = $client->breadcrumbs->fetch();

        $this->assertCount(1, $crumbs);
        $this->assertEquals($crumbs[0]['message'], 'message');
        $this->assertEquals($crumbs[0]['category'], 'error_reporting');
        $this->assertEquals($crumbs[0]['level'], 'warning');
    }
}
