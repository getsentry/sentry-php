<?php

namespace Sentry\Integration;

interface IntegrationInterface
{
    /**
     * Initializes the IntegrationInterface once.
     */
    public function setupOnce(): void;
}
