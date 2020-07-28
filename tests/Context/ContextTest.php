<?php

declare(strict_types=1);

namespace Sentry\Tests\Context;

use PHPUnit\Framework\TestCase;
use Sentry\Context\Context;

class ContextTest extends TestCase
{
    public function testConstructor(): void
    {
        $context = new Context(['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $context->toArray());
    }

    public function testMerge(): void
    {
        $context = new Context([
            'foo' => 'bar',
            'bar' => [
                'foobar' => 'barfoo',
            ],
        ]);

        $context->merge(['bar' => ['barfoo' => 'foobar']], true);

        $this->assertSame(['foo' => 'bar', 'bar' => ['foobar' => 'barfoo', 'barfoo' => 'foobar']], $context->toArray());

        $context->merge(['bar' => 'foo']);

        $this->assertSame(['foo' => 'bar', 'bar' => 'foo'], $context->toArray());
    }

    public function testSetData(): void
    {
        $context = new Context(['foo' => 'bar']);
        $context->setData(['bar' => 'foo']);

        $this->assertSame(['foo' => 'bar', 'bar' => 'foo'], $context->toArray());

        $context->setData(['foo' => ['bar' => 'baz']]);

        $this->assertSame(['foo' => ['bar' => 'baz'], 'bar' => 'foo'], $context->toArray());
    }

    public function testReplaceData(): void
    {
        $context = new Context(['foo' => 'bar']);
        $context->replaceData(['bar' => 'foo']);

        $this->assertSame(['bar' => 'foo'], $context->toArray());
    }

    public function testClear(): void
    {
        $context = new Context(['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $context->toArray());

        $context->clear();

        $this->assertSame([], $context->toArray());
    }

    public function testIsEmpty(): void
    {
        $context = new Context();

        $this->assertTrue($context->isEmpty());

        $context->setData(['foo' => 'bar']);

        $this->assertFalse($context->isEmpty());
    }

    public function testJsonSerialize(): void
    {
        $context = new Context(['foo' => 'bar']);

        $this->assertSame('{"foo":"bar"}', json_encode($context));
    }

    public function testArrayLikeBehaviour(): void
    {
        $context = new Context();

        // Accessing a key that does not exists in the data object should behave
        // like accessing a non-existent key of an array
        @$context['foo'];

        $error = error_get_last();

        $this->assertIsArray($error);
        $this->assertSame('Undefined index: foo', $error['message']);

        $context['foo'] = 'bar';

        $this->assertTrue(isset($context['foo']));
        $this->assertSame('bar', $context['foo']);

        unset($context['foo']);

        $this->assertArrayNotHasKey('foo', $context);
    }

    public function testGetIterator(): void
    {
        $context = new Context(['foo' => 'bar', 'bar' => 'foo']);

        $this->assertSame($context->toArray(), iterator_to_array($context));
    }
}
