<?php

declare(strict_types=1);

namespace Sentry\Tests\Util;

use PHPUnit\Framework\TestCase;
use Sentry\Exception\JsonException;
use Sentry\Tests\Util\Fixtures\JsonSerializableClass;
use Sentry\Tests\Util\Fixtures\NonBackedEnum;
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

    public static function encodeDataProvider(): \Generator
    {
        yield [
            [
                'key' => 'value',
            ],
            '{"key":"value"}',
        ];

        yield [
            'string',
            '"string"',
        ];

        yield [
            123.45,
            '123.45',
        ];

        yield [
            null,
            'null',
        ];

        yield [
            (object) [
                'key' => 'value',
            ],
            '{"key":"value"}',
        ];

        yield [
            new SimpleClass(),
            '{"keyPublic":"public"}',
        ];

        yield [
            new JsonSerializableClass(),
            '{"key":"value"}',
        ];

        $data = [];
        $data['recursive'] = &$data;

        yield [
            $data,
            '{"recursive":{"recursive":null}}',
        ];
    }

    /**
     * @dataProvider encodeSubstitutesInvalidUtf8CharactersDataProvider
     */
    public function testEncodeSubstitutesInvalidUtf8Characters($value, string $expectedResult): void
    {
        $this->assertSame($expectedResult, JSON::encode($value));
    }

    public static function encodeSubstitutesInvalidUtf8CharactersDataProvider(): \Generator
    {
        yield [
            "\x61\xb0\x62",
            '"a�b"',
        ];

        yield [
            "\x61\xf0\x80\x80\x41",
            '"a�A"',
        ];

        yield [
            [
                123.45,
                'foo',
                "\x61\xb0\x62",
                [
                    'bar' => "\x61\xf0\x80\x80\x41",
                    "\x61\xf0\x80\x80\x41" => (object) [
                        "\x61\xb0\x62",
                        "\x61\xf0\x80\x80\x41" => 'baz',
                    ],
                ],
            ],
            '[123.45,"foo","a�b",{"bar":"a�A","a�A":{"0":"a�b","a�A":"baz"}}]',
        ];
    }

    public function testEncodeThrowsIfValueIsResource(): void
    {
        $resource = fopen('php://memory', 'r');

        $this->assertNotFalse($resource);

        fclose($resource);

        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Could not encode value into JSON format. Error was: "Type is not supported".');

        JSON::encode($resource);
    }

    /**
     * @dataProvider encodeThrowsExceptionIfMaxDepthArgumentIsInvalidDataProvider
     */
    public function testEncodeThrowsExceptionIfMaxDepthArgumentIsInvalid(int $maxDepth): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The $maxDepth argument must be an integer greater than 0.');

        JSON::encode('foo', 0, $maxDepth);
    }

    public static function encodeThrowsExceptionIfMaxDepthArgumentIsInvalidDataProvider(): \Generator
    {
        yield [0];
        yield [-1];
    }

    public function testEncodeRespectsOptionsArgument(): void
    {
        $this->assertSame('{}', JSON::encode([], \JSON_FORCE_OBJECT));
    }

    /**
     * The `JSON_ERROR_NON_BACKED_ENUM` constant is only exposed from 8.1.5 and up.
     *
     * @requires PHP >= 8.1.5
     */
    public function testEncodeGracefullyHandlesUnitEnums(): void
    {
        $result = JSON::encode([NonBackedEnum::None, NonBackedEnum::Some]);
        $this->assertSame('[0,0]', $result);
    }

    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $value, $expectedResult): void
    {
        $this->assertSame($expectedResult, JSON::decode($value));
    }

    public static function decodeDataProvider(): \Generator
    {
        yield [
            '{"key":"value"}',
            [
                'key' => 'value',
            ],
        ];

        yield [
            '"string"',
            'string',
        ];

        yield [
            '123.45',
            123.45,
        ];

        yield [
            'null',
            null,
        ];
    }

    public function testDecodeThrowsIfValueIsNotValidJson(): void
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Could not decode value from JSON format. Error was: "Syntax error".');

        JSON::decode('foo');
    }
}
