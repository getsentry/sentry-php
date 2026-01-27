<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\NoOpClient;

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
     * @var Scope[] Stack of isolation scopes (request/execution context)
     */
    private $isolationScopeStack = [];

    /**
     * @var Scope[] Stack of current scopes (active span context)
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
     * @param callable $callback The callback to be executed
     *
     * @psalm-template T
     *
     * @psalm-param callable(Scope): T $callback
     *
     * @return mixed|void The callback's return value, upon successful execution
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
     * Forks the isolation scope (and current scope) and executes the callback within it.
     *
     * @param callable $callback The callback to be executed
     *
     * @psalm-template T
     *
     * @psalm-param callable(Scope): T $callback
     *
     * @return mixed|void The callback's return value, upon successful execution
     *
     * @psalm-return T
     */
    public function withIsolationScope(callable $callback)
    {
        $this->pushCurrentScope();
        $scope = $this->pushIsolationScope();

        try {
            return $callback($scope);
        } finally {
            $this->popCurrentScope();
            $this->popIsolationScope();
        }
    }

    public function resetScopes(): void
    {
        $this->isolationScopeStack = [];
        $this->currentScopeStack = [];
    }

    private function pushIsolationScope(): Scope
    {
        $scope = clone $this->getOrCreateIsolationScope();
        $scope->setType(ScopeType::isolation());
        $this->isolationScopeStack[] = $scope;

        return $scope;
    }

    private function popIsolationScope(): bool
    {
        if (\count($this->isolationScopeStack) <= 1) {
            return false;
        }

        return array_pop($this->isolationScopeStack) !== null;
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
        if (empty($this->isolationScopeStack)) {
            $this->isolationScopeStack[] = new Scope(null, ScopeType::isolation());
        }

        return $this->isolationScopeStack[\count($this->isolationScopeStack) - 1];
    }

    private function getOrCreateCurrentScope(): Scope
    {
        if (empty($this->currentScopeStack)) {
            $this->currentScopeStack[] = new Scope(null, ScopeType::current());
        }

        return $this->currentScopeStack[\count($this->currentScopeStack) - 1];
    }
}
