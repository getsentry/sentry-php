<?php

declare(strict_types=1);

namespace Sentry\Tests\Context;

use PHPUnit\Framework\TestCase;
use Sentry\Context\Context;

class ContextTest extends TestCase
{
    public function testConstructor()
    {
        $context = new Context(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $context->toArray());
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

        $this->assertEquals(['foo' => 'bar', 'bar' => ['foobar' => 'barfoo', 'barfoo' => 'foobar']], $context->toArray());

        $context->merge(['bar' => 'foo']);

        $this->assertEquals(['foo' => 'bar', 'bar' => 'foo'], $context->toArray());
    }

    public function testSetData()
    {
        $context = new Context(['foo' => 'bar']);
        $context->setData(['bar' => 'foo']);

        $this->assertEquals(['foo' => 'bar', 'bar' => 'foo'], $context->toArray());

        $context->setData(['foo' => ['bar' => 'baz']]);

        $this->assertEquals(['foo' => ['bar' => 'baz'], 'bar' => 'foo'], $context->toArray());
    }

    public function testReplaceData()
    {
        $context = new Context(['foo' => 'bar']);
        $context->replaceData(['bar' => 'foo']);

        $this->assertEquals(['bar' => 'foo'], $context->toArray());
    }

    public function testClear()
    {
        $context = new Context(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $context->toArray());

        $context->clear();

        $this->assertEmpty($context->toArray());
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

        $this->assertEquals([], $context->toArray());
        $this->assertArrayNotHasKey('foo', $context);

        // Accessing a key that does not exists in the data object should behave
        // like accessing a non-existent key of an array
        @$context['foo'];

        $error = error_get_last();

        $this->assertIsArray($error);
        $this->assertEquals('Undefined index: foo', $error['message']);

        $context['foo'] = 'bar';

        $this->assertEquals(['foo' => 'bar'], $context->toArray());
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
