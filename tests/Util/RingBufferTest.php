<?php

declare(strict_types=1);

namespace Sentry\Tests\Util;

use PHPUnit\Framework\TestCase;
use Sentry\Util\RingBuffer;

class RingBufferTest extends TestCase
{
    public function testPush(): void
    {
        $buffer = new RingBuffer(5);
        $buffer->push('foo');
        $buffer->push('bar');

        $result = $buffer->toArray();
        $this->assertSame(2, $buffer->count());
        $this->assertEquals(['foo', 'bar'], $result);
    }

    public function testPeekBack(): void
    {
        $buffer = new RingBuffer(5);
        $buffer->push('foo');
        $buffer->push('bar');

        $this->assertSame(2, $buffer->count());
        $this->assertSame('bar', $buffer->peekBack());
    }

    public function testPeekBackEmpty(): void
    {
        $buffer = new RingBuffer(5);

        $this->assertEmpty($buffer);
        $this->assertNull($buffer->peekBack());
    }

    public function testPeekFront(): void
    {
        $buffer = new RingBuffer(5);
        $buffer->push('foo');
        $buffer->push('bar');

        $this->assertSame(2, $buffer->count());
        $this->assertSame('foo', $buffer->peekFront());
    }

    public function testPeekFrontEmpty(): void
    {
        $buffer = new RingBuffer(5);

        $this->assertEmpty($buffer);
        $this->assertNull($buffer->peekFront());
    }

    public function testFixedCapacity(): void
    {
        $buffer = new RingBuffer(2);
        $buffer->push('foo');
        $buffer->push('bar');
        $buffer->push('baz');

        $this->assertSame(2, $buffer->count());
        $this->assertEquals(['bar', 'baz'], $buffer->toArray());
    }

    public function testClear(): void
    {
        $buffer = new RingBuffer(5);
        $buffer->push('foo');
        $buffer->push('bar');

        $this->assertSame(2, $buffer->count());
        $buffer->clear();
        $this->assertTrue($buffer->isEmpty());
    }

    public function testDrain(): void
    {
        $buffer = new RingBuffer(5);
        $buffer->push('foo');
        $buffer->push('bar');

        $this->assertSame(2, $buffer->count());
        $result = $buffer->drain();
        $this->assertTrue($buffer->isEmpty());
        $this->assertEquals(['foo', 'bar'], $result);
    }

    public function testShift(): void
    {
        $buffer = new RingBuffer(5);
        $buffer->push('foo');
        $buffer->push('bar');

        $this->assertEquals('foo', $buffer->shift());
        $this->assertCount(1, $buffer);
    }

    public function testShiftAndPush(): void
    {
        $buffer = new RingBuffer(5);
        $buffer->push('foo');
        $buffer->push('bar');

        $buffer->shift();

        $buffer->push('baz');

        $this->assertCount(2, $buffer);
        $this->assertEquals(['bar', 'baz'], $buffer->toArray());
    }

    public function testCapacityOne(): void
    {
        $buffer = new RingBuffer(1);
        $buffer->push('foo');
        $buffer->push('bar');

        $this->assertCount(1, $buffer);
        $this->assertSame('bar', $buffer->shift());
    }

    public function testInvalidCapacity(): void
    {
        $this->expectException(\RuntimeException::class);
        $buffer = new RingBuffer(-1);
    }

    public function testIsEmpty(): void
    {
        $buffer = new RingBuffer(5);
        $this->assertTrue($buffer->isEmpty());
    }

    public function testIsFull(): void
    {
        $buffer = new RingBuffer(2);
        $buffer->push('foo');
        $buffer->push('bar');
        $this->assertTrue($buffer->isFull());
    }
}
