<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\NoOpClient;
use Sentry\Tracing\PropagationContext;

/**
 * Manages global, isolation, and current scopes.
 */
final class ScopeManager
{
    /**
     * @var Scope
     */
    private $globalScope;

    /**
     * @var Scope|null The current isolation scope
     */
    private $isolationScope;

    /**
     * @var Scope[] Stack of current scopes
     */
    private $currentScopeStack = [];

    public function __construct(?Scope $globalScope = null)
    {
        if ($globalScope === null) {
            $globalScope = new Scope(null, ScopeType::global());
            $globalScope->setClient(new NoOpClient());
        }

        $globalScope->setType(ScopeType::global());
        $this->globalScope = $globalScope;
    }

    public function getGlobalScope(): Scope
    {
        return $this->globalScope;
    }

    public function getIsolationScope(): Scope
    {
        return $this->getOrCreateIsolationScope();
    }

    public function getCurrentScope(): Scope
    {
        return $this->getOrCreateCurrentScope();
    }

    /**
     * Forks the current scope and executes the given callback within it.
     *
     * @psalm-template T
     *
     * @psalm-param callable(Scope): T $callback
     *
     * @return mixed|void
     *
     * @psalm-return T
     */
    public function withScope(callable $callback)
    {
        $scope = $this->pushCurrentScope();

        try {
            return $callback($scope);
        } finally {
            $this->popCurrentScope();
        }
    }

    /**
     * Forks the isolation and current scope and executes the callback within it.
     *
     * @psalm-template T
     *
     * @psalm-param callable(Scope): T $callback
     *
     * @return mixed|void
     *
     * @psalm-return T
     */
    public function withIsolationScope(callable $callback)
    {
        $this->pushCurrentScope();
        $previousIsolationScope = $this->getOrCreateIsolationScope();
        $scope = clone $previousIsolationScope;
        $this->isolationScope = $scope;

        try {
            return $callback($scope);
        } finally {
            $this->popCurrentScope();
            $this->isolationScope = $previousIsolationScope;
        }
    }

    public function resetScopes(): void
    {
        $this->isolationScope = null;
        $this->currentScopeStack = [];
    }

    /**
     * Creates a copy suitable for a fresh runtime context.
     *
     * The global scope is preserved while the isolation scope is inherited as a
     * new baseline for the runtime context. Current scope state is intentionally
     * not carried over.
     */
    public function forkForRuntimeContext(): self
    {
        $globalScope = clone $this->globalScope;
        $globalScope->setType(ScopeType::global());
        $globalScope->setSpan(null);

        $scopeManager = new self($globalScope);

        if ($this->isolationScope !== null) {
            $scopeManager->isolationScope = clone $this->isolationScope;
            $scopeManager->isolationScope->setType(ScopeType::isolation());
            $scopeManager->isolationScope->setSpan(null);
            $scopeManager->isolationScope->setPropagationContext(PropagationContext::fromDefaults());
        }

        return $scopeManager;
    }

    private function pushCurrentScope(): Scope
    {
        $scope = clone $this->getOrCreateCurrentScope();
        $scope->setType(ScopeType::current());
        $this->currentScopeStack[] = $scope;

        return $scope;
    }

    private function popCurrentScope(): bool
    {
        if (\count($this->currentScopeStack) <= 1) {
            return false;
        }

        return array_pop($this->currentScopeStack) !== null;
    }

    private function getOrCreateIsolationScope(): Scope
    {
        if ($this->isolationScope === null) {
            $this->isolationScope = new Scope(null, ScopeType::isolation());
        }

        return $this->isolationScope;
    }

    private function getOrCreateCurrentScope(): Scope
    {
        if (empty($this->currentScopeStack)) {
            $this->currentScopeStack[] = new Scope(null, ScopeType::current());
        }

        return $this->currentScopeStack[\count($this->currentScopeStack) - 1];
    }
}
