<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\TransactionMetadata;
use Sentry\Tracing\TransactionSource;

final class TransactionMetadataTest extends TestCase
{
    /**
     * @dataProvider constructorDataProvider
     */
    public function testConstructor(array $constructorArgs, $expectedSamplingRate, ?DynamicSamplingContext $expectedDynamicSamplingContext, ?TransactionSource $expectedSource): void
    {
        $transactionMetadata = new TransactionMetadata(...$constructorArgs);

        $this->assertSame($expectedSamplingRate, $transactionMetadata->getSamplingRate());
        $this->assertSame($expectedDynamicSamplingContext, $transactionMetadata->getDynamicSamplingContext());
        $this->assertSame($expectedSource, $transactionMetadata->getSource());
    }

    public function constructorDataProvider(): \Generator
    {
        $dsc = DynamicSamplingContext::fromHeader('sentry-public_key=public,sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-sample_rate=1');
        $source = TransactionSource::custom();

        yield [
            [],
            null,
            null,
            $source,
        ];

        yield [
            [
                1,
            ],
            1,
            null,
            $source,
        ];

        yield [
            [
                null,
                $dsc,
            ],
            null,
            $dsc,
            $source,
        ];

        yield [
            [
                null,
                null,
                $source,
            ],
            null,
            null,
            $source,
        ];

        yield [
            [
                0.5,
                $dsc,
                $source,
            ],
            0.5,
            $dsc,
            TransactionSource::custom(),
        ];
    }

    public function testGettersAndSetters(): void
    {
        $dsc = DynamicSamplingContext::fromHeader('sentry-public_key=public,sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-sample_rate=1');
        $source = TransactionSource::task();

        $transactionMetadata = new TransactionMetadata();
        $transactionMetadata->setSamplingRate(0);
        $transactionMetadata->setDynamicSamplingContext($dsc);
        $transactionMetadata->setSource($source);

        $this->assertSame(0, $transactionMetadata->getSamplingRate());
        $this->assertSame($dsc, $transactionMetadata->getDynamicSamplingContext());
        $this->assertSame($source, $transactionMetadata->getSource());
    }
}
