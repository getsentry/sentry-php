<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

final class SpanContextTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @dataProvider fromTraceparentDataProvider
     *
     * @group legacy
     */
    public function testFromTraceparent(string $header, ?SpanId $expectedSpanId, ?TraceId $expectedTraceId, ?bool $expectedSampled): void
    {
        $this->expectDeprecation('The Sentry\\Tracing\\SpanContext::fromTraceparent() method is deprecated since version 3.1 and will be removed in 4.0. Use TransactionContext::fromSentryTrace() instead.');

        $spanContext = SpanContext::fromTraceparent($header);

        if (null !== $expectedSpanId) {
            $this->assertEquals($expectedSpanId, $spanContext->getParentSpanId());
        }

        if (null !== $expectedTraceId) {
            $this->assertEquals($expectedTraceId, $spanContext->getTraceId());
        }

        $this->assertSame($expectedSampled, $spanContext->getSampled());
    }

    public function fromTraceparentDataProvider(): iterable
    {
        yield [
            '0',
            null,
            null,
            false,
        ];

        yield [
            '1',
            null,
            null,
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            false,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            null,
        ];
    }
}
