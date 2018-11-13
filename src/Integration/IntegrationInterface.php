<?php

declare(strict_types=1);

namespace Sentry\Integration;

/**
 * This interface describes an Integration.
 *
 * Interface IntegrationInterface
 */
interface IntegrationInterface
{
    /**
     * Initializes the current integration once.
     * This function is also expected to be called once, {@link Handler} takes care of calling this.
     */
    public function setupOnce(): void;
}
