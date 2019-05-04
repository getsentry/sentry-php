<?php

declare(strict_types=1);

namespace Sentry\Serializer;

interface SerializableInterface
{
    /**
     * Return an array representation of the object for Sentry.
     *
     * @return array|null
     */
    public function toSentry(): ?array;
}
