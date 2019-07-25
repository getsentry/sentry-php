<?php

declare(strict_types=1);

namespace Sentry\Tests\Context;

use PHPUnit\Framework\TestCase;
use Sentry\Context\TagsContext;

class TagsContextTest extends TestCase
{
    /**
     * @dataProvider mergeDataProvider
     */
    public function testMerge($data, $recursive, $expectedData, $expectedExceptionMessage)
    {
        if (null !== $expectedExceptionMessage) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $context = new TagsContext(['foo' => 'bar', 'bar' => 'foo']);
        $context->merge($data, $recursive);

        $this->assertEquals($expectedData, $context->toArray());
    }

    public function mergeDataProvider()
    {
        return [
            [
                ['foo' => 'baz', 'baz' => 'foo', 'int' => 1, 'float' => 1.1],
                false,
                ['foo' => 'baz', 'bar' => 'foo', 'baz' => 'foo', 'int' => '1', 'float' => '1.1'],
                null,
            ],
            [
                ['foo' => 'bar'],
                true,
                null,
                'The tags context does not allow recursive merging of its data.',
            ],
            [
                ['foo' => new \stdClass()],
                false,
                null,
                'The $data argument must contains a simple array of string values.',
            ],
        ];
    }

    /**
     * @dataProvider setDataDataProvider
     */
    public function testSetData($data, $expectException)
    {
        if ($expectException) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('The $data argument must contains a simple array of string values.');
        }

        $context = new TagsContext();
        $context->setData($data);

        $this->assertEquals(['foo' => 'bar'], $context->toArray());
    }

    public function setDataDataProvider()
    {
        return [
            [
                ['foo' => 'bar'],
                false,
            ],
            [
                [new \stdClass()],
                true,
            ],
        ];
    }

    /**
     * @dataProvider replaceDataDataProvider
     */
    public function testReplaceData($data, $expectException)
    {
        if ($expectException) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('The $data argument must contains a simple array of string values.');
        }

        $context = new TagsContext(['foo', 'bar']);
        $context->replaceData($data);

        $this->assertEquals(['bar', 'foo'], $context->toArray());
    }

    public function replaceDataDataProvider()
    {
        return [
            [
                ['bar', 'foo'],
                false,
            ],
            [
                [new \stdClass()],
                true,
            ],
        ];
    }

    /**
     * @dataProvider offsetSetDataProvider
     */
    public function testOffsetSet($offset, $value, $expectedExceptionMessage)
    {
        if (null !== $expectedExceptionMessage) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $context = new TagsContext();
        $context[$offset] = $value;

        $this->assertEquals(['foo' => 'bar'], $context->toArray());
    }

    public function offsetSetDataProvider()
    {
        return [
            [
                'foo',
                'bar',
                null,
            ],
            [
                'foo',
                new \stdClass(),
                'The $value argument must be a string.',
            ],
        ];
    }
}
