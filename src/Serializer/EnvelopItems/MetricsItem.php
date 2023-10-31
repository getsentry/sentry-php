<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Event;
use Sentry\Util\JSON;

/**
 * @internal
 */
class MetricsItem implements EnvelopeItemInterface
{
    public static function toEnvelopeItem(Event $event): string
    {
        $header = [
            'type' => (string) $event->getType(),
            'content_type' => 'application/json',
        ];

        $payload = [];

        $metric = $event->getMetric();
        if ($event->getMetric() !== null) {
            $payload[] = $metric;
        }

        return sprintf("%s\n%s", JSON::encode($header), JSON::encode($payload));
    }
}
