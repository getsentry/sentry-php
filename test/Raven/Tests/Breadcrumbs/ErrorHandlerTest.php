<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_Tests_ErrorHandlerBreadcrumbHandlerTest extends \PHPUnit\Framework\TestCase
{
    public function testSimple()
    {
        $client = new \Raven_Client(array(
            'install_default_breadcrumb_handlers' => false,
        ));

        $handler = new \Raven_Breadcrumbs_ErrorHandler($client);
        $handler->handleError(E_WARNING, 'message');

        $crumbs = $client->breadcrumbs->fetch();
        $this->assertEquals(count($crumbs), 1);
        $this->assertEquals($crumbs[0]['message'], 'message');
        $this->assertEquals($crumbs[0]['category'], 'error_reporting');
        $this->assertEquals($crumbs[0]['level'], 'warning');
    }
}
