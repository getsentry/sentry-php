--TEST--
A fiber canceled during before_send propagates the runtime's exception
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80100) {
    die('skip - fibers require PHP 8.1');
}
?>
--FILE--
<?php
declare(strict_types=1);

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\SentrySdk;

$vendor = __DIR__;
while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor . '/vendor/autoload.php';

$cancellation = new RuntimeException('Request canceled');
SentrySdk::setCancellationDetector(static function (Throwable $exception) use ($cancellation): bool {
    return $exception === $cancellation;
});
$client = ClientBuilder::create([
    'default_integrations' => false,
    'before_send' => static function (Event $event): Event {
        Fiber::suspend();

        return $event;
    },
])->getClient();
$fiber = new Fiber(static function () use ($client): void {
    $client->captureEvent(Event::createEvent());
    echo "Continued after capture\n";
});

$fiber->start();
try {
    $fiber->throw($cancellation);
} catch (Throwable $exception) {
    var_dump($exception === $cancellation);
}
?>
--EXPECT--
bool(true)
