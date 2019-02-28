--TEST--
Test that the error handler ignores silenced errors by default, but it reports them with the appropriate option enabled.
--FILE--
<?php

namespace Sentry\Tests;

use function Sentry\init;
use Sentry\State\Hub;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

init([
    'before_send' => function () {
        echo 'Event captured' . PHP_EOL;
    }    
]);

echo 'Triggering silenced error' . PHP_EOL;
@$a['missing'];
echo 'End first test' . PHP_EOL;
Hub::getCurrent()
    ->getClient()
    ->getOptions()
    ->setCaptureSilencedErrors(true);
echo 'Triggering silenced error' . PHP_EOL;
@$a['missing'];
echo 'End'
?>
--EXPECTF--
Triggering silenced error
End first test
Triggering silenced error
Event captured
End
