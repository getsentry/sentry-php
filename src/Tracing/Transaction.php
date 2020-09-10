<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;

/**
 * This class stores all the information about a Transaction.
 */
final class Transaction extends Span
{
    /**
     * @var HubInterface The hub instance
     */
    private $hub;

    /**
     * @var string Name of the transaction
     */
    private $name;

    /**
     * Span constructor.
     *
     * @param TransactionContext $context The context to create the transaction with
     * @param HubInterface|null  $hub     Instance of a hub to flush the transaction
     *
     * @internal
     */
    public function __construct(TransactionContext $context, ?HubInterface $hub = null)
    {
        parent::__construct($context);

        $this->hub = $hub ?? SentrySdk::getCurrentHub();
        $this->name = $context->getName();
    }

    /**
     * Sets the name of this transaction.
     *
     * @param string $name The name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Attaches a {@see SpanRecorder} to the transaction itself.
     *
     * @param int $maxSpans The maximum number of spans that can be recorded
     */
    public function initSpanRecorder(int $maxSpans = 1000): void
    {
        if (null === $this->spanRecorder) {
            $this->spanRecorder = new SpanRecorder($maxSpans);
        }

        $this->spanRecorder->add($this);
    }

    /**
     * {@inheritdoc}
     */
    public function finish(?float $endTimestamp = null): ?EventId
    {
        if (null !== $this->endTimestamp) {
            // Transaction was already finished once and we don't want to re-flush it
            return null;
        }

        parent::finish($endTimestamp);

        if (true !== $this->sampled) {
            return null;
        }

        $finishedSpans = [];

        if (null !== $this->spanRecorder) {
            foreach ($this->spanRecorder->getSpans() as $span) {
                if ($span->getSpanId() !== $this->getSpanId() && null !== $span->getEndTimestamp()) {
                    $finishedSpans[] = $span;
                }
            }
        }

        $event = Event::createTransaction();
        $event->setSpans($finishedSpans);
        $event->setStartTimestamp($this->startTimestamp);
        $event->setTimestamp($this->endTimestamp);
        $event->setTags($this->tags);
        $event->setTransaction($this->name);
        $event->setContext('trace', $this->getTraceContext());

        return $this->hub->captureEvent($event);
    }
}
