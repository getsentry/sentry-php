<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use Sentry\Event;
use Sentry\Options;
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
     * @var Options The client option
     */
    private $options;

    /**
     * @var array The list of installed vendors
     */
    private static $loadedModules = [];

    /**
     * Constructor.
     *
     * @param Options $options The Client options
     */
    public function __construct(Options $options)
    {
        if (!class_exists(Composer::class)) {
            throw new \LogicException('You need the "composer/composer" package in order to use this integration.');
        }

        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(function (Event $event) {
            $self = Hub::getCurrent()->getIntegration(\get_class($this));

            if ($self instanceof self) {
                self::applyToEvent($self, $event);
            }

            return $event;
        });
    }

    /**
     * @param ModulesIntegration $self  The instance of this integration
     * @param Event              $event The event that will be enriched with the modules
     */
    public static function applyToEvent(self $self, Event $event): void
    {
        $composerFilePath = $self->options->getProjectRoot() . \DIRECTORY_SEPARATOR . 'composer.json';

        if (file_exists($composerFilePath) && 0 == \count(self::$loadedModules)) {
            $composer = Factory::create(new NullIO(), $composerFilePath, true);
            $locker = $composer->getLocker();

            if ($locker->isLocked()) {
                foreach ($locker->getLockedRepository()->getPackages() as $package) {
                    self::$loadedModules[$package->getName()] = $package->getVersion();
                }
            }
        }

        $event->setModules(self::$loadedModules);
    }
}
