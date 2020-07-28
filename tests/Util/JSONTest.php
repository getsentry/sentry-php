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

    public function encodeDataProvider(): \Generator
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
    }

    /**
     * @requires PHP >= 7.2
     *
     * @dataProvider encodeSubstitutesInvalidUtf8CharactersOnPhp72OrGreaterDataProvider
     */
    public function testEncodeSubstitutesInvalidUtf8CharactersOnPhp72OrGreater($value, string $expectedResult): void
    {
        $this->assertSame($expectedResult, JSON::encode($value));
    }

    public function encodeSubstitutesInvalidUtf8CharactersOnPhp72OrGreaterDataProvider(): \Generator
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

    /**
     * @requires PHP < 7.2
     *
     * @dataProvider encodeSubstitutesInvalidUtf8CharactersOnPhp71OrLowerDataProvider
     */
    public function testEncodeSubstitutesInvalidUtf8CharactersOnPhp71OrLower($value, string $expectedResult): void
    {
        $this->assertSame($expectedResult, JSON::encode($value));
    }

    public function encodeSubstitutesInvalidUtf8CharactersOnPhp71OrLowerDataProvider(): \Generator
    {
        yield [
            "\x61\xb0\x62",
            '"a�b"',
        ];

        yield [
            "\x61\xf0\x80\x80\x41",
            '"a���A"',
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
            '[123.45,"foo","a�b",{"bar":"a���A","a���A":{"0":"a�b","a���A":"baz"}}]',
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

    public function testEncodeRespectsOptionsArgument(): void
    {
        $this->assertSame('{}', JSON::encode([], JSON_FORCE_OBJECT));
    }

    /**
     * @requires PHP < 7.2
     *
     * @expectedException \Sentry\Exception\JsonException
     * @expectedExceptionMessage Reached the maximum depth limit while sanitizing the data.
     */
    public function testEncodeThrowsOnPhp71OrLowerWhenSanitizationReachesMaxDepthLimit(): void
    {
        JSON::encode([[["\x61\xb0\x62"]]], 0, 2);
    }

    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $value, $expectedResult): void
    {
        $this->assertSame($expectedResult, JSON::decode($value));
    }

    public function decodeDataProvider(): \Generator
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

    /**
     * @expectedException \Sentry\Exception\JsonException
     * @expectedExceptionMessage Could not decode value from JSON format. Error was: "Syntax error".
     */
    public function testDecodeThrowsIfValueIsNotValidJson(): void
    {
        JSON::decode('foo');
    }
}
