<?php

declare(strict_types=1);

namespace Sentry\State;

use Psr\Log\LoggerInterface;

/**
 * Manages runtime-local SDK state across different execution models.
 *
 * Lifecycle model:
 * - The manager keeps a lazily initialized global context as fallback.
 * - startContext() creates an isolated runtime context for the current
 *   execution key when no context is active yet.
 * - endContext() flushes context resources and removes that context.
 *
 * @internal
 */
final class RuntimeContextManager
{
    private const PROCESS_EXECUTION_CONTEXT_KEY = 'process';

    /**
     * @var ScopeManager
     */
    private $baseScopeManager;

    /**
     * @var RuntimeContext|null
     */
    private $globalContext;

    /**
     * @var array<string, RuntimeContext>
     */
    private $activeContexts = [];

    /**
     * @var array<string, string>
     */
    private $executionContextToRuntimeContext = [];

    public function __construct(ScopeManager $baseScopeManager)
    {
        $this->baseScopeManager = $baseScopeManager;
        $this->globalContext = null;
    }

    public function getCurrentScopeManager(): ScopeManager
    {
        return $this->getCurrentContext()->getScopeManager();
    }

    public function getCurrentContext(): RuntimeContext
    {
        $executionContextKey = $this->getExecutionContextKey();

        if ($this->hasActiveContextForExecutionContextKey($executionContextKey)) {
            $runtimeContextId = $this->executionContextToRuntimeContext[$executionContextKey];

            return $this->activeContexts[$runtimeContextId];
        }

        return $this->getGlobalContext();
    }

    public function hasActiveContext(): bool
    {
        return $this->hasActiveContextForExecutionContextKey($this->getExecutionContextKey());
    }

    /**
     * Starts an isolated context for the current execution key.
     */
    public function startContext(): void
    {
        $executionContextKey = $this->getExecutionContextKey();

        if ($this->hasActiveContextForExecutionContextKey($executionContextKey)) {
            // Nested start calls for the same execution key should be a no-op.
            return;
        }

        $this->createContextForExecutionContextKey($executionContextKey);
    }

    /**
     * Ends and flushes the active context for the current execution key.
     *
     * When no context is active for the key this is a no-op.
     */
    public function endContext(?int $timeout = null): void
    {
        $executionContextKey = $this->getExecutionContextKey();

        if (!$this->hasActiveContextForExecutionContextKey($executionContextKey)) {
            return;
        }

        $runtimeContextId = $this->executionContextToRuntimeContext[$executionContextKey];
        unset($this->executionContextToRuntimeContext[$executionContextKey]);

        $this->removeContextById($runtimeContextId, $timeout);
    }

    private function createContextForExecutionContextKey(string $executionContextKey): void
    {
        $runtimeContextId = $this->generateRuntimeContextId();
        $runtimeContext = new RuntimeContext($runtimeContextId, $this->baseScopeManager->forkForRuntimeContext());

        $this->activeContexts[$runtimeContextId] = $runtimeContext;
        $this->executionContextToRuntimeContext[$executionContextKey] = $runtimeContextId;
    }

    private function removeContextById(string $runtimeContextId, ?int $timeout = null): void
    {
        if (!isset($this->activeContexts[$runtimeContextId])) {
            return;
        }

        $runtimeContext = $this->activeContexts[$runtimeContextId];
        unset($this->activeContexts[$runtimeContextId]);
        // Remove any key mappings that may still reference this context.
        $this->removeExecutionContextMappingsForRuntimeContext($runtimeContextId);

        $scopeManager = $runtimeContext->getScopeManager();
        $logger = $this->getLoggerFromScopeManager($scopeManager);

        $this->flushRuntimeContextResources($runtimeContext, $timeout, $logger);
    }

    private function flushRuntimeContextResources(RuntimeContext $runtimeContext, ?int $timeout, LoggerInterface $logger): void
    {
        $scopeManager = $runtimeContext->getScopeManager();
        $client = Scope::getClientFromScopes(
            $scopeManager->getGlobalScope(),
            $scopeManager->getIsolationScope(),
            $scopeManager->getCurrentScope()
        );

        // captureEvent can throw before transport send (for example from scope event processors
        // or before_send callbacks), so we isolate failures and continue flushing other resources.
        try {
            $runtimeContext->getLogsAggregator()->flush($scopeManager);
        } catch (\Throwable $exception) {
            $logger->error('Failed to flush logs while ending a runtime context.', [
                'exception' => $exception,
                'runtime_context_id' => $runtimeContext->getId(),
            ]);
        }

        // Keep metrics flush independent from logs flush so one bad callback does not block the rest.
        try {
            $runtimeContext->getMetricsAggregator()->flush($scopeManager);
        } catch (\Throwable $exception) {
            $logger->error('Failed to flush trace metrics while ending a runtime context.', [
                'exception' => $exception,
                'runtime_context_id' => $runtimeContext->getId(),
            ]);
        }

        // Custom transports may throw from close(); endContext must stay best-effort and non-fatal.
        try {
            $client->flush($timeout);
        } catch (\Throwable $exception) {
            $logger->error('Failed to flush the client transport while ending a runtime context.', [
                'exception' => $exception,
                'runtime_context_id' => $runtimeContext->getId(),
            ]);
        }
    }

    private function removeExecutionContextMappingsForRuntimeContext(string $runtimeContextId): void
    {
        foreach ($this->executionContextToRuntimeContext as $executionContextKey => $mappedRuntimeContextId) {
            if ($mappedRuntimeContextId === $runtimeContextId) {
                unset($this->executionContextToRuntimeContext[$executionContextKey]);
            }
        }
    }

    private function hasActiveContextForExecutionContextKey(string $executionContextKey): bool
    {
        if (!isset($this->executionContextToRuntimeContext[$executionContextKey])) {
            return false;
        }

        $runtimeContextId = $this->executionContextToRuntimeContext[$executionContextKey];

        if (!isset($this->activeContexts[$runtimeContextId])) {
            // Mapping points to a context that was already evicted/ended; drop the stale index entry.
            unset($this->executionContextToRuntimeContext[$executionContextKey]);

            return false;
        }

        return true;
    }

    private function getLoggerFromScopeManager(ScopeManager $scopeManager): LoggerInterface
    {
        $client = Scope::getClientFromScopes(
            $scopeManager->getGlobalScope(),
            $scopeManager->getIsolationScope(),
            $scopeManager->getCurrentScope()
        );

        return $client->getOptions()->getLoggerOrNullLogger();
    }

    private function generateRuntimeContextId(): string
    {
        return \sprintf('%s-%d', str_replace('.', '', uniqid('', true)), mt_rand());
    }

    private function getExecutionContextKey(): string
    {
        // All supported runtime modes currently use a process-local execution key.
        return self::PROCESS_EXECUTION_CONTEXT_KEY;
    }

    private function getGlobalContext(): RuntimeContext
    {
        if ($this->globalContext === null) {
            // Lazy fallback keeps baseline behavior when users do not opt into explicit context lifecycle.
            $this->globalContext = new RuntimeContext('global', $this->baseScopeManager);
        }

        return $this->globalContext;
    }
}
