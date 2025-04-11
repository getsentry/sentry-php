<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Event;
use Sentry\Util\JSON;

/**
 * @internal
 */
class LogsItem implements EnvelopeItemInterface
{
    public static function toEnvelopeItem(Event $event): string
    {
        $header = [
            'type' => (string) $event->getType(),
            'content_type' => 'application/json',
        ];

        $payload = '';

        $logs = $event->getLogs();
        foreach ($logs as $log) {
            $payload .= \sprintf("%s\n%s", JSON::encode($header), JSON::encode($log));
        }

        return $payload;
    }
}
