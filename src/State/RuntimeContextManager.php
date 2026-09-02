<?php

declare(strict_types=1);

namespace Sentry\State;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sentry\ErrorHandler;
use Sentry\Tracing\PropagationContext;

/**
 * Manages runtime-local SDK state across different execution models.
 *
 * The manager keeps a lazily initialized global context as fallback. Explicit
 * contexts use process-local storage by default, or the configured storage for
 * runtimes with overlapping logical executions.
 *
 * @internal
 */
final class RuntimeContextManager
{
    /**
     * @var HubInterface
     */
    private $baseHub;

    /**
     * @var RuntimeContext|null
     */
    private $globalContext;

    /**
     * @var RuntimeContext|null
     */
    private $runtimeContext;

    /**
     * @var RuntimeContextStorageInterface|null
     */
    private $runtimeContextStorage;

    public function __construct(HubInterface $baseHub, ?RuntimeContextStorageInterface $runtimeContextStorage = null)
    {
        $this->baseHub = $baseHub;
        $this->runtimeContextStorage = $runtimeContextStorage;
    }

    /**
     * Sets the current hub with context-aware behavior.
     *
     * If a runtime context is active for the current logical execution, the hub is
     * updated only for that active context. Otherwise, the baseline/global hub
     * template is updated.
     *
     * @return bool Whether the hub was set on an active runtime context
     */
    public function setCurrentHub(HubInterface $hub): bool
    {
        $runtimeContext = $this->getActiveContext();

        if ($runtimeContext !== null) {
            $runtimeContext->setHub($hub);

            return true;
        }

        $this->baseHub = $hub;

        if ($this->globalContext !== null) {
            $this->globalContext->setHub($hub);
        }

        return false;
    }

    public function getCurrentHub(): HubInterface
    {
        return $this->getCurrentContext()->getHub();
    }

    public function getCurrentContext(): RuntimeContext
    {
        return $this->getActiveContext() ?? $this->getGlobalContext();
    }

    /**
     * Starts an isolated context for the current logical execution.
     *
     * @return bool Whether a new context was started
     */
    public function startContext(): bool
    {
        if ($this->getActiveContext() !== null) {
            // Nested start calls for the same logical execution should be a no-op.
            return false;
        }

        ErrorHandler::resetFatalErrorHandlerState();

        $this->setActiveContext(new RuntimeContext($this->generateRuntimeContextId(), $this->createHubFromBaseHub()));

        return true;
    }

    /**
     * Ends and flushes the active context for the current logical execution.
     *
     * When no context is active this is a no-op.
     */
    public function endContext(?int $timeout = null): void
    {
        $runtimeContext = $this->removeActiveContext();

        if ($runtimeContext === null) {
            return;
        }

        $logger = $this->getLoggerFromHub($runtimeContext->getHub());

        $this->flushRuntimeContextResources($runtimeContext, $timeout, $logger);
    }

    /**
     * Discards the active context for the current logical execution without flushing it.
     */
    public function discardActiveContext(): void
    {
        $this->removeActiveContext();
    }

    private function flushRuntimeContextResources(RuntimeContext $runtimeContext, ?int $timeout, LoggerInterface $logger): void
    {
        $hub = $runtimeContext->getHub();

        // captureEvent can throw before transport send (for example from scope event processors
        // or before_send callbacks), so we isolate failures and continue flushing other resources.
        try {
            $runtimeContext->getLogsAggregator()->flush($hub);
        } catch (\Throwable $exception) {
            $logger->error('Failed to flush logs while ending a runtime context.', [
                'exception' => $exception,
                'runtime_context_id' => $runtimeContext->getId(),
            ]);
        }

        // Keep metrics flush independent from logs flush so one bad callback does not block the rest.
        try {
            $runtimeContext->getMetricsAggregator()->flush($hub);
        } catch (\Throwable $exception) {
            $logger->error('Failed to flush trace metrics while ending a runtime context.', [
                'exception' => $exception,
                'runtime_context_id' => $runtimeContext->getId(),
            ]);
        }

        $client = $hub->getClient();

        if ($client === null) {
            return;
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

    private function getActiveContext(): ?RuntimeContext
    {
        if ($this->runtimeContextStorage !== null) {
            return $this->runtimeContextStorage->get();
        }

        return $this->runtimeContext;
    }

    private function setActiveContext(RuntimeContext $runtimeContext): void
    {
        if ($this->runtimeContextStorage !== null) {
            $this->runtimeContextStorage->set($runtimeContext);

            return;
        }

        $this->runtimeContext = $runtimeContext;
    }

    private function removeActiveContext(): ?RuntimeContext
    {
        if ($this->runtimeContextStorage !== null) {
            return $this->runtimeContextStorage->remove();
        }

        $runtimeContext = $this->runtimeContext;
        $this->runtimeContext = null;

        return $runtimeContext;
    }

    private function createHubFromBaseHub(): HubInterface
    {
        if (!$this->baseHub instanceof Hub) {
            return new Hub($this->baseHub->getClient());
        }

        $clonedScope = null;

        $this->baseHub->configureScope(static function (Scope $scope) use (&$clonedScope): void {
            $clonedScope = clone $scope;
            // Do not inherit active traces into a new runtime context.
            $clonedScope->setSpan(null);
            $clonedScope->setPropagationContext(PropagationContext::fromDefaults());
        });

        return new Hub($this->baseHub->getClient(), $clonedScope ?? new Scope());
    }

    private function getLoggerFromHub(HubInterface $hub): LoggerInterface
    {
        $client = $hub->getClient();

        if ($client === null) {
            return new NullLogger();
        }

        return $client->getOptions()->getLoggerOrNullLogger();
    }

    private function generateRuntimeContextId(): string
    {
        return \sprintf('%s-%d', str_replace('.', '', uniqid('', true)), mt_rand());
    }

    private function getGlobalContext(): RuntimeContext
    {
        if ($this->globalContext === null) {
            // Lazy fallback keeps baseline behavior when users do not opt into explicit context lifecycle.
            $this->globalContext = new RuntimeContext('global', $this->baseHub);
        }

        return $this->globalContext;
    }
}
