<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Options;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\PropagationContext;
use Sentry\Tracing\TraceId;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionSource;

final class DynamicSamplingContextTest extends TestCase
{
    /**
     * @dataProvider fromHeaderDataProvider
     */
    public function testFromHeader(
        string $header,
        ?string $expectedTraceId,
        ?string $expectedPublicKey,
        ?string $expectedSampleRate,
        ?string $expectedRelease,
        ?string $expectedEnvironment,
        ?string $expectedTransaction,
        ?string $expectedSampleRand
    ): void {
        $samplingContext = DynamicSamplingContext::fromHeader($header);

        $this->assertSame($expectedTraceId, $samplingContext->get('trace_id'));
        $this->assertSame($expectedPublicKey, $samplingContext->get('public_key'));
        $this->assertSame($expectedSampleRate, $samplingContext->get('sample_rate'));
        $this->assertSame($expectedRelease, $samplingContext->get('release'));
        $this->assertSame($expectedEnvironment, $samplingContext->get('environment'));
        $this->assertSame($expectedTransaction, $samplingContext->get('transaction'));
        $this->assertSame($expectedSampleRand, $samplingContext->get('sample_rand'));
    }

    public static function fromHeaderDataProvider(): \Generator
    {
        yield [
            '',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        ];

        yield [
            'sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1',
            'd49d9bf66f13450b81f65bc51cf49c03',
            'public',
            '1',
            null,
            null,
            null,
            null,
        ];

        yield [
            'sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1,sentry-release=1.0.0,sentry-environment=test,sentry-user_segment=my_segment,sentry-transaction=<unlabeled transaction>,sentry-sample_rand=0.5',
            'd49d9bf66f13450b81f65bc51cf49c03',
            'public',
            '1',
            '1.0.0',
            'test',
            '<unlabeled transaction>',
            '0.5',
        ];
    }

    public function testFromTransaction(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'dsn' => 'http://public@example.com/sentry/1',
                'release' => '1.0.0',
                'environment' => 'test',
            ]));

        $hub = new Hub($client);

        $transactionContext = new TransactionContext();
        $transactionContext->setName('foo');

        $transaction = new Transaction($transactionContext, $hub);
        $transaction->getMetadata()->setSamplingRate(1.0);
        $transaction->getMetadata()->setSampleRand(0.5);

        $samplingContext = DynamicSamplingContext::fromTransaction($transaction, $hub);

        $this->assertSame((string) $transaction->getTraceId(), $samplingContext->get('trace_id'));
        $this->assertSame((string) $transaction->getMetaData()->getSamplingRate(), $samplingContext->get('sample_rate'));
        $this->assertSame('foo', $samplingContext->get('transaction'));
        $this->assertSame('public', $samplingContext->get('public_key'));
        $this->assertSame('1.0.0', $samplingContext->get('release'));
        $this->assertSame('test', $samplingContext->get('environment'));
        $this->assertSame('0.5', $samplingContext->get('sample_rand'));
        $this->assertTrue($samplingContext->isFrozen());
    }

    public function testFromTransactionSourceUrl(): void
    {
        $hub = new Hub();

        $transactionContext = new TransactionContext();
        $transactionContext->setName('/foo/bar/123');
        $transactionContext->setSource(TransactionSource::url());

        $transaction = new Transaction($transactionContext, $hub);

        $samplingContext = DynamicSamplingContext::fromTransaction($transaction, $hub);

        $this->assertNull($samplingContext->get('transaction'));
    }

    public function testFromOptions(): void
    {
        $options = new Options([
            'dsn' => 'http://public@example.com/sentry/1',
            'release' => '1.0.0',
            'environment' => 'test',
            'traces_sample_rate' => 0.5,
        ]);

        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('21160e9b836d479f81611368b2aa3d2c'));
        $propagationContext->setSampleRand(0.5);

        $scope = new Scope();
        $scope->setPropagationContext($propagationContext);

        $samplingContext = DynamicSamplingContext::fromOptions($options, $scope);

        $this->assertSame('21160e9b836d479f81611368b2aa3d2c', $samplingContext->get('trace_id'));
        $this->assertSame('0.5', $samplingContext->get('sample_rate'));
        $this->assertSame('public', $samplingContext->get('public_key'));
        $this->assertSame('1.0.0', $samplingContext->get('release'));
        $this->assertSame('test', $samplingContext->get('environment'));
        $this->assertSame('0.5', $samplingContext->get('sample_rand'));
        $this->assertTrue($samplingContext->isFrozen());
    }

    /**
     * @dataProvider getEntriesDataProvider
     */
    public function testGetEntries(DynamicSamplingContext $samplingContext, array $expectedDynamicSamplingContext): void
    {
        $this->assertSame($expectedDynamicSamplingContext, $samplingContext->getEntries());
    }

    public static function getEntriesDataProvider(): \Generator
    {
        yield [
            DynamicSamplingContext::fromHeader(''),
            [],
        ];

        yield [
            DynamicSamplingContext::fromHeader('sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1'),
            [
                'trace_id' => 'd49d9bf66f13450b81f65bc51cf49c03',
                'public_key' => 'public',
                'sample_rate' => '1',
            ],
        ];

        yield [
            DynamicSamplingContext::fromHeader('sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1,foo=bar;foo;bar;bar=baz'),
            [
                'trace_id' => 'd49d9bf66f13450b81f65bc51cf49c03',
                'public_key' => 'public',
                'sample_rate' => '1',
            ],
        ];
    }

    /**
     * @dataProvider toStringDataProvider
     */
    public function testToString(DynamicSamplingContext $samplingContext, string $expectedString): void
    {
        $this->assertSame($expectedString, (string) $samplingContext);
    }

    public static function toStringDataProvider(): \Generator
    {
        yield [
            DynamicSamplingContext::fromHeader(''),
            '',
        ];

        yield [
            DynamicSamplingContext::fromHeader('sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1'),
            'sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1',
        ];

        yield [
            DynamicSamplingContext::fromHeader('sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1,foo=bar;foo;bar;bar=baz'),
            'sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1',
        ];

        yield [
            DynamicSamplingContext::fromHeader('foo=bar;foo;bar;bar=baz'),
            '',
        ];
    }
}
