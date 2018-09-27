<?php

namespace Raven\Middleware;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use Psr\Http\Message\ServerRequestInterface;
use Raven\Configuration;
use Raven\Event;

/**
 * This middleware logs with the event details all the versions of the packages
 * installed with Composer, if any.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ModulesMiddleware
{
    /**
     * @var Configuration The Raven client configuration
     */
    private $config;

    /**
     * Constructor.
     *
     * @param Configuration $config The Raven client configuration
     */
    public function __construct(Configuration $config)
    {
        if (!class_exists(Composer::class)) {
            throw new \LogicException('You need the "composer/composer" package in order to use this middleware.');
        }

        $this->config = $config;
    }

    /**
     * Collects the needed data and sets it in the given event object.
     *
     * @param Event                       $event     The event being processed
     * @param callable                    $next      The next middleware to call
     * @param ServerRequestInterface|null $request   The request, if available
     * @param \Exception|\Throwable|null  $exception The thrown exception, if available
     * @param array                       $payload   Additional data
     *
     * @return Event
     */
    public function __invoke(Event $event, callable $next, ServerRequestInterface $request = null, $exception = null, array $payload = [])
    {
        $composerFilePath = $this->config->getProjectRoot() . \DIRECTORY_SEPARATOR . 'composer.json';

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

        return $next($event, $request, $exception, $payload);
    }
}
