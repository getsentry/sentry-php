<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\HttpDataCollector;
use Sentry\DataCollection\HttpHeaderNormalizer;

final class HttpHeaderNormalizerTest extends TestCase
{
    public function testNormalizeSupportsHeaderLinesAndMaps(): void
    {
        $this->assertSame([
            'content-type' => ['application/json'],
            'x-request-id' => ['one', 'two', 'three'],
            'x-count' => ['42'],
            123 => ['raw numeric header name'],
            456 => ['mapped numeric header name'],
        ], HttpHeaderNormalizer::normalize([
            'Content-Type: application/json',
            'X-Request-ID' => ['one', 'two'],
            'x-request-id: three',
            'X-Count' => 42,
            '123: raw numeric header name',
            456 => ['mapped numeric header name'],
        ]));
    }

    public function testMixedFormatsPreserveHeaderValueOrder(): void
    {
        $this->assertSame([
            'x-test' => ['first', 'second', 'third', 'fourth', 'fifth'],
            'location' => ['https://example.com/a:b'],
        ], HttpHeaderNormalizer::normalize([
            'X-Test: first',
            'x-test' => ['second'],
            'X-TEST: third',
            ' X-Test ' => ['fourth', 'fifth'],
            "Location: https://example.com/a:b\r\n",
        ]));
    }

    public function testNormalizationIsIdempotentAndDoesNotModifyInput(): void
    {
        $headers = [
            ' X-Test ' => [7, null],
            'x-test: value',
            123 => ['numeric'],
            'X-Nested' => [['secret' => 'must-not-be-collected']],
        ];
        $original = $headers;
        $normalized = HttpHeaderNormalizer::normalize($headers);

        $this->assertSame($original, $headers);
        $this->assertSame([
            'x-test' => ['7', '[Filtered]', 'value'],
            123 => ['numeric'],
            'x-nested' => ['[Filtered]'],
        ], $normalized);
        $this->assertSame($normalized, HttpHeaderNormalizer::normalize($normalized));
    }

    public function testResourceValuesAreNotReadOrClosed(): void
    {
        $resource = fopen('php://temp', 'r+');
        $this->assertIsResource($resource);

        try {
            fwrite($resource, 'must-not-be-collected');
            fseek($resource, 4);

            $this->assertSame(['x-stream' => ['[Filtered]']], HttpHeaderNormalizer::normalize(['X-Stream' => $resource]));
            $this->assertIsResource($resource);
            $this->assertSame(4, ftell($resource));
        } finally {
            fclose($resource);
        }
    }

    public function testNormalizationDoesNotInvokeApplicationCallbacksOrRetainObjects(): void
    {
        $value = new class {
            /**
             * @var string
             */
            public $privateContext = 'must-not-be-collected';

            public function __toString(): string
            {
                throw new \LogicException('Must not be invoked by collection');
            }
        };
        $headers = HttpHeaderNormalizer::normalize(['X-Test' => [$value], 'x-test' => $value, $value]);
        $this->assertSame(['x-test' => ['[Filtered]', '[Filtered]']], $headers);
        $this->assertSame('{"x-test":["[Filtered]","[Filtered]"]}', json_encode($headers));
    }

    public function testNullableAndScalarHeaderBagValuesAreNormalized(): void
    {
        $this->assertSame([
            'x-null' => ['[Filtered]'],
            'x-mixed' => ['[Filtered]', 'value', '7', ''],
        ], HttpHeaderNormalizer::normalize([
            'X-Null' => null,
            'X-Mixed' => [null, 'value', 7, false],
        ]));
    }

    public function testEmptyAndMalformedHeadersAreIgnored(): void
    {
        $this->assertSame(['x-removed' => []], HttpHeaderNormalizer::normalize([
            'Invalid',
            ': empty header name',
            'X-Removed' => [],
            false,
        ]));
    }

    public function testNormalizedHeadersCanBeCollected(): void
    {
        $headers = HttpHeaderNormalizer::normalize([
            'Authorization: Bearer secret',
            'Cookie' => ['session_id=secret'],
            'X-Request-ID' => 'request-id',
        ]);
        $this->assertSame([
            'http.request.header.authorization' => ['[Filtered]'],
            'http.request.header.x-request-id' => ['request-id'],
        ], HttpDataCollector::collectRequestHeaders(new DataCollectionOptions(), $headers));
    }
}
