<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Swoole\Coroutine;

/**
 * Attributes each Swoole or OpenSwoole coroutine to its own thread, and everything that
 * runs outside of a coroutine to the main thread. OpenSwoole provides the Swoole namespace too.
 */
final class SwooleThreadProvider implements ThreadProviderInterface
{
    public function getThreadId(): string
    {
        $coroutineId = $this->getCoroutineId();

        return $coroutineId > 0 ? (string) $coroutineId : ProfileChunk::THREAD_ID;
    }

    public function getThreadName(): string
    {
        $coroutineId = $this->getCoroutineId();

        return $coroutineId > 0 ? 'coroutine ' . $coroutineId : ProfileChunk::THREAD_NAME;
    }

    public function isConcurrent(): bool
    {
        return true;
    }

    /**
     * Gets the ID of the running coroutine, or -1 outside a coroutine.
     */
    private function getCoroutineId(): int
    {
        /** @var int $coroutineId */
        $coroutineId = Coroutine::getCid();

        return $coroutineId;
    }
}
