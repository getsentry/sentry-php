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

        /**
         * @var array<string, Attribute> $mandatoryAttributes
         */
        $mandatoryAttributes = [
            'sentry.release' => Attribute::tryFromValue($event->getRelease()),
            'sentry.environment' => Attribute::tryFromValue($event->getEnvironment()),
            'sentry.sdk.name' => Attribute::tryFromValue($event->getSdkIdentifier()),
            'sentry.sdk.version' => Attribute::tryFromValue($event->getSdkVersion()),
        ];

        if ($event->getOsContext() !== null) {
            $mandatoryAttributes['os.name'] = Attribute::tryFromValue($event->getOsContext()->getName());
        }

        return \sprintf(
            "%s\n%s",
            JSON::encode($header),
            JSON::encode([
                'items' => array_map(static function (Span $span) use ($mandatoryAttributes): array {
                    $attributes = array_map(['Sentry\Serializer\EnvelopItems\SpansItem', 'attributeToArray'], $span->attributes()->all());
                    foreach ($mandatoryAttributes as $key => $attribute) {
                        if ($attribute === null) {
                            continue;
                        }
                        if (!\array_key_exists($key, $attributes)) {
                            $attributes[$key] = self::attributeToArray($attribute);
                        }
                    }

                    if (!\array_key_exists('sentry.segment.name', $attributes)) {
                        $attributes['sentry.segment.name'] = [
                            'type' => 'string',
                            'value' => $span->getSegmentName(),
                        ];
                    }

                    // TODO: should we have a default status in case a span never gets one assigned
                    // explicitly?
                    $status = 'error';
                    if ($span->getStatus() !== null) {
                        $status = $span->getStatus()->isOk() ? 'ok' : 'error';
                    }

                    return [
                        'trace_id' => (string) $span->getTraceId(),
                        'parent_span_id' => (string) $span->getParentSpanId(),
                        'span_id' => (string) $span->getSpanId(),
                        'name' => $span->getName(),
                        'status' => $status,
                        'is_remote' => $span->isSegment() && $span->getParentSpanId() !== null,
                        'kind' => 'server',
                        'start_timestamp' => $span->getStartTimestamp(),
                        'end_timestamp' => $span->getEndTimestamp(),
                        'attributes' => $attributes,
                    ];
                }, $spans),
            ])
        );
    }

    /**
     * @param Attribute $attribute
     * @return array<string, bool|float|string|int>
     */
    private static function attributeToArray(Attribute $attribute): array
    {
        return [
            'type' => $attribute->getType(),
            'value' => $attribute->getValue(),
        ];
    }
}
