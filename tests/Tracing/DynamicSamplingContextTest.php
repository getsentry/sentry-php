<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Options;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Sentry\UserDataBag;

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
        ?string $expectedUserSegment,
        ?string $expectedTransaction
    ): void {
        $dsc = DynamicSamplingContext::fromHeader($header);

        $this->assertSame($expectedTraceId, $dsc->get('trace_id'));
        $this->assertSame($expectedPublicKey, $dsc->get('public_key'));
        $this->assertSame($expectedSampleRate, $dsc->get('sample_rate'));
        $this->assertSame($expectedRelease, $dsc->get('release'));
        $this->assertSame($expectedEnvironment, $dsc->get('environment'));
        $this->assertSame($expectedUserSegment, $dsc->get('user_segment'));
        $this->assertSame($expectedTransaction, $dsc->get('transaction'));
    }

    public function fromHeaderDataProvider()
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
            'sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1,sentry-release=1.0.0,sentry-environment=test,sentry-user_segment=my_segment,sentry-transaction=<unlabeled transaction>',
            'd49d9bf66f13450b81f65bc51cf49c03',
            'public',
            '1',
            '1.0.0',
            'test',
            'my_segment',
            '<unlabeled transaction>',
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

        $user = new UserDataBag();
        $user->setSegment('my_segment');

        $scope = new Scope();
        $scope->setUser($user);

        $hub = new Hub($client, $scope);

        $transactionContext = new TransactionContext();
        $transactionContext->setName('foo');

        $transaction = new Transaction($transactionContext, $hub);

        $dsc = DynamicSamplingContext::fromTransaction($transaction, $hub);

        $this->assertSame((string) $transaction->getTraceId(), $dsc->get('trace_id'));
        $this->assertSame((string) $transaction->getMetaData()->getSamplingRate(), $dsc->get('sample_rate'));
        $this->assertSame('foo', $dsc->get('transaction'));
        $this->assertSame('public', $dsc->get('public_key'));
        $this->assertSame('1.0.0', $dsc->get('release'));
        $this->assertSame('test', $dsc->get('environment'));
        $this->assertSame('my_segment', $dsc->get('user_segment'));
        $this->assertTrue($dsc->isFrozen());
    }

    /**
     * @dataProvider getEntriesDataProvider
     */
    public function testGetEntries(string $header, array $expectedDynamicSamplingContext): void
    {
        $dsc = DynamicSamplingContext::fromHeader($header);
        $this->assertSame($expectedDynamicSamplingContext, $dsc->getEntries());
    }

    public function getEntriesDataProvider()
    {
        yield [
            '',
            [],
        ];

        yield [
            'sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1',
            [
                'trace_id' => 'd49d9bf66f13450b81f65bc51cf49c03',
                'public_key' => 'public',
                'sample_rate' => '1',
            ],
        ];

        yield [
            'sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1,foo=bar;foo;bar;bar=baz',
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
    public function testToString(string $header, string $expectedString): void
    {
        $dsc = DynamicSamplingContext::fromHeader($header);
        $this->assertSame($expectedString, (string) $dsc);
    }

    public function toStringDataProvider()
    {
        yield [
            '',
            '',
        ];

        yield [
            'sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1',
            'sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1',
        ];

        yield [
            'sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1,foo=bar;foo;bar;bar=baz',
            'sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-public_key=public,sentry-sample_rate=1',
        ];

        yield [
            'foo=bar;foo;bar;bar=baz',
            '',
        ];
    }
}
