<?php

declare(strict_types=1);

namespace Sentry\Integration;

/**
 * This class handles the state of already installed integrations.
 * It makes sure to call {@link IntegrationInterface::setupOnce} only once per integration.
 *
 * Class Handler
 */
final class Handler
{
    private static $integrations = [];

    public static function setupIntegrations(array $integrations): array
    {
        $integrationIndex = [];

        foreach ($integrations as $integration) {
            /* @var IntegrationInterface $integration */
            $class = \get_class($integration);

            if (!$integration instanceof IntegrationInterface) {
                throw new \InvalidArgumentException(sprintf('Expecting integration implementing %s interface, got %s', IntegrationInterface::class, $class));
            }

            if (!isset(self::$integrations[$class])) {
                self::$integrations[$class] = true;

                $integration->setupOnce();
            }

            $integrationIndex[$class] = $integration;
        }

        return $integrationIndex;
    }
}
