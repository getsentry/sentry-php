<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventId;
use Sentry\EventType;
use Sentry\Options;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Symfony\Bridge\PhpUnit\ClockMock;

/**
 * @group time-sensitive
 */
final class TransactionTest extends TestCase
{
    public function testFinish(): void
    {
        ClockMock::withClockMock(1600640877);

        $expectedEventId = null;
        $transactionContext = TransactionContext::make()
            ->setTags(['ios_version' => '4.0'])
            ->setSampled(true)
            ->setStartTimestamp(1600640865);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options());

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('getClient')
            ->willReturn($client);

        $transaction = new Transaction($transactionContext, $hub);
        $transaction->initSpanRecorder();

        $span1 = $transaction->startChild(new SpanContext());
        $span2 = $transaction->startChild(new SpanContext());
        $span3 = $transaction->startChild(new SpanContext()); // This span isn't finished, so it should not be included in the event

        $hub->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(function (Event $eventArg) use ($transactionContext, $span1, $span2): bool {
                $this->assertSame(EventType::transaction(), $eventArg->getType());
                $this->assertSame($transactionContext->getName(), $eventArg->getTransaction());
                $this->assertSame($transactionContext->getStartTimestamp(), $eventArg->getStartTimestamp());
                $this->assertSame(ClockMock::microtime(true), $eventArg->getTimestamp());
                $this->assertSame($transactionContext->getTags(), $eventArg->getTags());
                $this->assertSame([$span1, $span2], $eventArg->getSpans());

                return true;
            }))
            ->willReturnCallback(static function (Event $eventArg) use (&$expectedEventId): EventId {
                $expectedEventId = $eventArg->getId();

                return $expectedEventId;
            });

        $span1->finish();
        $span2->finish();

        $eventId = $transaction->finish();

        $this->assertSame($expectedEventId, $eventId);
    }

    public function testFinishDoesNothingIfSampledFlagIsNotTrue(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())
            ->method('captureEvent');

        $transaction = new Transaction(new TransactionContext(), $hub);
        $transaction->finish();
    }

    public function testFluentApi(): void
    {
        $transaction = new Transaction(TransactionContext::make());
        $tags = ['foo' => 'bar'];
        $name = 'baz';
        $transaction->setTags($tags)
          ->setName($name)
          ->finish();
        $this->assertSame($tags, $transaction->getTags());
        $this->assertSame($name, $transaction->getName());
    }

    /**
     * @dataProvider parentTransactionContextDataProvider
     */
    public function testTransactionIsSampledCorrectlyWhenTracingIsSetToZeroInOptions(TransactionContext $context, bool $expectedSampled): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(
                new Options([
                    'traces_sampler' => null,
                    'traces_sample_rate' => 0,
                ])
            );

        $transaction = (new Hub($client))->startTransaction($context);

        $this->assertSame($expectedSampled, $transaction->getSampled());
    }

    public static function parentTransactionContextDataProvider(): \Generator
    {
        yield [
            new TransactionContext(TransactionContext::DEFAULT_NAME, true),
            true,
        ];

        yield [
            new TransactionContext(TransactionContext::DEFAULT_NAME, false),
            false,
        ];

        yield [
            TransactionContext::fromHeaders('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1', ''),
            true,
        ];

        yield [
            TransactionContext::fromHeaders('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0', ''),
            false,
        ];
    }

    /**
     * @dataProvider parentTransactionContextDataProviderDisabled
     */
    public function testTransactionIsNotSampledWhenTracingIsDisabledInOptions(TransactionContext $context, bool $expectedSampled): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(
                new Options([
                    'traces_sampler' => null,
                    'traces_sample_rate' => null,
                ])
            );

        $transaction = (new Hub($client))->startTransaction($context);

        $this->assertSame($expectedSampled, $transaction->getSampled());
    }

    public function parentTransactionContextDataProviderDisabled(): \Generator
    {
        yield [
            new TransactionContext(TransactionContext::DEFAULT_NAME, true),
            false,
        ];

        yield [
            new TransactionContext(TransactionContext::DEFAULT_NAME, false),
            false,
        ];

        yield [
            TransactionContext::fromHeaders('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1', ''),
            false,
        ];

        yield [
            TransactionContext::fromHeaders('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0', ''),
            false,
        ];
    }
}
