<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\EventId;
use Sentry\EventType;
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
        $transactionContext = new TransactionContext();
        $transactionContext->setTags(['ios_version' => '4.0']);
        $transactionContext->setSampled(true);
        $transactionContext->setStartTimestamp(1600640865);

        $hub = $this->createMock(HubInterface::class);

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
}
