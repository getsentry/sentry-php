<?php

namespace Sentry\Integration;

interface Integration
{
    /**
     * Initializes the Integration once.
     */
    public function setupOnce(): void;
}
