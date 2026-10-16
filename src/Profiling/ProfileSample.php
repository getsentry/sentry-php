<?php

declare(strict_types=1);

namespace Sentry\Profiling;

/**
 * @internal
 */
final class ProfileSample
{
    /**
     * @var int The index of the captured stack in {@see ProfileData::getStacks()}
     */
    private $stackId;

    /**
     * @var float The number of seconds elapsed since the profiler was started
     */
    private $timestamp;

    /**
     * @var string The ID of the thread the sample was taken on
     */
    private $threadId;

    /**
     * @var string The name of the thread the sample was taken on
     */
    private $threadName;

    public function __construct(int $stackId, float $timestamp, string $threadId, string $threadName)
    {
        $this->stackId = $stackId;
        $this->timestamp = $timestamp;
        $this->threadId = $threadId;
        $this->threadName = $threadName;
    }

    public function getStackId(): int
    {
        return $this->stackId;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function getThreadId(): string
    {
        return $this->threadId;
    }

    public function getThreadName(): string
    {
        return $this->threadName;
    }
}
