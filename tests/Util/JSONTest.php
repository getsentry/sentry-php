<?php

declare(strict_types=1);

namespace Sentry\Tests\Util;

use PHPUnit\Framework\TestCase;
use Sentry\Tests\Util\Fixtures\JsonSerializableClass;
use Sentry\Tests\Util\Fixtures\SimpleClass;
use Sentry\Util\JSON;

final class JSONTest extends TestCase
{
    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode($value, string $expectedResult): void
    {
        $this->assertSame($expectedResult, JSON::encode($value));
    }

    public function encodeDataProvider(): array
    {
        return [
            [
                [
                    'key' => 'value',
                ],
                '{"key":"value"}',
            ],
            [
                'string',
                '"string"',
            ],
            [
                123.45,
                '123.45',
            ],
            [
                null,
                'null',
            ],
            [
                (object) [
                    'key' => 'value',
                ],
                '{"key":"value"}',
            ],
            [
                new SimpleClass(),
                '{"keyPublic":"public"}',
            ],
            [
                new JsonSerializableClass(),
                '{"key":"value"}',
            ],
        ];
    }

    /**
     * @expectedException \Sentry\Exception\JsonException
     * @expectedExceptionMessage Could not encode value into JSON format. Error was: "Type is not supported".
     */
    public function testEncodeThrowsIfValueIsResource(): void
    {
        $resource = fopen('php://memory', 'r');

        $this->assertNotFalse($resource);

        fclose($resource);

        JSON::encode($resource);
    }

    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $value, $expectedResult): void
    {
        $this->assertSame($expectedResult, JSON::decode($value));
    }

    public function decodeDataProvider(): array
    {
        return [
            [
                '{"key":"value"}',
                [
                    'key' => 'value',
                ],
            ],
            [
                '"string"',
                'string',
            ],
            [
                '123.45',
                123.45,
            ],
            [
                'null',
                null,
            ],
        ];
    }

    /**
     * @expectedException \Sentry\Exception\JsonException
     * @expectedExceptionMessage Could not decode value from JSON format. Error was: "Syntax error".
     */
    public function testDecodeThrowsIfValueIsNotValidJson(): void
    {
        JSON::decode('foo');
    }
}
