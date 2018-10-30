<?php

namespace Sentry\Integration;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\Event;
use Sentry\Options;

/**
 * This middleware logs with the event details all the versions of the packages
 * installed with Composer, if any.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ModulesIntegration
{
    /**
     * @var Options The client option
     */
    private $options;

    /**
     * Constructor.
     *
     * @param Options $options The Raven client configuration
     */
    public function __construct(Options $options)
    {
        if (!class_exists(Composer::class)) {
            throw new \LogicException('You need the "composer/composer" package in order to use this middleware.');
        }

        $this->options = $options;
    }

    /**
     * Collects the needed data and sets it in the given event object.
     *
     * @param Event                       $event     The event being processed
     * @param \Exception|\Throwable|null  $exception The thrown exception, if available
     *
     * @return Event
     */
    public function __invoke(Event $event, $exception = null)
    {
        $composerFilePath = $this->options->getProjectRoot() . \DIRECTORY_SEPARATOR . 'composer.json';

        if (file_exists($composerFilePath)) {
            $composer = Factory::create(new NullIO(), $composerFilePath, true);
            $locker = $composer->getLocker();

            if ($locker->isLocked()) {
                $modules = [];

                foreach ($locker->getLockedRepository()->getPackages() as $package) {
                    $modules[$package->getName()] = $package->getVersion();
                }

                $event->setModules($modules);
            }
        }

        return $event;
    }
}
