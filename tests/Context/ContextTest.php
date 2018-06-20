<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Context;

use PHPUnit\Framework\TestCase;
use Raven\Context\Context;

class ContextTest extends TestCase
{
    public function testConstructor()
    {
        $context = new Context(['foo' => 'bar']);

        $this->assertAttributeEquals(['foo' => 'bar'], 'data', $context);
    }

    public function testMerge()
    {
        $context = new Context([
            'foo' => 'bar',
            'bar' => [
                'foobar' => 'barfoo',
            ],
        ]);

        $context->merge(['bar' => ['barfoo' => 'foobar']], true);

        $this->assertAttributeEquals(['foo' => 'bar', 'bar' => ['foobar' => 'barfoo', 'barfoo' => 'foobar']], 'data', $context);

        $context->merge(['bar' => 'foo']);

        $this->assertAttributeEquals(['foo' => 'bar', 'bar' => 'foo'], 'data', $context);
    }

    public function testSetData()
    {
        $context = new Context(['foo' => 'bar']);
        $context->setData(['bar' => 'foo']);

        $this->assertAttributeEquals(['foo' => 'bar', 'bar' => 'foo'], 'data', $context);

        $context->setData(['foo' => ['bar' => 'baz']]);

        $this->assertAttributeEquals(['foo' => ['bar' => 'baz'], 'bar' => 'foo'], 'data', $context);
    }

    public function testReplaceData()
    {
        $context = new Context(['foo' => 'bar']);
        $context->replaceData(['bar' => 'foo']);

        $this->assertAttributeEquals(['bar' => 'foo'], 'data', $context);
    }

    public function testClear()
    {
        $context = new Context(['foo' => 'bar']);

        $this->assertAttributeEquals(['foo' => 'bar'], 'data', $context);

        $context->clear();

        $this->assertAttributeEmpty('data', $context);
    }

    public function testIsEmpty()
    {
        $context = new Context();

        $this->assertTrue($context->isEmpty());

        $context->setData(['foo' => 'bar']);

        $this->assertFalse($context->isEmpty());
    }

    public function testToArray()
    {
        $context = new Context(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $context->toArray());
    }

    public function testJsonSerialize()
    {
        $context = new Context(['foo' => 'bar']);

        $this->assertEquals('{"foo":"bar"}', json_encode($context));
    }

    public function testArrayLikeBehaviour()
    {
        $context = new Context();

        $this->assertAttributeEquals([], 'data', $context);
        $this->assertFalse(isset($context['foo']));

        // Accessing a key that does not exists in the data object should behave
        // like accessing a non-existent key of an array
        @$context['foo'];

        $error = error_get_last();

        $this->assertInternalType('array', $error);
        $this->assertEquals('Undefined index: foo', $error['message']);

        $context['foo'] = 'bar';

        $this->assertAttributeEquals(['foo' => 'bar'], 'data', $context);
        $this->assertTrue(isset($context['foo']));
        $this->assertEquals('bar', $context['foo']);

        unset($context['foo']);

        $this->assertArrayNotHasKey('foo', $context);
    }

    public function testGetIterator()
    {
        $context = new Context(['foo' => 'bar', 'bar' => 'foo']);

        $this->assertEquals($context->toArray(), iterator_to_array($context));
    }
}
