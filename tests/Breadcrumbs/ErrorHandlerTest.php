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

use Raven\Client;
use Raven\ClientBuilder;

class ErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testSimple()
    {
        $client = $client = ClientBuilder::create([
            'install_default_breadcrumb_handlers' => false,
        ]);

        $breadcrumbsRecorder = $this->getObjectAttribute($client, 'recorder');

        /** @var \Raven\Breadcrumbs\Breadcrumb[] $breadcrumbs */
        $breadcrumbs = iterator_to_array($breadcrumbsRecorder);

        $this->assertCount(1, $breadcrumbs);

        $this->assertEquals($breadcrumbs[0]->getMessage(), 'message');
        $this->assertEquals($breadcrumbs[0]->getLevel(), Client::LEVEL_WARNING);
        $this->assertEquals($breadcrumbs[0]->getCategory(), 'error_reporting');
    }
}
