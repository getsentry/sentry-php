<?php

declare(strict_types=1);

namespace Sentry\Tracing\Spans;

use Sentry\Attributes\Attribute;
use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;

/**
 * @phpstan-import-type AttributeValue from Attribute
 *
 * @internal
 */
final class SpansAggregator
{
    /**
     * @var Span[]
     */
    private $spans = [];

    public function add(Span $span): void
    {
        $this->spans[(string) $span->getSpanId()] = $span;
    }

    public function get(?SpanId $spanId): ?Span
    {
        if ($spanId === null) {
            return null;
        }
        return $this->spans[(string) $spanId] ?? null;
    }

    public function flush(): ?EventId
    {
        if (empty($this->spans)) {
            return null;
        }

        $hub = SentrySdk::getCurrentHub();
        $event = Event::createSpans()->setSpans(array_values($this->spans));

        $this->spans = [];

        return $hub->captureEvent($event);
    }
}
