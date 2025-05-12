<?php

declare(strict_types=1);

namespace Sentry\Tests\Logs;

use PHPUnit\Framework\TestCase;
use Sentry\Logs\LogAttribute;

/**
 * @phpstan-import-type AttributeValue from LogAttribute
 * @phpstan-import-type AttributeSerialized from LogAttribute
 */
final class LogAttributeTest extends TestCase
{
    /**
     * @param AttributeValue      $value
     * @param AttributeSerialized $expected
     *
     * @dataProvider fromValueDataProvider
     */
    public function testFromValue($value, array $expected, bool $expectError = false): void
    {
        if ($expectError) {
            $this->expectException(\Error::class);
        }

        $this->assertEquals($expected, LogAttribute::fromValue($value)->jsonSerialize());
    }

    public static function fromValueDataProvider(): \Generator
    {
        yield [
            'foo',
            [
                'type' => 'string',
                'value' => 'foo',
            ],
        ];

        yield [
            123,
            [
                'type' => 'integer',
                'value' => 123,
            ],
        ];

        yield [
            123.33,
            [
                'type' => 'double',
                'value' => 123.33,
            ],
        ];

        yield [
            true,
            [
                'type' => 'boolean',
                'value' => true,
            ],
        ];

        yield [
            new class {
                public function __toString(): string
                {
                    return 'foo';
                }
            },
            [
                'type' => 'string',
                'value' => 'foo',
            ],
        ];

        yield [
            new class {}, // not stringable or scalar
            [],
            true,
        ];
    }
}
