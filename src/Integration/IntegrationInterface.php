<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\ClientInterface;

/**
 * This interface defines a contract that must be implemented by integrations,
 * bindings or hooks that integrate certain frameworks or environments with the SDK.
 */
interface IntegrationInterface
{
    /**
     * Creates and initializes the current integration by registering and binding it to the passed client.
     *
     * @param ClientInterface $client
     *
     * @return IntegrationInterface
     */
    public static function setup(ClientInterface $client): self;
}
