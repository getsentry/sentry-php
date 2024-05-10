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

        $span = $event->getSpan();

        $payload = [
            'traceId' => (string) $span->traceId,
            'spanId' => (string) $span->spanId,
            'parentSpanId' => (string) $span->parentSpanId,
            // @TODO(michi) name is required
            'name' => $span->name ?? '<unlabeled span>',
            'startTimeUnixNano' => (int) floor($span->startTimeUnixNano * 1_000_000_000),
            'endTimeUnixNano' => (int) floor($span->endTimeUnixNano * 1_000_000_000),
            // @TODO(michi)) tbd
            'kind' => 0,
        ];

        foreach ($span->attributes as $attribute) {
            $payload['attributes'][] = [
                'key' => array_key_first($attribute),
                'value' => [
                    'stringValue' => (string) $attribute[array_key_first($attribute)],
                ],
            ];
        }

        if ($span->segmentSpan !== null) {
            $payload['attributes'][] = [
                'key' => 'sentry.segment.id',
                'value' => [
                    'stringValue' => (string) $span->segmentSpan->spanId,
                ],
            ];
            $payload['attributes'][] = [
                'key' => 'sentry.segment.name',
                'value' => [
                    'stringValue' => $span->segmentSpan->name,
                ],
            ];
            // @TODO(michi) name is required
            // $payload['attributes'][] = [
            //     'key' => 'sentry.segment.op',
            //     'value' => [
            //         'stringValue' => $span->segmentSpan->spanId,
            //     ],
            // ];
        } else {
            $payload['attributes'][] = [
                'key' => 'sentry.segment.id',
                'value' => [
                    'stringValue' => (string) $span->spanId,
                ],
            ];
            $payload['attributes'][] = [
                'key' => 'sentry.segment.name',
                'value' => [
                    'stringValue' => $span->name,
                ],
            ];
        }

        if ($event->getRelease() !== null) {
            $payload['attributes'][] = [
                'key' => 'sentry.release',
                'value' => [
                    'stringValue' => $event->getRelease(),
                ],
            ];
        }
        if ($event->getEnvironment() !== null) {
            $payload['attributes'][] = [
                'key' => 'sentry.environment',
                'value' => [
                    'stringValue' => $event->getEnvironment(),
                ],
            ];
        }

        $payload['attributes'][] = [
            'key' => 'sentry.platform',
            'value' => [
                'stringValue' => 'php',
            ],
        ];
        $payload['attributes'][] = [
            'key' => 'sentry.sdk.name',
            'value' => [
                'stringValue' => $event->getSdkIdentifier(),
            ],
        ];
        $payload['attributes'][] = [
            'key' => 'sentry.sdk.version',
            'value' => [
                'stringValue' => $event->getSdkVersion(),
            ],
        ];
        // @TODO(michi): add exclusive_time
        // $payload['attributes'][] = [
        //     'key' => 'sentry.exclusive_time_nano',
        //     'value' => [
        //         'integerValue' => $event->getEnvironment(),
        //     ],
        // ];

        // @TODO(michi): trace-origin
        // @TODO(michi): add sentry.profiler.id attribute
        // @TODO(michi): tags

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
