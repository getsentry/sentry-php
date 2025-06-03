<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Event;
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

        return \sprintf(
            "%s\n%s",
            JSON::encode($header),
            JSON::encode([
                'items' => $spans,
            ])
        );
    }
}
