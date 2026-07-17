<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Options;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\SamplingContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionMetadata;
use Sentry\Tracing\TransactionSampler;

final class TransactionSamplerTest extends TestCase
{
    /**
     * @dataProvider sampleTransactionDataProvider
     */
    public function testSampleTransaction(Options $options, TransactionContext $transactionContext, bool $expectedSampled): void
    {
        $transaction = $this->sampleTransaction($options, $transactionContext);

        $this->assertSame($expectedSampled, $transaction->getSampled());
    }

    public function testIgnoresBaggageSampleRateWithoutSentryTrace(): void
    {
        $transactionContext = TransactionContext::fromHeaders('', 'sentry-sample_rate=1');
        $transaction = $this->sampleTransaction(new Options([
            'traces_sample_rate' => 0.0,
        ]), $transactionContext);

        $this->assertFalse($transaction->getSampled());
    }

    public static function sampleTransactionDataProvider(): iterable
    {
        yield 'Acceptable float value returned from traces_sampler' => [
            new Options([
                'traces_sampler' => static function (): float {
                    return 1.0;
                },
            ]),
            new TransactionContext(),
            true,
        ];

        yield 'Acceptable but too low float value returned from traces_sampler' => [
            new Options([
                'traces_sampler' => static function (): float {
                    return 0.0;
                },
            ]),
            new TransactionContext(),
            false,
        ];

        yield 'Acceptable integer value returned from traces_sampler' => [
            new Options([
                'traces_sampler' => static function (): int {
                    return 1;
                },
            ]),
            new TransactionContext(),
            true,
        ];

        yield 'Acceptable but too low integer value returned from traces_sampler' => [
            new Options([
                'traces_sampler' => static function (): int {
                    return 0;
                },
            ]),
            new TransactionContext(),
            false,
        ];

        yield 'Acceptable float value returned from traces_sample_rate' => [
            new Options([
                'traces_sample_rate' => 1.0,
            ]),
            new TransactionContext(),
            true,
        ];

        yield 'Acceptable but too low float value returned from traces_sample_rate' => [
            new Options([
                'traces_sample_rate' => 0.0,
            ]),
            new TransactionContext(),
            false,
        ];

        yield 'Acceptable integer value returned from traces_sample_rate' => [
            new Options([
                'traces_sample_rate' => 1,
            ]),
            new TransactionContext(),
            true,
        ];

        yield 'Acceptable but too low integer value returned from traces_sample_rate' => [
            new Options([
                'traces_sample_rate' => 0,
            ]),
            new TransactionContext(),
            false,
        ];

        yield 'Acceptable but too low value returned from traces_sample_rate which is preferred over sample_rate' => [
            new Options([
                'sample_rate' => 1.0,
                'traces_sample_rate' => 0.0,
            ]),
            new TransactionContext(),
            false,
        ];

        yield 'Acceptable value returned from traces_sample_rate which is preferred over sample_rate' => [
            new Options([
                'sample_rate' => 0.0,
                'traces_sample_rate' => 1.0,
            ]),
            new TransactionContext(),
            true,
        ];

        yield 'Acceptable value returned from SamplingContext::getParentSampled() which is preferred over traces_sample_rate (x1)' => [
            new Options([
                'traces_sample_rate' => 0.5,
            ]),
            new TransactionContext(TransactionContext::DEFAULT_NAME, true),
            true,
        ];

        yield 'Acceptable value returned from SamplingContext::getParentSampled() which is preferred over traces_sample_rate (x2)' => [
            new Options([
                'traces_sample_rate' => 1.0,
            ]),
            new TransactionContext(TransactionContext::DEFAULT_NAME, false),
            false,
        ];

        yield 'Invalid incoming sample_rand is ignored' => [
            new Options([
                'traces_sample_rate' => 1.0,
            ]),
            TransactionContext::fromHeaders(
                '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8',
                'sentry-sample_rand=2.0'
            ),
            true,
        ];

        yield 'Out of range sample rate returned from traces_sampler (lower than minimum)' => [
            new Options([
                'traces_sampler' => static function (): float {
                    return -1.0;
                },
            ]),
            new TransactionContext(TransactionContext::DEFAULT_NAME, false),
            false,
        ];

        yield 'Out of range sample rate returned from traces_sampler (greater than maximum)' => [
            new Options([
                'traces_sampler' => static function (): float {
                    return 1.1;
                },
            ]),
            new TransactionContext(TransactionContext::DEFAULT_NAME, false),
            false,
        ];

        yield 'Invalid type returned from traces_sampler' => [
            new Options([
                'traces_sampler' => static function (): string {
                    return 'foo';
                },
            ]),
            new TransactionContext(TransactionContext::DEFAULT_NAME, false),
            false,
        ];
    }

    public function testDoesNothingIfTracingIsNotEnabled(): void
    {
        $transaction = $this->sampleTransaction(new Options(), new TransactionContext());

        $this->assertFalse($transaction->getSampled());
    }

    public function testPassesCustomSamplingContextToTracesSampler(): void
    {
        $customSamplingContext = ['a' => 'b'];
        $samplerInvoked = false;

        $this->sampleTransaction(new Options([
            'traces_sampler' => function (SamplingContext $samplingContext) use ($customSamplingContext, &$samplerInvoked): float {
                $this->assertSame($customSamplingContext, $samplingContext->getAdditionalContext());
                $samplerInvoked = true;

                return 1.0;
            },
        ]), new TransactionContext(), $customSamplingContext);

        $this->assertTrue($samplerInvoked);
    }

    public function testStartsProfilerWithProfilesSampler(): void
    {
        $transaction = $this->sampleTransaction(new Options([
            'traces_sample_rate' => 1.0,
            'profiles_sampler' => static function (): float {
                return 1.0;
            },
        ]), new TransactionContext());

        $this->assertTrue($transaction->getSampled());
        $this->assertNotNull($transaction->getProfiler());
    }

    public function testDoesNotStartProfilerWhenProfilesSamplerReturnsZero(): void
    {
        $transaction = $this->sampleTransaction(new Options([
            'traces_sample_rate' => 1.0,
            'profiles_sampler' => static function (): float {
                return 0.0;
            },
        ]), new TransactionContext());

        $this->assertTrue($transaction->getSampled());
        $this->assertNull($transaction->getProfiler());
    }

    public function testPrefersProfilesSamplerOverProfilesSampleRate(): void
    {
        $transaction = $this->sampleTransaction(new Options([
            'traces_sample_rate' => 1.0,
            'profiles_sample_rate' => 1.0,
            'profiles_sampler' => static function (): float {
                return 0.0;
            },
        ]), new TransactionContext());

        $this->assertTrue($transaction->getSampled());
        $this->assertNull($transaction->getProfiler());
    }

    public function testPassesCustomSamplingContextToProfilesSampler(): void
    {
        $customSamplingContext = ['a' => 'b'];
        $samplerInvoked = false;

        $this->sampleTransaction(new Options([
            'traces_sample_rate' => 1.0,
            'profiles_sampler' => function (SamplingContext $samplingContext) use ($customSamplingContext, &$samplerInvoked): float {
                $this->assertSame($customSamplingContext, $samplingContext->getAdditionalContext());
                $samplerInvoked = true;

                return 0.0;
            },
        ]), new TransactionContext(), $customSamplingContext);

        $this->assertTrue($samplerInvoked);
    }

    public function testDoesNotStartProfilerWhenProfilesSamplerReturnsInvalidValue(): void
    {
        $transaction = $this->sampleTransaction(new Options([
            'traces_sample_rate' => 1.0,
            'profiles_sampler' => static function (): string {
                return 'foo';
            },
        ]), new TransactionContext());

        $this->assertTrue($transaction->getSampled());
        $this->assertNull($transaction->getProfiler());
    }

    public function testDoesNotCallProfilesSamplerWhenTransactionIsNotSampled(): void
    {
        $profilesSamplerInvoked = false;

        $transaction = $this->sampleTransaction(new Options([
            'traces_sample_rate' => 0.0,
            'profiles_sampler' => static function () use (&$profilesSamplerInvoked): float {
                $profilesSamplerInvoked = true;

                return 1.0;
            },
        ]), new TransactionContext());

        $this->assertFalse($transaction->getSampled());
        $this->assertFalse($profilesSamplerInvoked);
        $this->assertNull($transaction->getProfiler());
    }

    public function testUpdatesTheDscSampleRate(): void
    {
        $dsc = DynamicSamplingContext::fromHeader('sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public');
        $transactionMetaData = new TransactionMetadata(null, $dsc);
        $transactionContext = new TransactionContext(TransactionContext::DEFAULT_NAME, null, $transactionMetaData);

        $transaction = $this->sampleTransaction(new Options([
            'traces_sampler' => static function (SamplingContext $samplingContext): float {
                return 1.0;
            },
        ]), $transactionContext);

        $this->assertSame('1', $transaction->getMetadata()->getDynamicSamplingContext()->get('sample_rate'));
    }

    /**
     * @param array<string, mixed> $customSamplingContext
     */
    private function sampleTransaction(Options $options, TransactionContext $transactionContext, array $customSamplingContext = []): Transaction
    {
        return TransactionSampler::startTransaction($options, $transactionContext, $customSamplingContext);
    }
}
