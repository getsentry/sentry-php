<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Attributes\Attribute;
use Sentry\Event;
use Sentry\Tracing\Spans\Span;
use Sentry\Util\JSON;

/**
 * @internal
 */
class SpansItem implements EnvelopeItemInterface
{
    public static function toEnvelopeItem(Event $event): string
    {
        $spans = $event->getSpans();

        $header = [
            'type' => (string) $event->getType(),
            'item_count' => \count($spans),
            'content_type' => 'application/vnd.sentry.items.span.v2+json',
        ];

        return \sprintf(
            "%s\n%s",
            JSON::encode($header),
            JSON::encode([
                'items' => array_map(static function (Span $span): array {
                    return [
                        'trace_id' => (string) $span->traceId,
                        'parent_span_id' => (string) $span->parentSpanId,
                        'span_id' => (string) $span->spanId,
                        'name' => $span->name,
                        'status' => $span->status,
                        'is_remote' => !$span->parentSpanId ? true : false,
                        'kind' => 'server',
                        'start_timestamp' => $span->startTimestamp,
                        'end_timestamp' => $span->endTimestamp,
                        'attributes' => array_map(static function (Attribute $attribute): array {
                            return [
                                'type' => $attribute->getType(),
                                'value' => $attribute->getValue(),
                            ];
                        }, $span->attributes()->all()),
                    ];
                }, $spans),
            ])
        );
    }
}
