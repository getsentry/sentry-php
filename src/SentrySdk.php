<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\Logs\Logs;
use Sentry\Metrics\TraceMetrics;
use Sentry\State\HubAdapter;
use Sentry\State\HubInterface;
use Sentry\State\RuntimeContext;
use Sentry\State\RuntimeContextManager;
use Sentry\State\Scope;

/**
 * This class is the main entry point for all the most common SDK features.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SentrySdk
{
    /**
     * @var Scope|null The process-global scope
     */
    private static $globalScope;

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
     * Initializes the SDK by binding the client to the global scope and reset
     * the current local runtime state.
     */
    public static function init(?ClientInterface $client = null): HubInterface
    {
        if ($client !== null) {
            self::getGlobalScope()->setClient($client);
        }
        self::$runtimeContextManager = new RuntimeContextManager();

        return self::getCurrentHub();
    }

    public static function getCurrentHub(): HubInterface
    {
        return HubAdapter::getInstance();
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
        self::getGlobalScope()->setClient($hub->getClient());

        return $hub;
    }

    public static function getGlobalScope(): Scope
    {
        if (self::$globalScope === null) {
            self::$globalScope = new Scope();
        }

        return self::$globalScope;
    }

    public static function getIsolationScope(): Scope
    {
        return self::getCurrentRuntimeContext()->getIsolationScope();
    }

    public static function getClient(?Scope $isolationScope = null): ClientInterface
    {
        $client = ($isolationScope ?? self::getIsolationScope())->getClient();

        if (!$client instanceof NoOpClient) {
            return $client;
        }

        return self::getGlobalScope()->getClient();
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
     * @phpstan-template T
     *
     * @phpstan-param callable(): T $callback
     *
     * @return mixed
     *
     * @phpstan-return T
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

        self::getClient()->flush();
    }

    private static function getRuntimeContextManager(): RuntimeContextManager
    {
        if (self::$runtimeContextManager === null) {
            self::$runtimeContextManager = new RuntimeContextManager();
        }

        return self::$runtimeContextManager;
    }
}
