<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Event;
use Sentry\Metrics\MetricsUnit;
use Sentry\Metrics\Types\AbstractType;
use Sentry\Serializer\Traits\StacktraceFrameSeralizerTrait;
use Sentry\Util\JSON;

/**
 * @internal
 */
class MetricsItem implements EnvelopeItemInterface
{
    use StacktraceFrameSeralizerTrait;

    /**
     * @var string
     */
    private const KEY_PATTERN = '/[^\w\-.]+/i';

    /**
     * @var string
     */
    private const UNIT_PATTERN = '/[^\w]+/i';

    /**
     * @var string
     */
    private const TAG_KEY_PATTERN = '/[^\w\-.\/]+/i';

    public static function toEnvelopeItem(Event $event): string
    {
        $metrics = $event->getMetrics();
        if (empty($metrics)) {
            return '';
        }

        $statsdPayload = [];
        $metricMetaPayload = [];

        foreach ($metrics as $metric) {
            $statsdPayload[] = self::seralizeMetric($metric);

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

    public static function seralizeMetric(AbstractType $metric): string
    {
        /**
         * In case of us adding support for emitting metrics from other namespaces,
         * we have to alter the RateLimiter::class to properly handle these
         * namespaces.
         */

        // key - my.metric
        $line = preg_replace(self::KEY_PATTERN, '_', $metric->getKey());

        if ($metric->getUnit() !== MetricsUnit::none()) {
            // unit - @second
            $line .= '@' . preg_replace(self::UNIT_PATTERN, '', (string) $metric->getUnit());
        }

        foreach ($metric->serialize() as $value) {
            // value - 2:3:4...
            $line .= ':' . $value;
        }

        // type - |c|, |d|, ...
        $line .= '|' . $metric->getType() . '|';

        $tags = [];
        foreach ($metric->getTags() as $key => $value) {
            $tags[] = preg_replace(self::TAG_KEY_PATTERN, '', $key) .
                ':' . self::escapeTagValues($value);
        }

        if (!empty($tags)) {
            // tags - #key:value,key:value...
            $line .= '#' . implode(',', $tags) . '|';
        }

        // timestamp - T123456789
        $line .= 'T' . $metric->getTimestamp();

        return $line;
    }

    public static function escapeTagValues(string $tagValue): string
    {
        $result = '';

        for ($i = 0; $i < mb_strlen($tagValue); ++$i) {
            $character = mb_substr($tagValue, $i, 1);
            $result .= str_replace(
                [
                    "\n",
                    "\r",
                    "\t",
                    '\\',
                    '|',
                    ',',
                ],
                [
                    '\n',
                    '\r',
                    '\t',
                    '\\\\',
                    '\u{7c}',
                    '\u{2c}',
                ],
                $character
            );
        }

        return $result;
    }
}
