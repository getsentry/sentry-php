<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Event;
use Sentry\EventType;
use Sentry\Util\JSON;

/**
 * @internal
 */
class ProfileChunkItem implements EnvelopeItemInterface
{
    public static function toEnvelopeItem(Event $event): ?string
    {
        $profileChunk = $event->getProfileChunk();
        if ($profileChunk === null) {
            return null;
        }

        $payload = $profileChunk->getFormattedData($event);
        if ($payload === null) {
            return null;
        }

        $header = [
            'type' => (string) EventType::profileChunk(),
            // Required for rate limiting and categorization, must match the platform of the payload
            'platform' => $payload['platform'],
            'content_type' => 'application/json',
        ];

        return \sprintf("%s\n%s", JSON::encode($header), JSON::encode($payload));
    }
}
