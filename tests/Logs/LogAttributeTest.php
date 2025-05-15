<?php

declare(strict_types=1);

namespace Sentry\Tests\Logs;

use PHPUnit\Framework\TestCase;
use Sentry\Attributes\Attribute;

/**
 * @phpstan-import-type AttributeValue from Attribute
 * @phpstan-import-type AttributeSerialized from Attribute
 */
final class LogAttributeTest extends TestCase
{
    /**
     * @param AttributeValue           $value
     * @param AttributeSerialized|null $expected
     *
     * @dataProvider fromValueDataProvider
     */
    public function testFromValue($value, $expected): void
    {
        $attribute = Attribute::tryFromValue($value);

        if ($expected === null) {
            $this->assertNull($attribute);

            return;
        }

        $this->assertEquals($expected, $attribute->jsonSerialize());
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
            new class {},
            null,
        ];

        yield [
            new \stdClass(),
            null,
        ];

        yield [
            ['key' => 'value'],
            null,
        ];
    }
}
