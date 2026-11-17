<?php

declare(strict_types=1);

namespace Sentry\Tests\Util;

use PHPUnit\Framework\TestCase;
use Sentry\Util\Random;

final class RandomTest extends TestCase
{
    public function testFloatIsInTheHalfOpenUnitInterval(): void
    {
        for ($i = 0; $i < 1000; ++$i) {
            $value = Random::float();

            $this->assertGreaterThanOrEqual(0.0, $value);
            $this->assertLessThan(1.0, $value);
        }
    }

    public function testIntIsInTheGivenRange(): void
    {
        $values = [];

        for ($i = 0; $i < 1000; ++$i) {
            $value = Random::int(1, 3);

            $this->assertGreaterThanOrEqual(1, $value);
            $this->assertLessThanOrEqual(3, $value);

            $values[$value] = true;
        }

        // Each value is missed with a probability of (2/3)^1000
        $this->assertCount(3, $values);
    }

    public function testIntIsNotAffectedBySeedingTheMersenneTwister(): void
    {
        $values = [];

        for ($i = 0; $i < 5; ++$i) {
            mt_srand(42);

            $values[] = Random::int(0, \PHP_INT_MAX);
        }

        $this->assertGreaterThan(1, \count(array_unique($values)));
    }
}
