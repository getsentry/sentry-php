<?php

declare(strict_types=1);

namespace Sentry;

/**
 * Factory for the {@see Event} class.
 */
interface EventFactoryInterface
{
    /**
     * Create an {@see Event} with a stacktrace attached to it.
     *
     * @param array<string, mixed>|Event $payload The data to be attached to the Event
     */
    public function createWithStacktrace($payload): Event;

    /**
     * Create an {@see Event} from a data payload.
     *
     * @param array<string, mixed>|Event $payload The data to be attached to the Event
     */
    public function create($payload): Event;
}
