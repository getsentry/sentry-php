<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Sentry\Options;
use Sentry\Util\PrefixStripper;

/**
 * Collects the stack samples recorded by Excimer into the deduplicated frames,
 * stacks and samples shared by all Sentry profile formats.
 *
 * @phpstan-type ExcimerLogStackEntryTrace array{
 *     file: string,
 *     line: int,
 *     class?: string,
 *     function?: string,
 *     closure_line?: int,
 * }
 * @phpstan-type ExcimerLogStackEntry array{
 *     trace: array<int, ExcimerLogStackEntryTrace>,
 *     timestamp: float
 * }
 *
 * @internal
 */
final class ProfileDataBuffer
{
    use PrefixStripper;

    /**
     * @var Options|null
     */
    private $options;

    /**
     * @var ProfileFrame[]
     */
    private $frames = [];

    /**
     * @var array<string, array<int, array<string, array<string, int>>>> 4D array keyed by file, line, class and function, as different functions can share a line (e.g. closures)
     */
    private $frameIndices = [];

    /**
     * @var array<int, array<int, int>>
     */
    private $stacks = [];

    /**
     * @var array<string, int> Keys are comma separated frame indices
     */
    private $stackIndices = [];

    /**
     * @var ProfileSample[]
     */
    private $samples = [];

    public function __construct(?Options $options = null)
    {
        $this->options = $options;
    }

    /**
     * Sets the options to strip the prefixes of the paths of frames that are collected from now on.
     */
    public function setOptions(Options $options): void
    {
        $this->options = $options;
    }

    /**
     * Adds a log entry taken on the given thread. The timestamp of the sample is the
     * number of seconds elapsed since the profiler was started, as reported by Excimer.
     *
     * @param \ExcimerLogEntry|ExcimerLogStackEntry $entry
     */
    public function add($entry, string $threadId, string $threadName): void
    {
        if ($entry instanceof \ExcimerLogEntry) {
            $trace = $entry->getTrace();
            $timestamp = $entry->getTimestamp();
        } else {
            $trace = $entry['trace'];
            $timestamp = $entry['timestamp'];
        }

        $stackFrames = [];

        foreach ($trace as $frame) {
            $absolutePath = $frame['file'];
            $lineno = $frame['line'];
            $className = $frame['class'] ?? '';
            $functionName = $frame['function'] ?? '';

            $frameIndex = $this->frameIndices[$absolutePath][$lineno][$className][$functionName] ?? null;

            if ($frameIndex === null) {
                $file = $this->stripPrefixFromFilePath($this->options, $absolutePath);
                $module = null;

                if (isset($frame['class'], $frame['function'])) {
                    // Class::method
                    $function = $frame['class'] . '::' . $frame['function'];
                    $module = $frame['class'];
                } elseif (isset($frame['function'])) {
                    // {closure}
                    $function = $frame['function'];
                } else {
                    // /index.php
                    $function = $file;
                }

                $frameIndex = \count($this->frames);
                $this->frameIndices[$absolutePath][$lineno][$className][$functionName] = $frameIndex;
                $this->frames[] = new ProfileFrame($absolutePath, $file, $function, $module, $lineno);
            }

            $stackFrames[] = $frameIndex;
        }

        $stackKey = implode(',', $stackFrames);
        $stackIndex = $this->stackIndices[$stackKey] ?? null;

        if ($stackIndex === null) {
            $stackIndex = \count($this->stacks);
            $this->stackIndices[$stackKey] = $stackIndex;
            $this->stacks[] = $stackFrames;
        }

        $this->samples[] = new ProfileSample($stackIndex, $timestamp, $threadId, $threadName);
    }

    public function getSampleCount(): int
    {
        return \count($this->samples);
    }

    /**
     * Gets the timestamp of the first collected sample, if any.
     */
    public function getStartTimestamp(): ?float
    {
        return isset($this->samples[0]) ? $this->samples[0]->getTimestamp() : null;
    }

    /**
     * Returns the collected samples and empties the buffer for the next ones.
     */
    public function takeProfileData(): ProfileData
    {
        $profileData = new ProfileData($this->frames, $this->stacks, $this->samples);

        $this->frames = [];
        $this->frameIndices = [];
        $this->stacks = [];
        $this->stackIndices = [];
        $this->samples = [];

        return $profileData;
    }
}
