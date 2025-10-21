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

        $mandatoryAttributes = [
            'sentry.release' => $event->getRelease(),
            'sentry.environment' => $event->getEnvironment(),
            'sentry.segment.name' => 'Segment Name',
            'os.name' => $event->getOsContext()->getName(),
            'browser.name' => $event->getTags()['browser.name'] ?? 'unknown',
            'thread.id' => 1,
            'thread.name' => 'MainThread',
            'sentry.sdk.name' => $event->getSdkIdentifier(),
            'sentry.sdk.version' => $event->getSdkPayload(),
        ];

        return \sprintf(
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

                    return [
                        'trace_id' => (string) $span->getTraceId(),
                        'parent_span_id' => (string) $span->getParentSpanId() ?: null,
                        'span_id' => (string) $span->getSpanId(),
                        'name' => $span->getName(),
                        'status' => $span->getStatus()->isOk() ? 'ok' : 'error',
                        'is_remote' => !$span->getParentSpanId() ? true : false,
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
    }
}
