<?php

declare(strict_types=1);

namespace Sentry\Serializer;

interface Serializable
{
    /**
     * Return an array representation of the object for Sentry.
     *
     * @return array
     */
    public function toSentry(): array;
}
