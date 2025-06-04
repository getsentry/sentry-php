<?php

declare(strict_types=1);

namespace Sentry\Tests\Logs;

use PHPUnit\Framework\TestCase;
use Sentry\Attributes\Attribute;
use Sentry\Logs\Log;
use Sentry\Logs\LogLevel;

/**
 * @phpstan-import-type AttributeValue from Attribute
 * @phpstan-import-type AttributeSerialized from Attribute
 */
final class LogTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $log = new Log(1.0, '123', LogLevel::debug(), 'foo');

        $this->assertSame(1.0, $log->getTimestamp());
        $this->assertSame('123', $log->getTraceId());
        $this->assertSame(LogLevel::debug(), $log->getLevel());
        $this->assertSame('foo', $log->getBody());
        $this->assertSame([], $log->attributes()->toArray());

        $log->setTimestamp(2.0);
        $this->assertSame(2.0, $log->getTimestamp());

        $log->setTraceId('456');
        $this->assertSame('456', $log->getTraceId());

        $log->setLevel(LogLevel::warn());
        $this->assertSame(LogLevel::warn(), $log->getLevel());

        $log->setBody('bar');
        $this->assertSame('bar', $log->getBody());
    }

    public function testJsonSerializesToExpected(): void
    {
        $timestamp = microtime(true);

        $log = new Log($timestamp, '123', LogLevel::debug(), 'foo');

        $log->setAttribute('foo', 'bar');
        $log->setAttribute('should-be-missing', ['foo' => 'bar']);

        $this->assertEquals(
            [
                'timestamp' => $timestamp,
                'trace_id' => '123',
                'level' => 'debug',
                'body' => 'foo',
                'attributes' => [
                    'foo' => [
                        'type' => 'string',
                        'value' => 'bar',
                    ],
                ],
            ],
            $log->jsonSerialize()
        );
    }
}
