<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\Logs\Logs;
use Sentry\Metrics\TraceMetrics;
use Sentry\State\GlobalScope;
use Sentry\State\IsolationScope;
use Sentry\State\RuntimeContext;
use Sentry\State\RuntimeContextManager;
use Sentry\State\RuntimeContextStorageInterface;

/**
 * This class is the main entry point for all the most common SDK features.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SentrySdk
{
    /**
     * @var GlobalScope|null The process-global scope
     */
    private static $globalScope;

    /**
     * @var RuntimeContextManager|null
     */
    private static $runtimeContextManager;

    /**
     * @var RuntimeContextStorageInterface|null
     */
    private static $runtimeContextStorage;

    /**
     * Constructor.
     */
    private function __construct()
    {
    }

    /**
     * Initializes the SDK by binding the client to the global scope and resetting
     * the current local runtime state.
     */
    public static function init(?ClientInterface $client = null): void
    {
        if (self::$runtimeContextManager !== null) {
            self::$runtimeContextManager->discardActiveContext();
        }

        if ($client !== null) {
            self::getGlobalScope()->setClient($client);
        }

        self::$runtimeContextManager = null;
    }

    /**
     * Registers storage for isolating runtime contexts across overlapping logical executions.
     *
     * The registration persists across SDK initialization. Changing it discards the active
     * context for the current logical execution without flushing it, while the global fallback
     * context is kept. Concurrent runtimes should register storage before logical executions
     * begin and must not replace it while other logical executions are active.
     */
    public static function setRuntimeContextStorage(?RuntimeContextStorageInterface $runtimeContextStorage): void
    {
        if (self::$runtimeContextManager !== null) {
            self::$runtimeContextManager->discardActiveContext();
            self::$runtimeContextManager->setRuntimeContextStorage($runtimeContextStorage);
        }

        self::$runtimeContextStorage = $runtimeContextStorage;
    }

    public static function getGlobalScope(): GlobalScope
    {
        if (self::$globalScope === null) {
            self::$globalScope = new GlobalScope();
        }

        return self::$globalScope;
    }

    public static function getIsolationScope(): IsolationScope
    {
        return self::getCurrentRuntimeContext()->getIsolationScope();
    }

    public static function getLastEventId(): ?EventId
    {
        return self::getCurrentRuntimeContext()->getLastEventId();
    }

    public static function getClient(?IsolationScope $isolationScope = null): ClientInterface
    {
        $client = ($isolationScope ?? self::getIsolationScope())->getClient();

        if (!$client instanceof NoOpClient) {
            return $client;
        }

        return self::getGlobalScope()->getClient();
    }

    /**
     * Starts an isolated context for the current logical execution.
     *
     * A provided isolation scope is used as-is, allowing runtimes to prepare the
     * isolation scope of the new context. When no isolation scope is provided, the
     * SDK creates an empty one.
     *
     * If a context is already active, this method is a no-op and the provided
     * isolation scope is ignored.
     *
     * @param IsolationScope|null $isolationScope The isolation scope to use for the new context
     */
    public static function startContext(?IsolationScope $isolationScope = null): void
    {
        self::getRuntimeContextManager()->startContext($isolationScope);
    }

    /**
     * Ends and flushes the active context for the current logical execution.
     *
     * When no context is active this is a no-op.
     *
     * @param int|null $timeout The maximum number of seconds to wait while flushing the client transport
     */
    public static function endContext(?int $timeout = null): void
    {
        self::getRuntimeContextManager()->endContext($timeout);
    }

    /**
     * Executes the given callback within an isolated context.
     *
     * If a context is already active for the current logical execution, this method
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
        $startedNewContext = $runtimeContextManager->startContext();

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
            self::$runtimeContextManager = new RuntimeContextManager(self::$runtimeContextStorage);
        }

        return self::$runtimeContextManager;
    }
}
