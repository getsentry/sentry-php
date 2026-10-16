<?php

declare(strict_types=1);

namespace Sentry\Profiling;

/**
 * Attributes everything to the main thread of the process.
 */
final class MainThreadProvider implements ThreadProviderInterface
{
    public function getThreadId(): string
    {
        return ProfileChunk::THREAD_ID;
    }

    public function getThreadName(): string
    {
        return ProfileChunk::THREAD_NAME;
    }

    public function isConcurrent(): bool
    {
        return false;
    }
}
