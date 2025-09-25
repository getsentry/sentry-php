<?php

declare(strict_types=1);

namespace Sentry\Serializer\EnvelopItems;

use Sentry\Event;

/**
 * @internal
 */
interface EnvelopeItemInterface
{
    /**
     * @param Event $event
     * @return ?string|string[]
     */
    public static function toEnvelopeItem(Event $event);
}
