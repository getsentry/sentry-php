<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\State\RuntimeContext;
use Sentry\State\RuntimeContextStorageInterface;

/**
 * Models independently selectable execution slots and abandoned execution cleanup.
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

        return $runtimeContext;
    }

    public function switchTo(string $execution): void
    {
        $this->execution = $execution;
    }

    public function release(string $execution): void
    {
        unset($this->contexts[$execution]);
    }
}
