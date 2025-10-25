<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Attributes\Attribute;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Metrics\Types\AbstractType;
use Sentry\SentrySdk;
use Sentry\Util\JSON;

/**
 * @internal
 */
class MetricsItem implements EnvelopeItemInterface
{
    public static function toEnvelopeItem(Event $event): string
    {
        $metrics = $event->getMetrics();

        $header = [
            'type' => (string) EventType::metrics(),
            'item_count' => \count($metrics),
            'content_type' => 'application/vnd.sentry.items.trace-metric+json',
        ];

        return \sprintf(
            "%s\n%s",
            JSON::encode($header),
            JSON::encode([
                'items' => array_map(static function (AbstractType $metric): array {
                    return [
                        'timestamp' => $metric->getTimestamp(),
                        'trace_id' => (string) SentrySdk::getCurrentHub()->getSpan()->getTraceId(),
                        'name' => $metric->getName(),
                        'value' => $metric->getValue(),
                        // 'unit' => (string) $metric->getUnit(),
                        'type' => $metric->getType(),
                        'attributes' => array_map(static function (Attribute $attribute): array {
                            return [
                                'type' => $attribute->getType(),
                                'value' => $attribute->getValue(),
                            ];
                        }, $metric->getAttributes()->all()),
                    ];
                }, $metrics),
            ])
        );
    }
}
