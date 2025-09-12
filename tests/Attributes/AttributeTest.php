<?php

declare(strict_types=1);

namespace Sentry\Tests\Attributes;

use PHPUnit\Framework\TestCase;
use Sentry\Attributes\Attribute;

/**
 * @phpstan-import-type AttributeType from Attribute
 * @phpstan-import-type AttributeValue from Attribute
 */
final class AttributeTest extends TestCase
{
    /**
     * @param AttributeValue                                         $value
     * @param array{type: AttributeType, value: AttributeValue}|null $expected
     *
     * @dataProvider fromValueDataProvider
     */
    public function testFromValue($value, ?array $expected): void
    {
        $attribute = Attribute::tryFromValue($value);

        if ($attribute === null || $expected === null) {
            $this->assertNull($attribute);

            return;
        }

        $this->assertEquals($expected['type'], $attribute->getType());
        $this->assertEquals($expected['value'], $attribute->getValue());
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
            [],
            null,
        ];
    }

    public function testSerializeAsString(): void
    {
        $attribute = Attribute::tryFromValue('foo');

        $this->assertInstanceOf(Attribute::class, $attribute);

        $this->assertEquals(
            'foo (string)',
            (string) $attribute
        );
    }

    public function testFromValueFactoryMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Attribute::fromValue([]);
    }
}
