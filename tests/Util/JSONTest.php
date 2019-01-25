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
    public function testEncode($value, $expectedResult): void
    {
        $this->assertEquals($expectedResult, JSON::encode($value));
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
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Could not encode value into JSON format. Error was: "Type is not supported".
     */
    public function testEncodeThrowsIfValueIsResource(): void
    {
        $resource = fopen('php://memory', 'rb');

        $this->assertNotFalse($resource);

        fclose($resource);

        JSON::encode($resource);
    }
}
