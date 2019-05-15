<?php

declare(strict_types=1);

namespace Sentry\Serializer;

/**
 * This interface can be used to customize how an object is serialized in the
 * payload of an event.
 */
interface SerializableInterface
{
    /**
     * Return an array representation of the object for Sentry.
     *
     * @return array|null
     */
    public function toSentry(): ?array;
}
