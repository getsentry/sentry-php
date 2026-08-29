<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\SentrySdk;

/**
 * Stores the SDK runtime context for the current logical execution.
 *
 * Concurrent runtimes should back this interface with execution-local storage.
 * They must start a context before replacing the current Hub so one execution
 * cannot modify the process-wide baseline used by other executions.
 *
 * Integrations must call {@see SentrySdk::endContext()} before execution-local
 * state is released so buffered telemetry is flushed. Storage must still release
 * the context if an execution terminates without reaching that boundary.
 *
 * If a child execution shares its parent's context, storage must retain that
 * context until every owner has released it. Otherwise, the child must use an
 * independent context.
 */
interface RuntimeContextStorageInterface
{
    /**
     * Gets the runtime context for the current logical execution.
     */
    public function get(): ?RuntimeContext;

    /**
     * Stores the runtime context for the current logical execution.
     */
    public function set(RuntimeContext $runtimeContext): void;

    /**
     * Removes and returns the runtime context for the current logical execution.
     *
     * This method must return null without side effects when no context is stored.
     */
    public function remove(): ?RuntimeContext;
}
