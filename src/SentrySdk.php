<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\Logs\Logs;
use Sentry\Metrics\TraceMetrics;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\State\RuntimeContext;
use Sentry\State\RuntimeContextManager;

/**
 * This class is the main entry point for all the most common SDK features.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SentrySdk
{
    /**
     * @var HubInterface|null The baseline hub
     */
    private static $currentHub;

    /**
     * @var RuntimeContextManager|null
     */
    private static $runtimeContextManager;

    /**
     * Constructor.
     */
    private function __construct()
    {
    }

    /**
     * Initializes the SDK by creating a new hub instance each time this method
     * gets called.
     */
    public static function init(): HubInterface
    {
        self::$currentHub = new Hub();
        self::$runtimeContextManager = new RuntimeContextManager(self::$currentHub);

        return self::getCurrentHub();
    }

    /**
     * Gets the current hub. If it's not initialized then creates a new instance
     * and sets it as current hub.
     */
    public static function getCurrentHub(): HubInterface
    {
        return self::getRuntimeContextManager()->getCurrentHub();
    }

    /**
     * Sets the current hub.
     *
     * If called while an explicit runtime context is active, the hub update is
     * scoped to that active context only. Otherwise, it updates the baseline
     * hub used by the global fallback context and future contexts.
     *
     * @param HubInterface $hub The hub to set
     */
    public static function setCurrentHub(HubInterface $hub): HubInterface
    {
        $wasSetOnActiveRuntimeContext = self::getRuntimeContextManager()->setCurrentHub($hub);

        if (!$wasSetOnActiveRuntimeContext) {
            self::$currentHub = $hub;
        }

        return $hub;
    }

    public static function startContext(): void
    {
        self::getRuntimeContextManager()->startContext();
    }

    public static function endContext(?int $timeout = null): void
    {
        self::getRuntimeContextManager()->endContext($timeout);
    }

    /**
     * Executes the given callback within an isolated context.
     *
     * If a context is already active for the current execution key, this method
     * reuses it and only executes the callback.
     *
     * @param callable $callback The callback to execute
     *
     * @psalm-template T
     * @psalm-param callable(): T $callback
     *
     * @return mixed
     * @psalm-return T
     */
    public static function withContext(callable $callback, ?int $timeout = null)
    {
        $runtimeContextManager = self::getRuntimeContextManager();
        $startedNewContext = !$runtimeContextManager->hasActiveContext();

        if ($startedNewContext) {
            $runtimeContextManager->startContext();
        }

        try {
            return $callback();
        } finally {
            if ($startedNewContext) {
                $runtimeContextManager->endContext($timeout);
            }
        }
    }

    /**
     * Gets the current runtime-local context.
     *
     * @internal
     */
    public static function getCurrentRuntimeContext(): RuntimeContext
    {
        return self::getRuntimeContextManager()->getCurrentContext();
    }

    /**
     * Flushes all buffered telemetry data.
     *
     * This is a convenience facade that forwards the flush operation to all
     * internally managed components.
     *
     * Calling this method is equivalent to invoking `flush()` on each component
     * individually. It does not change flushing behavior, improve performance,
     * or reduce the number of network requests.
     */
    public static function flush(): void
    {
        Logs::getInstance()->flush();
        TraceMetrics::getInstance()->flush();
    }

    private static function getRuntimeContextManager(): RuntimeContextManager
    {
        if (self::$currentHub === null) {
            self::$currentHub = new Hub();
        }

        if (self::$runtimeContextManager === null) {
            self::$runtimeContextManager = new RuntimeContextManager(self::$currentHub);
        }

        return self::$runtimeContextManager;
    }
}
