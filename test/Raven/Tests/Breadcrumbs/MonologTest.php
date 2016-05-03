<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_Tests_MonologBreadcrumbHandlerTest extends PHPUnit_Framework_TestCase
{
    public function testSimple()
    {
        $client = new \Raven_Client();
        $handler = new \Raven_Breadcrumbs_MonologHandler($client);

        $logger = new Monolog\Logger('sentry');
        $logger->pushHandler($handler);
        $logger->addWarning('Foo');

        $crumbs = $client->breadcrumbs->fetch();
        $this->assertEquals(count($crumbs), 1);
        $this->assertEquals($crumbs[0]['message'], 'Foo');
        $this->assertEquals($crumbs[0]['category'], 'sentry');
        $this->assertEquals($crumbs[0]['level'], 'warning');
    }
}
