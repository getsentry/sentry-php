<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\State\HubInterface;

/**
 * This interface defines a contract that must be implemented by integrations,
 * bindings or hooks that integrate certain frameworks or environments with the SDK.
 */
interface IntegrationInterface
{
    /**
     * Binds the integration to a {@see Hub}, through a new instance if necessary.
     *
     * @param HubInterface $hub The {@see Hub} to which the integration will be bound
     *
     * @return IntegrationInterface The instance of the integration that will be bound
     *                              ($this or a fresh one if necessary)
     */
    public function bindToHub(HubInterface $hub): self;
}
