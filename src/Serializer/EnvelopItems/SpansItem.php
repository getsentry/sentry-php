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
    public static function toEnvelopeItem(Event $event): ?string
    {
        /**
         * @var Span[] $spans
         */
        $spans = $event->getSpans();
        if (empty($spans)) {
            return null;
        }

        $header = [
            'type' => 'span',
            'item_count' => \count($spans),
            'content_type' => 'application/vnd.sentry.items.span.v2+json',
        ];

        $mandatoryAttributes = [
            'sentry.release' => $event->getRelease() ?? '1',
            'sentry.environment' => $event->getEnvironment(),
            'os.name' => $event->getOsContext() ? $event->getOsContext()->getName() : null,
            'sentry.sdk.name' => $event->getSdkIdentifier(),
            'sentry.sdk.version' => $event->getSdkVersion(),
        ];

        $result = \sprintf(
            "%s\n%s",
            JSON::encode($header),
            JSON::encode([
                'items' => array_map(static function (Span $span) use ($mandatoryAttributes): array {
                    $attributes = $span->attributes();
                    foreach ($mandatoryAttributes as $key => $value) {
                        if (!$attributes->exists($key)) {
                            $attributes->set($key, $value);
                        }
                    }
                    if (!$attributes->exists('segment.name')) {
                        $attributes->set('segment.name', $span->getSegmentName());
                    }

                    return [
                        'trace_id' => (string) $span->getTraceId(),
                        'parent_span_id' => ($span->getParentSpan() ? (string) $span->getParentSpan()->getSpanId() : null),
                        'span_id' => (string) $span->getSpanId(),
                        'name' => $span->getName(),
                        'status' => $span->getStatus()->isOk() ? 'ok' : 'error',
                        'is_remote' => $span->isSegment(),
                        'kind' => 'server',
                        'start_timestamp' => $span->getStartTimestamp(),
                        'end_timestamp' => $span->getEndTimestamp(),
                        'attributes' => array_map(static function (Attribute $attribute): array {
                            return [
                                'type' => $attribute->getType(),
                                'value' => $attribute->getValue(),
                            ];
                        }, $attributes->all()),
                    ];
                }, $spans),
            ])
        );

        return $result;
    }
}
