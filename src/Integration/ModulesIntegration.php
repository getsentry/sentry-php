<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Composer\Composer;
use Jean85\PrettyVersions;
use PackageVersions\Versions;
use Sentry\Event;
use Sentry\State\Hub;
use Sentry\State\Scope;

/**
 * This integration logs with the event details all the versions of the packages
 * installed with Composer, if any.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ModulesIntegration implements IntegrationInterface
{
    /**
     * @var array The list of installed vendors
     */
    private static $loadedModules = [];

    public function __construct()
    {
        if (!class_exists(PrettyVersions::class)) {
            throw new \LogicException('You need the "jean85/pretty-package-versions" package in order to use this integration.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(function (Event $event) {
            $self = Hub::getCurrent()->getIntegration(self::class);

            if ($self instanceof self) {
                self::applyToEvent($self, $event);
            }

            return $event;
        });
    }

    /**
     * @param self  $self  The instance of this integration
     * @param Event $event The event that will be enriched with the modules
     */
    public static function applyToEvent(self $self, Event $event): void
    {
        if (empty(self::$loadedModules)) {
            foreach (Versions::VERSIONS as $package => $rawVersion) {
                self::$loadedModules[$package] = PrettyVersions::getVersion($package)->getPrettyVersion();
            }
        }

        $event->setModules(self::$loadedModules);
    }
}
