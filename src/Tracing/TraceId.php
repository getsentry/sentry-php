<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\EventId;

/**
 * This class represents an trace ID.
 */
final class TraceId extends EventId
{
    /**
     * Generates a new event ID.
     */
    public static function generate(): self
    {
        return new self(str_replace('-', '', uuid_create(UUID_TYPE_RANDOM)));
    }
}
