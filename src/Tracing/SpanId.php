<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\EventId;

/**
 * This class represents an span ID.
 */
final class SpanId extends EventId
{
    /**
     * {@inheritDoc}
     */
    public function __construct(string $value)
    {
        if (!preg_match('/^[a-f0-9]{16}$/i', $value)) {
            throw new \InvalidArgumentException('The $value argument must be a 16 characters long hexadecimal string.');
        }

        $this->value = $value;
    }

    /**
     * {@inheritDoc}
     */
    public static function generate(): self
    {
        return new self(substr(str_replace('-', '', uuid_create(UUID_TYPE_RANDOM)), 0, 16));
    }
}
