<?php

declare(strict_types=1);

namespace Sentry\Profiling;

/**
 * Provides the thread that profile samples and spans are attributed to.
 *
 * Runtimes that interleave several logical executions in one process, such as Swoole
 * coroutines, can attribute each of them to its own thread.
 */
interface ThreadProviderInterface
{
    /**
     * Gets the ID of the thread the current logical execution is attributed to.
     */
    public function getThreadId(): string;

    /**
     * Gets the name of the thread the current logical execution is attributed to.
     */
    public function getThreadName(): string;

    /**
     * Whether several logical executions interleave in the process, so that each sample has to be
     * attributed to the thread right after it was taken instead of in batches.
     */
    public function isConcurrent(): bool;
}
