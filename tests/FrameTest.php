<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests;

use PHPUnit\Framework\TestCase;
use Raven\Frame;

class FrameTest extends TestCase
{
    public function testConstructor()
    {
        $frame = new Frame('foo', 'bar', 10);

        $this->assertEquals('foo', $frame->getFunctionName());
        $this->assertEquals('bar', $frame->getFile());
        $this->assertEquals(10, $frame->getLine());
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters($getterMethod, $setterMethod, $expectedData)
    {
        $frame = new Frame('foo', 'bar', 10);
        $frame->$setterMethod($expectedData);

        $this->assertEquals($expectedData, $frame->$getterMethod());
    }

    public function gettersAndSettersDataProvider()
    {
        return [
            ['getPreContext', 'setPreContext', ['foo' => 'bar', 'bar' => 'baz']],
            ['getContextLine', 'setContextLine', 'foo bar'],
            ['getPostContext', 'setPostContext', ['bar' => 'foo', 'baz' => 'bar']],
            ['isInApp', 'setIsInApp', true],
            ['getVars', 'setVars', ['foo' => 'bar']],
        ];
    }

    /**
     * @dataProvider toArrayAndJsonSerializeDataProvider
     */
    public function testToArrayAndJsonSerialize($setterMethod, $expectedDataKey, $expectedData)
    {
        $frame = new Frame('foo', 'bar', 10);
        $frame->$setterMethod($expectedData);

        $expectedResult = [
            'function' => 'foo',
            'filename' => 'bar',
            'lineno' => 10,
            $expectedDataKey => $expectedData,
        ];

        $this->assertArraySubset($expectedResult, $frame->toArray());
        $this->assertArraySubset($expectedResult, $frame->jsonSerialize());
    }

    public function toArrayAndJsonSerializeDataProvider()
    {
        return [
            ['setPreContext', 'pre_context', ['foo' => 'bar']],
            ['setContextLine', 'context_line', 'foo bar'],
            ['setPostContext', 'post_context', ['bar' => 'foo']],
            ['setIsInApp', 'in_app', true],
            ['setVars', 'vars', ['baz' => 'bar']],
        ];
    }
}
