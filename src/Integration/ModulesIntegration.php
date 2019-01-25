<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Jean85\PrettyVersions;
use PackageVersions\Versions;
use Sentry\Event;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

/**
 * This integration logs with the event details all the versions of the packages
 * installed with Composer; the root project is included too.
 */
final class ModulesIntegration implements IntegrationInterface
{
    /**
     * @var array The list of installed vendors
     */
    private static $loadedModules = [];

    /**
     * {@inheritdoc}
     */
    public function bindToHub(HubInterface $hub): IntegrationInterface
    {
        Scope::addGlobalEventProcessor([$this, 'applyToEvent']);

        return $this;
    }

    /**
     * Applies the information gathered by this integration to the event.
     *
     * @param Event $event The event that will be enriched with the modules
     */
    public static function applyToEvent(Event $event): void
    {
        if (empty(self::$loadedModules)) {
            foreach (Versions::VERSIONS as $package => $rawVersion) {
                self::$loadedModules[$package] = PrettyVersions::getVersion($package)->getPrettyVersion();
            }
        }

        $event->setModules(self::$loadedModules);
    }
}
