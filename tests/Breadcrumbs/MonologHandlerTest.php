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
use ParseError;
use PHPUnit\Framework\TestCase;
use Raven\Breadcrumbs\Breadcrumb;
use Raven\Breadcrumbs\MonologHandler;
use Raven\Client;
use Raven\ClientBuilder;

class MonologHandlerTest extends TestCase
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
        $client = $this->createClient();
        $logger = $this->createLoggerWithHandler($client);

        $logger->addWarning('foo');

        $breadcrumbs = $this->getBreadcrumbs($client);
        $this->assertCount(1, $breadcrumbs);
        $this->assertEquals('foo', $breadcrumbs[0]->getMessage());
        $this->assertEquals(Client::LEVEL_WARNING, $breadcrumbs[0]->getLevel());
        $this->assertEquals('sentry', $breadcrumbs[0]->getCategory());
    }

    public function testErrorInMessage()
    {
        $client = $this->createClient();
        $logger = $this->createLoggerWithHandler($client);

        $logger->addError($this->getSampleErrorMessage());

        $breadcrumbs = $this->getBreadcrumbs($client);
        $this->assertCount(1, $breadcrumbs);
        $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumbs[0]->getType());
        $this->assertEquals(Client::LEVEL_ERROR, $breadcrumbs[0]->getLevel());
        $this->assertEquals('sentry', $breadcrumbs[0]->getCategory());
        $this->assertEquals('An unhandled exception', $breadcrumbs[0]->getMetadata()['value']);
    }

    public function testExceptionBeingParsed()
    {
        $client = $this->createClient();
        $logger = $this->createLoggerWithHandler($client);

        $logger->addError('A message', ['exception' => new \Exception('Foo bar')]);

        $breadcrumbs = $this->getBreadcrumbs($client);
        $this->assertCount(1, $breadcrumbs);
        $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumbs[0]->getType());
        $this->assertEquals('Foo bar', $breadcrumbs[0]->getMetadata()['value']);
        $this->assertEquals('sentry', $breadcrumbs[0]->getCategory());
        $this->assertEquals(Client::LEVEL_ERROR, $breadcrumbs[0]->getLevel());
        $this->assertNull($breadcrumbs[0]->getMessage());
    }

    public function testThrowableBeingParsedAsException()
    {
        if (\PHP_VERSION_ID <= 70000) {
            $this->markTestSkipped('PHP 7.0 introduced Throwable');
        }

        $client = $this->createClient();
        $logger = $this->createLoggerWithHandler($client);
        $throwable = new ParseError('Foo bar');

        $logger->addError('This is a throwable', ['exception' => $throwable]);

        $breadcrumbs = $this->getBreadcrumbs($client);
        $this->assertCount(1, $breadcrumbs);
        $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumbs[0]->getType());
        $this->assertEquals('Foo bar', $breadcrumbs[0]->getMetadata()['value']);
        $this->assertEquals('sentry', $breadcrumbs[0]->getCategory());
        $this->assertEquals(Client::LEVEL_ERROR, $breadcrumbs[0]->getLevel());
        $this->assertNull($breadcrumbs[0]->getMessage());
    }

    /**
     * @return Client
     */
    private function createClient()
    {
        $client = $client = ClientBuilder::create()->getClient();

        return $client;
    }

    /**
     * @param Client $client
     *
     * @return Logger
     */
    private function createLoggerWithHandler(Client $client)
    {
        $handler = new MonologHandler($client);
        $logger = new Logger('sentry');
        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * @param Client $client
     *
     * @return Breadcrumb[]
     */
    private function getBreadcrumbs(Client $client)
    {
        $breadcrumbsRecorder = $this->getObjectAttribute($client, 'breadcrumbRecorder');

        $breadcrumbs = iterator_to_array($breadcrumbsRecorder);
        $this->assertContainsOnlyInstancesOf(Breadcrumb::class, $breadcrumbs);

        return $breadcrumbs;
    }
}
