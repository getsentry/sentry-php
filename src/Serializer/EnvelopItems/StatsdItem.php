<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Event;
use Sentry\Util\JSON;

/**
 * @internal
 */
class StatsdItem implements EnvelopeItemInterface
{
    public static function toEnvelopeItem(Event $event): string
    {
        $metrics = $event->getMetrics();
        if ($metrics === null) {
            return '';
        }

        $payload = [];

        foreach ($metrics as $metric) {
            $line = $metric->getKey();

            foreach ($metric->serialize() as $value) {
                $line .= ':' . $value;
            }

            $line .= '|' . $metric->getType() . '|' .
                '#' . $metric->getTags() . '|' .
                'T' . $metric->getTimestamp();

            $payload[] = $line;
        }

        $payload = implode("\n", $payload);

        $header = [
            'type' => (string) $event->getType(),
            'length' => mb_strlen($payload),
        ];

        return sprintf(
            "%s\n%s",
            JSON::encode($header),
            $payload
        );
    }
}
