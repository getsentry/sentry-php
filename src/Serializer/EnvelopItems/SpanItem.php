<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Event;
use Sentry\Serializer\Traits\BreadcrumbSeralizerTrait;
use Sentry\Tracing\Span;
use Sentry\Tracing\TransactionMetadata;
use Sentry\Util\JSON;

/**
 * @internal
 *
 * @phpstan-type MetricsSummary array{
 *     min: int|float,
 *     max: int|float,
 *     sum: int|float,
 *     count: int,
 *     tags: array<string>,
 * }
 */
class SpanItem implements EnvelopeItemInterface
{
    use BreadcrumbSeralizerTrait;

    public static function toEnvelopeItem(Event $event): string
    {
        $header = [
            'type' => (string) $event->getType(),
            'content_type' => 'application/json',
        ];

        $payload = [
            'platform' => 'php',
            'sdk' => [
                'name' => $event->getSdkIdentifier(),
                'version' => $event->getSdkVersion(),
            ],
        ];

        $span = $event->getSpan();

        $payload['start_timestamp'] = $span->startTimestamp;
        $payload['timestamp'] = $span->endTimestamp;
        $payload['exclusive_time'] = $span->exclusiveTime;

        $payload['trace_id'] = (string) $span->traceId;
        $payload['segment_id'] = (string) $span->segmentId;
        $payload['span_id'] = (string) $span->spanId;

        $payload['is_segment'] = $span->isSegment;

        if ($span->description !== null) {
            $payload['description'] = $span->description;
        }

        if ($span->op !== null) {
            $payload['op'] = $span->op;
        }

        if ($span->status !== null) {
            $payload['status'] = $span->status;
        }

        if ($span->data !== null) {
            $payload['data'] = $span->data;
        }

        if ($event->getRelease() !== null) {
            $payload['release'] = $event->getRelease();
        }

        if ($event->getEnvironment() !== null) {
            $payload['environment'] = $event->getEnvironment();
        }

        // TBD: status
        // TBD: transaction
        // TBD: trace-origin
        // TBD: profiling

        return sprintf("%s\n%s", JSON::encode($header), JSON::encode($payload));
    }
}
