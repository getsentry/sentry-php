<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Event;
use Sentry\EventType;
use Sentry\Util\JSON;

/**
 * @internal
 */
class LogsItem implements EnvelopeItemInterface
{
    public static function toEnvelopeItem(Event $event): string
    {
        $logs = $event->getLogs();

        $header = [
            'type' => (string) EventType::logs(),
            'item_count' => \count($logs),
            'content_type' => 'application/vnd.sentry.items.log+json',
        ];

        return \sprintf(
            "%s\n%s",
            JSON::encode($header),
            JSON::encode([
                'items' => $logs,
            ])
        );
    }
}
