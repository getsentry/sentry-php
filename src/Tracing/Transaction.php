<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\Event;
use Sentry\State\HubInterface;

/**
 * This class stores all the information about a Transaction.
 */
final class Transaction extends Span
{
    /**
     * @var HubInterface|null The hub instance
     */
    private $hub;

    /**
     * @var string Name of the transaction
     */
    private $name;

    /**
     * Span constructor.
     *
     * @param TransactionContext|null $context The context to create the transaction with
     * @param HubInterface|null       $hub     Instance of a hub to flush the transaction
     *
     * @internal
     */
    public function __construct(?TransactionContext $context = null, ?HubInterface $hub = null)
    {
        parent::__construct($context);
        $this->hub = $hub;

        $this->name = $context->name ?? '<unlabled transaction>';
    }

    /**
     * @param string $name Name of the transaction
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return array<string, mixed> Returns the trace context
     */
    public function getTraceContext(): array
    {
        $data = $this->toArray();
        unset($data['start_timestamp']);
        unset($data['timestamp']);

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function finish($endTimestamp = null): ?string
    {
        if (null !== $this->endTimestamp) {
            // Transaction was already finished once and we don't want to reflush it
            return null;
        }

        parent::finish($endTimestamp);

        if (true !== $this->sampled) {
            // At this point if `sampled !== true` we want to discard the transaction.
            return null;
        }

        $hub = $this->hub;
        if (null !== $hub) {
            return $hub->captureEvent($this->jsonSerialize());
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $event = new Event();
        $event->setType('transaction');
        $event->getTagsContext()->merge($this->tags->toArray());
        $event->setTransaction($this->name);
        $event->setStartTimestamp($this->startTimestamp);
        $event->setContext('trace', $this->getTraceContext());
        $spanRecorder = $this->spanRecorder;
        if (null != $spanRecorder) {
            $event->setSpans($spanRecorder->getSpans());
        }
        $data = $event->toArray();
        $data['timestamp'] = $this->endTimestamp;

        return $data;
    }
}
