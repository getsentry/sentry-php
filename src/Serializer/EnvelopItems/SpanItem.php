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

        if ($span->parentSpanId !== null) {
            $payload['parent_span_id'] = (string) $span->parentSpanId;
        }

        if ($span->description !== null) {
            $payload['description'] = $span->description;
        }

        if ($span->op !== null) {
            $payload['op'] = $span->op;
        }

        if ($span->status !== null) {
            $payload['status'] = $span->status;
        }

        if (!empty($span->data)) {
            $payload['data'] = $span->data;
        }

        // if (!empty($span->tags)) {
        //     $payload['tags'] = $span->tags;
        // }

        // if (!empty($span->context)) {
        //     $payload['context'] = $span->context;
        // }

        // if (!empty($span->metricsSummary)) {
        //     $payload['_metrics_summary'] = self::serializeMetricsSummary($span->metricsSummary);
        // }

        if ($event->getRelease() !== null) {
            $payload['release'] = $event->getRelease();
        }

        if ($event->getEnvironment() !== null) {
            $payload['environment'] = $event->getEnvironment();
        }

        // In general, mainly use data as it makes the SDK simpler
        // See https://github.com/getsentry/rfcs/blob/main/text/0116-sentry-semantic-conventions.md

        // TBD: description -> name (OTel does the same) ✅
        // TBD: status -> use only three, HTTP status as context ✅
        // TBD: transaction -> confusing -> data.sentry.segment.name (data.sentry.segment_name) ✅
        // TBD: trace-origin
        // TBD: profiling -> data.sentry.profiler_id
        // TBD: tags?? -> this could become sentry.tags... field at one point

        // TBD: exclusive_time can't be calculated for single spans
        // see https://sentry.my.sentry.io/organizations/sentry/issues/797887/events/?query=sdk%3A%22sentry.javascript.browser%2F7.109.0%22
        // we will remove the requirement for exclusive_time on Relay

        return sprintf("%s\n%s", JSON::encode($header), JSON::encode($payload));
    }

    /**
     * @param array<string, array<string, MetricsSummary>> $metricsSummary
     *
     * @return array<string, mixed>
     */
    protected static function serializeMetricsSummary(array $metricsSummary): array
    {
        $formattedSummary = [];

        foreach ($metricsSummary as $mri => $metrics) {
            foreach ($metrics as $metric) {
                $formattedSummary[$mri][] = $metric;
            }
        }

        return $formattedSummary;
    }
}
