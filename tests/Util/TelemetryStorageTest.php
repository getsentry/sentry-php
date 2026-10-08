<?php

declare(strict_types=1);

namespace Sentry\Tests\Util;

use PHPUnit\Framework\TestCase;
use Sentry\Util\TelemetryStorage;

final class TelemetryStorageTest extends TestCase
{
    public function testUnboundedPushAndToArray(): void
    {
        $storage = TelemetryStorage::unbounded();
        $storage->push('foo');
        $storage->push('bar');

        $result = $storage->toArray();
        $this->assertSame(2, $storage->count());
        $this->assertEquals(['foo', 'bar'], $result);
    }

    public function testUnboundedDrainClearsStorage(): void
    {
        $storage = TelemetryStorage::unbounded();
        $storage->push('foo');
        $storage->push('bar');

        $this->assertSame(2, $storage->count());
        $result = $storage->drain();
        $this->assertTrue($storage->isEmpty());
        $this->assertEquals(['foo', 'bar'], $result);
    }

    public function testUnboundedIsEmpty(): void
    {
        $storage = TelemetryStorage::unbounded();
        $this->assertTrue($storage->isEmpty());

        $storage->push('foo');

        $this->assertFalse($storage->isEmpty());
    }

    public function testBoundedCapacityOverwritesOldestItems(): void
    {
        $storage = TelemetryStorage::bounded(2);
        $storage->push('foo');
        $storage->push('bar');
        $storage->push('baz');

        $this->assertSame(2, $storage->count());
        $this->assertEquals(['bar', 'baz'], $storage->toArray());
    }

    public function testBoundedDrainReturnsLogicalOrderAndClearsStorage(): void
    {
        $storage = TelemetryStorage::bounded(2);
        $storage->push('foo');
        $storage->push('bar');
        $storage->push('baz');

        $this->assertSame(2, $storage->count());
        $result = $storage->drain();
        $this->assertTrue($storage->isEmpty());
        $this->assertEquals(['bar', 'baz'], $result);
    }

    public function testBoundedCapacityOneKeepsLatestItem(): void
    {
        $storage = TelemetryStorage::bounded(1);
        $storage->push('foo');
        $storage->push('bar');

        $this->assertCount(1, $storage);
        $this->assertEquals(['bar'], $storage->toArray());
    }
}
