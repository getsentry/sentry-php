<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_Tests_MonologBreadcrumbHandlerTest extends \PHPUnit\Framework\TestCase
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
        $client = new \Raven_Client(array(
            'install_default_breadcrumb_handlers' => false,
        ));
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

    public function testErrorInMessage()
    {
        $client = new \Raven_Client(array(
            'install_default_breadcrumb_handlers' => false,
        ));
        $handler = new \Raven_Breadcrumbs_MonologHandler($client);

        $logger = new Monolog\Logger('sentry');
        $logger->pushHandler($handler);
        $logger->addError($this->getSampleErrorMessage());

        $crumbs = $client->breadcrumbs->fetch();
        $this->assertEquals(count($crumbs), 1);
        $this->assertEquals($crumbs[0]['data']['type'], 'Exception');
        $this->assertEquals($crumbs[0]['data']['value'], 'An unhandled exception');
        $this->assertEquals($crumbs[0]['category'], 'sentry');
        $this->assertEquals($crumbs[0]['level'], 'error');
    }

    public function testExceptionBeingParsed()
    {
        $client = new \Raven_Client(array(
            'install_default_breadcrumb_handlers' => false,
        ));
        $handler = new \Raven_Breadcrumbs_MonologHandler($client);
        $exception = new Exception('Foo bar');

        $logger = new Monolog\Logger('sentry');
        $logger->pushHandler($handler);
        $logger->addError('This is an exception', compact('exception'));

        $crumbs = $client->breadcrumbs->fetch();

        $this->assertEquals(count($crumbs), 1);
        $this->assertEquals($crumbs[0]['data']['type'], get_class($exception));
        $this->assertEquals($crumbs[0]['data']['value'], 'Foo bar');
        $this->assertEquals($crumbs[0]['category'], 'sentry');
        $this->assertEquals($crumbs[0]['level'], 'error');
    }

    public function testThrowableBeingParsedAsException()
    {
        if (PHP_VERSION_ID <= 70000) {
            $this->markTestSkipped('PHP 7.0 introduced Throwable');
        }

        $client = new \Raven_Client(array(
            'install_default_breadcrumb_handlers' => false,
        ));
        $handler = new \Raven_Breadcrumbs_MonologHandler($client);
        $throwable = new ParseError('Foo bar');

        $logger = new Monolog\Logger('sentry');
        $logger->pushHandler($handler);
        $logger->addError('This is an throwable', array('exception' => $throwable));

        $crumbs = $client->breadcrumbs->fetch();

        $this->assertEquals(count($crumbs), 1);
        $this->assertEquals($crumbs[0]['data']['type'], get_class($throwable));
        $this->assertEquals($crumbs[0]['data']['value'], 'Foo bar');
        $this->assertEquals($crumbs[0]['category'], 'sentry');
        $this->assertEquals($crumbs[0]['level'], 'error');
    }
}
