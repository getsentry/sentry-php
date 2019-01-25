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
     * The constructor of the integration accepts as the first argument the client
     * to which it will be bound. 
     * Passing other dependencies in further arguments is allowed. 
     *
     * @param ClientInterface $client
     *
     * @return IntegrationInterface
     */
    public function __construct(ClientInterface $client);
}
