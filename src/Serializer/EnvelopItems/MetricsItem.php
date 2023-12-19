<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Event;
use Sentry\Serializer\Traits\StacktraceFrameSeralizerTrait;
use Sentry\Util\JSON;

/**
 * @internal
 */
class MetricsItem implements EnvelopeItemInterface
{
    use StacktraceFrameSeralizerTrait;

    public static function toEnvelopeItem(Event $event): string
    {
        $metrics = $event->getMetrics();
        if ($metrics === null) {
            return '';
        }

        $statsdPayload = [];
        $metricMetaPayload = [];

        foreach ($metrics as $metric) {
            $line = $metric->getKey();

            foreach ($metric->serialize() as $value) {
                $line .= ':' . $value;
            }

            $line .= '|' . $metric->getType() . '|' .
                '#' . $metric->getTags() . '|' .
                'T' . $metric->getTimestamp();

            $statsdPayload[] = $line;

            if ($metric->hasCodeLocation()) {
                $metricMetaPayload[$metric->getMri()][] = array_merge(
                    ['type' => 'location'],
                    self::serializeStacktraceFrame($metric->getCodeLocation())
                );
            }
        }

        $statsdPayload = implode("\n", $statsdPayload);

        $statsdHeader = [
            'type' => 'statsd',
            'length' => mb_strlen($statsdPayload),
        ];

        if (!empty($metricMetaPayload)) {
            $metricMetaPayload = JSON::encode([
                'timestamp' => time(),
                'mapping' => $metricMetaPayload,
            ]);

            $metricMetaHeader = [
                'type' => 'metric_meta',
                'length' => mb_strlen($metricMetaPayload),
            ];

            return sprintf(
                "%s\n%s\n%s\n%s",
                JSON::encode($statsdHeader),
                $statsdPayload,
                JSON::encode($metricMetaHeader),
                $metricMetaPayload
            );
        }

        return sprintf(
            "%s\n%s",
            JSON::encode($statsdHeader),
            $statsdPayload
        );
    }
}
