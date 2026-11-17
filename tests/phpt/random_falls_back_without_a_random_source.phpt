--TEST--
Test that random numbers are still drawn, without failing the application, if the operating system has no random source
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Util {
    // Takes the place of the global function for the calls made from this namespace
    function random_int(int $min, int $max): int
    {
        throw new \Exception('Could not gather sufficient random data');
    }
}

namespace Sentry\Tests {
    use Sentry\Tracing\PropagationContext;
    use Sentry\Util\Random;

    $vendor = __DIR__;

    while (!file_exists($vendor . '/vendor')) {
        $vendor = \dirname($vendor);
    }

    require $vendor . '/vendor/autoload.php';

    mt_srand(42);
    $expected = mt_rand(1, 1000000);

    mt_srand(42);
    var_dump(Random::int(1, 1000000) === $expected);

    $float = Random::float();
    var_dump($float >= 0.0 && $float < 1.0);

    $sampleRand = PropagationContext::fromDefaults()->getSampleRand();
    var_dump($sampleRand >= 0.0 && $sampleRand < 1.0);
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
