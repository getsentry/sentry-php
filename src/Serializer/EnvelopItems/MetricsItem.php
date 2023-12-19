<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Event;
use Sentry\Metrics\MetricsUnit;
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
            // key - my.metric
            $line = $metric->getKey();

            if ($metric->getUnit() !== MetricsUnit::none()) {
                // unit - @second
                $line .= '@' . $metric->getunit();
            }

            foreach ($metric->serialize() as $value) {
                // value - 2:3:4...
                $line .= ':' . $value;
            }

            // type - |c|, |d|, ...
            $line .= '|' . $metric->getType() . '|';

            $tags = str_replace('=', ':', http_build_query(
                $metric->getTags(), '', ','
            ));

            // tags - #key:value,key:value...
            $line .= '#' . $tags . '|';
            // timestamp - T123456789
            $line .= 'T' . $metric->getTimestamp();

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
