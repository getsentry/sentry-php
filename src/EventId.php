<?php

declare(strict_types=1);

namespace Sentry;

/**
 * This class represents an event ID.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class EventId
{
    /**
     * @var string The ID
     */
    private $value;

    /**
     * Class constructor.
     *
     * @param string $value The ID
     */
    public function __construct(string $value)
    {
        if (!preg_match('/^[a-f0-9]{32}$/i', $value)) {
            throw new \InvalidArgumentException('The $value argument must be a 32 characters long hexadecimal string.');
        }

        $this->value = $value;
    }

    /**
     * Generates a new event ID.
     */
    public static function generate(): self
    {
        return new self(str_replace('-', '', uuid_create(UUID_TYPE_RANDOM)));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
