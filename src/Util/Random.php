<?php

declare(strict_types=1);

namespace Sentry\Util;

/**
 * Random numbers for sampling decisions. They are drawn from the random source of the
 * operating system, which has no state a forked process could inherit: the workers of
 * a process pool would otherwise continue the same sequence.
 *
 * @internal
 */
final class Random
{
    /**
     * Returns a random float in the range [0, 1).
     */
    public static function float(): float
    {
        return self::int(0, mt_getrandmax() - 1) / mt_getrandmax();
    }

    /**
     * Returns a random integer in the range [$min, $max].
     */
    public static function int(int $min, int $max): int
    {
        try {
            return random_int($min, $max);
        } catch (\Exception $exception) {
            // Without a random source of the operating system, the SDK must not fail the application
            return mt_rand($min, $max);
        }
    }
}
