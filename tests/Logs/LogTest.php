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
