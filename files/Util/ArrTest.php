<?php

declare(strict_types=1);

namespace Sentry\Tests\Util;

use PHPUnit\Framework\TestCase;
use Sentry\Util\Arr;

final class ArrTest extends TestCase
{
    /**
     * @dataProvider simpleDotDataProvider
     */
    public function testSimpleDot(array $value, array $expectedResult): void
    {
        $this->assertSame($expectedResult, Arr::simpleDot($value));
    }

    public static function simpleDotDataProvider(): \Generator
    {
        yield [
            [1, 2, 3],
            [1, 2, 3],
        ];

        yield [
            [
                'key' => 'value',
            ],
            [
                'key' => 'value',
            ],
        ];

        yield [
            [
                'key' => [
                    'key2' => 'value',
                ],
            ],
            [
                'key.key2' => 'value',
            ],
        ];

        yield [
            [
                'key' => ['foo', 'bar'],
            ],
            [
                'key' => ['foo', 'bar'],
            ],
        ];

        yield [
            [
                'key' => [
                    'key2' => ['foo', 'bar'],
                ],
            ],
            [
                'key.key2' => ['foo', 'bar'],
            ],
        ];

        $someClass = new \stdClass();

        yield [
            [
                'key' => $someClass,
            ],
            [
                'key' => $someClass,
            ],
        ];
    }

    /**
     * @dataProvider isListDataProvider
     */
    public function testIsList(array $value, bool $expectedResult): void
    {
        $this->assertSame($expectedResult, Arr::isList($value));
    }

    public static function isListDataProvider(): \Generator
    {
        yield [
            [1, 2, 3],
            true,
        ];

        yield [
            [
                'key' => 'value',
            ],
            false,
        ];
    }
}
