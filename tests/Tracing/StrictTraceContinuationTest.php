<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\Tracing\PropagationContext;
use Sentry\Tracing\TransactionContext;

final class StrictTraceContinuationTest extends TestCase
{
    private const SENTRY_TRACE_HEADER = '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1';

    protected function setUp(): void
    {
        parent::setUp();

        SentrySdk::setCurrentHub(new Hub());
    }

    /**
     * @dataProvider strictTraceContinuationDataProvider
     */
    public function testPropagationContext(Options $options, string $baggage, bool $expectedContinueTrace): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('getOptions')
            ->willReturn($options);

        SentrySdk::setCurrentHub(new Hub($client));

        $contexts = [
            PropagationContext::fromHeaders(self::SENTRY_TRACE_HEADER, $baggage),
            PropagationContext::fromEnvironment(self::SENTRY_TRACE_HEADER, $baggage),
        ];

        foreach ($contexts as $context) {
            if ($expectedContinueTrace) {
                $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $context->getTraceId());
                $this->assertSame('566e3688a61d4bc8', (string) $context->getParentSpanId());
            } else {
                $this->assertNotSame('566e3688a61d4bc888951642d6f14a19', (string) $context->getTraceId());
                $this->assertNotEmpty((string) $context->getTraceId());
                $this->assertNull($context->getParentSpanId());
                $this->assertNull($context->getDynamicSamplingContext());
            }
        }
    }

    /**
     * @dataProvider strictTraceContinuationDataProvider
     */
    public function testTransactionContext(Options $options, string $baggage, bool $expectedContinueTrace): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('getOptions')
            ->willReturn($options);

        SentrySdk::setCurrentHub(new Hub($client));

        $contexts = [
            TransactionContext::fromHeaders(self::SENTRY_TRACE_HEADER, $baggage),
            TransactionContext::fromEnvironment(self::SENTRY_TRACE_HEADER, $baggage),
        ];

        foreach ($contexts as $context) {
            if ($expectedContinueTrace) {
                $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $context->getTraceId());
                $this->assertSame('566e3688a61d4bc8', (string) $context->getParentSpanId());
                $this->assertTrue($context->getParentSampled());
            } else {
                $this->assertNotSame('566e3688a61d4bc888951642d6f14a19', (string) $context->getTraceId());
                $this->assertNull($context->getParentSpanId());
                $this->assertNull($context->getParentSampled());
                $this->assertNull($context->getMetadata()->getDynamicSamplingContext());
            }
        }
    }

    public static function strictTraceContinuationDataProvider(): \Generator
    {
        yield [
            new Options([
                'strict_trace_continuation' => false,
                'org_id' => 1,
            ]),
            'sentry-org_id=1',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => false,
                'org_id' => 1,
            ]),
            '',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => false,
            ]),
            'sentry-org_id=1',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => false,
            ]),
            '',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => false,
                'org_id' => 2,
            ]),
            'sentry-org_id=1',
            false,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
                'org_id' => 1,
            ]),
            'sentry-org_id=1',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
                'org_id' => 1,
            ]),
            '',
            false,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
            ]),
            'sentry-org_id=1',
            false,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
            ]),
            '',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
                'org_id' => 2,
            ]),
            'sentry-org_id=1',
            false,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
                'dsn' => 'http://public@o1.example.com/1',
            ]),
            'sentry-org_id=1',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
                'dsn' => 'http://public@o1.example.com/1',
                'org_id' => 2,
            ]),
            'sentry-org_id=1',
            false,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => true,
                'dsn' => 'http://public@o1.example.com/1',
                'org_id' => 2,
            ]),
            'sentry-org_id=2',
            true,
        ];

        yield [
            new Options([
                'strict_trace_continuation' => false,
                'org_id' => 1,
            ]),
            'sentry-org_id=01',
            false,
        ];
    }
}
