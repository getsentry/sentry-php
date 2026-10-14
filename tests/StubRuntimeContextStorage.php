<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\State\RuntimeContext;
use Sentry\State\RuntimeContextStorageInterface;

/**
 * Models independently selectable execution slots, contexts shared between executions and
 * abandoned execution cleanup.
 */
final class StubRuntimeContextStorage implements RuntimeContextStorageInterface
{
    /**
     * @var string
     */
    private $execution = 'default';

    /**
     * @var array<string, RuntimeContext>
     */
    private $contexts = [];

    public function get(): ?RuntimeContext
    {
        return $this->contexts[$this->execution] ?? null;
    }

    public function set(RuntimeContext $runtimeContext): void
    {
        $this->contexts[$this->execution] = $runtimeContext;
    }

    public function remove(): ?RuntimeContext
    {
        $runtimeContext = $this->get();
        unset($this->contexts[$this->execution]);

        // A shared context is retained until the last execution sharing it releases it
        if ($runtimeContext !== null && \in_array($runtimeContext, $this->contexts, true)) {
            return null;
        }

        return $runtimeContext;
    }

    public function switchTo(string $execution): void
    {
        $this->execution = $execution;
    }

    /**
     * Shares the context of the current execution with the given one, such as a child coroutine.
     */
    public function share(string $execution): void
    {
        $runtimeContext = $this->get();

        if ($runtimeContext !== null) {
            $this->contexts[$execution] = $runtimeContext;
        }
    }

    public function release(string $execution): void
    {
        unset($this->contexts[$execution]);
    }
}
