--TEST--
Test catching out of memory fatal error
--FILE--
<?php

namespace Sentry\Tests;

use PHPUnit\Framework\Assert;
use Sentry\Event;
use Sentry\Severity;
use function Sentry\init;

ini_set('memory_limit', '20M');

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

init([
    'before_send' => function (Event $event): ?Event {
        Assert::assertArrayHasKey(0,  $event->getExceptions());
        $error = $event->getExceptions()[0];
        Assert::assertContains('Allowed memory size', $error['value']);
        Assert::assertTrue($event->getLevel()->isEqualTo(Severity::fatal()));

        echo 'Sending event';

        return null;
    },
]);

$foo = str_repeat('x', 1024 * 1024 * 30);
?>
--EXPECTF--
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d
Sending event
