<?php

namespace Sentry\Integration;

final class Handler
{
    private static $integrations = [];

    public static function setupIntegrations(array $integrations): array
    {
        $integrationIndex = [];
        foreach ($integrations as $integration) {
            $class = \get_class($integration);
            if (!$integration instanceof Integration) {
                throw new \InvalidArgumentException(sprintf('Integration must implement %s interface', Integration::class));
            }
            if (!\array_key_exists($class, self::$integrations)) {
                /* @var Integration $integration */
                self::$integrations[$class] = true;
                $integration->setupOnce();
            }
            $integrationIndex[$class] = $integration;
        }

        return $integrationIndex;
    }
}
