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

    public function __construct(int $stackId, float $timestamp)
    {
        $this->stackId = $stackId;
        $this->timestamp = $timestamp;
    }

    public function getStackId(): int
    {
        return $this->stackId;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }
}
