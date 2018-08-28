<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Util;

use PHPUnit\Framework\TestCase;
use Raven\Tests\Util\Fixtures\JsonSerializableClass;
use Raven\Tests\Util\Fixtures\SimpleClass;
use Raven\Util\JSON;

class JSONTest extends TestCase
{
    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode($value, $expectedResult)
    {
        $this->assertEquals($expectedResult, JSON::encode($value));
    }

    public function encodeDataProvider()
    {
        $obj = new \stdClass();
        $obj->key = 'value';

        return [
            [['key' => 'value'], '{"key":"value"}'],
            ['string', '"string"'],
            [123.45, '123.45'],
            [null, 'null'],
            [$obj, '{"key":"value"}'],
            [new SimpleClass(), '{"keyPublic":"public"}'],
            [new JsonSerializableClass(), '{"key":"value"}'],
        ];
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Could not encode value into JSON format. Error was: "Type is not supported".
     */
    public function testEncodeThrowsIfValueIsResource()
    {
        $resource = fopen('php://memory', 'rb');

        fclose($resource);

        JSON::encode($resource);
    }
}
