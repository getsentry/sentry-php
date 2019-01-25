<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\ClientInterface;

/**
 * This interface defines a factory to instantiate an integration that implements {@see IntegrationInterface}
 * starting from its FQCN.
 */
interface IntegrationFactoryInterface
{
    /**
     * @param ClientInterface $client The client to be bound to the new integration
     * @param string          $fqcn   The full qualified class name of the desired integration
     *
     * @return IntegrationInterface
     *
     * @throws \InvalidArgumentException if the FQCN doesn't implement {@see IntegrationInterface}
     */
    public function create(ClientInterface $client, string $fqcn): IntegrationInterface;
}
