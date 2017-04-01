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

use Monolog\Logger;
use Raven\Breadcrumbs\MonologHandler;
use Raven\Breadcrumbs\Breadcrumb;
use Raven\Client;
use Raven\ClientBuilder;

class MonologHandlerTest extends \PHPUnit_Framework_TestCase
{
    protected function getSampleErrorMessage()
    {
        return <<<EOF
exception 'Exception' with message 'An unhandled exception' in /sentry-laravel/examples/laravel-4.2/app/routes.php:17
Stack trace:
#0 [internal function]: {closure}()
#1 /sentry-laravel/examples/laravel-4.2/bootstrap/compiled.php(5398): call_user_func_array(Object(Closure), Array)
#2 /sentry-laravel/examples/laravel-4.2/bootstrap/compiled.php(5065): Illuminate\Routing\Route->run(Object(Illuminate\Http\Request))
#3 /sentry-laravel/examples/laravel-4.2/bootstrap/compiled.php(5053): Illuminate\Routing\Router->dispatchToRoute(Object(Illuminate\Http\Request))
#4 /sentry-laravel/examples/laravel-4.2/bootstrap/compiled.php(715): Illuminate\Routing\Router->dispatch(Object(Illuminate\Http\Request))
#5 /sentry-laravel/examples/laravel-4.2/bootstrap/compiled.php(696): Illuminate\Foundation\Application->dispatch(Object(Illuminate\Http\Request))
#6 /sentry-laravel/examples/laravel-4.2/bootstrap/compiled.php(7825): Illuminate\Foundation\Application->handle(Object(Illuminate\Http\Request), 1, true)
#7 /sentry-laravel/examples/laravel-4.2/bootstrap/compiled.php(8432): Illuminate\Session\Middleware->handle(Object(Illuminate\Http\Request), 1, true)
#8 /sentry-laravel/examples/laravel-4.2/bootstrap/compiled.php(8379): Illuminate\Cookie\Queue->handle(Object(Illuminate\Http\Request), 1, true)
#9 /sentry-laravel/examples/laravel-4.2/bootstrap/compiled.php(11123): Illuminate\Cookie\Guard->handle(Object(Illuminate\Http\Request), 1, true)
#10 /sentry-laravel/examples/laravel-4.2/bootstrap/compiled.php(657): Stack\StackedHttpKernel->handle(Object(Illuminate\Http\Request))
#11 /sentry-laravel/examples/laravel-4.2/public/index.php(49): Illuminate\Foundation\Application->run()
#12 /sentry-laravel/examples/laravel-4.2/server.php(19): require_once('/Users/dcramer/...')
#13 {main}
EOF;
    }

    public function testSimple()
    {
        $client = $client = ClientBuilder::create([
            'install_default_breadcrumb_handlers' => false,
        ])->getClient();

        $handler = new MonologHandler($client);

        $logger = new Logger('sentry');
        $logger->pushHandler($handler);
        $logger->addWarning('foo');

        $breadcrumbsRecorder = $this->getObjectAttribute($client, 'recorder');

        /** @var \Raven\Breadcrumbs\Breadcrumb[] $breadcrumbs */
        $breadcrumbs = iterator_to_array($breadcrumbsRecorder);

        $this->assertCount(1, $breadcrumbs);

        $this->assertEquals($breadcrumbs[0]->getMessage(), 'foo');
        $this->assertEquals($breadcrumbs[0]->getLevel(), Client::LEVEL_WARNING);
        $this->assertEquals($breadcrumbs[0]->getCategory(), 'sentry');
    }

    public function testErrorInMessage()
    {
        $client = $client = ClientBuilder::create([
            'install_default_breadcrumb_handlers' => false,
        ])->getClient();

        $handler = new MonologHandler($client);

        $logger = new Logger('sentry');
        $logger->pushHandler($handler);
        $logger->addError($this->getSampleErrorMessage());

        $breadcrumbsRecorder = $this->getObjectAttribute($client, 'recorder');

        /** @var \Raven\Breadcrumbs\Breadcrumb[] $breadcrumbs */
        $breadcrumbs = iterator_to_array($breadcrumbsRecorder);

        $this->assertCount(1, $breadcrumbs);

        $metaData = $breadcrumbs[0]->getMetadata();

        $this->assertEquals($breadcrumbs[0]->getType(), Breadcrumb::TYPE_ERROR);
        $this->assertEquals($breadcrumbs[0]->getLevel(), Client::LEVEL_ERROR);
        $this->assertEquals($breadcrumbs[0]->getCategory(), 'sentry');
        $this->assertEquals($metaData['value'], 'An unhandled exception');
    }
}
