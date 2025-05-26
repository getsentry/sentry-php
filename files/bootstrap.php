<?php

declare(strict_types=1);

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\Metrics\Metrics;
use Sentry\Tracing\Span;
use Sentry\Transport\RateLimiter;
use Symfony\Bridge\PhpUnit\ClockMock;

require_once __DIR__ . '/../vendor/autoload.php';

// According to the Symfony documentation the proper way to register the mocked
// functions for a certain class would be to configure the listener in the
// phpunit.xml file, however in our case it doesn't work because PHPUnit loads
// the data providers of the tests long before instantiating the listeners. In
// turn, PHP caches the functions to call to avoid looking up in the function
// table again and again, therefore if for any reason the method that should use
// a mocked function gets called before the mock itself gets created it will not
// use the mocked methods.
//
// See https://symfony.com/doc/current/components/phpunit_bridge.html#troubleshooting
// See https://bugs.php.net/bug.php?id=64346
ClockMock::register(Event::class);
ClockMock::register(Breadcrumb::class);
ClockMock::register(Span::class);
ClockMock::register(RateLimiter::class);
ClockMock::register(Metrics::class);
