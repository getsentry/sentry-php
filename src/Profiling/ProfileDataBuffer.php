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
     * @var array<string, int>
     */
    private $frameHashMap = [];

    /**
     * @var array<int, array<int, int>>
     */
    private $stacks = [];

    /**
     * @var array<string, int>
     */
    private $stackHashMap = [];

    /**
     * @var ProfileSample[]
     */
    private $samples = [];

    public function __construct(?Options $options = null)
    {
        $this->options = $options;
    }

    /**
     * Adds a log entry. The timestamp of the sample is the number of seconds elapsed
     * since the profiler was started, as reported by Excimer.
     *
     * @param \ExcimerLogEntry|ExcimerLogStackEntry $entry
     */
    public function add($entry): void
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

            $frameKey = "{$absolutePath}:{$lineno}";

            $frameIndex = $this->frameHashMap[$frameKey] ?? null;

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

                $this->frameHashMap[$frameKey] = $frameIndex = \count($this->frames);
                $this->frames[] = new ProfileFrame($absolutePath, $file, $function, $module, $lineno);
            }

            $stackFrames[] = $frameIndex;
        }

        $stackHash = md5(serialize($stackFrames));

        if (\array_key_exists($stackHash, $this->stackHashMap) === false) {
            $this->stackHashMap[$stackHash] = \count($this->stacks);
            $this->stacks[] = $stackFrames;
        }

        $this->samples[] = new ProfileSample($this->stackHashMap[$stackHash], $timestamp);
    }

    /**
     * Returns the collected samples and empties the buffer for the next ones.
     */
    public function takeProfileData(): ProfileData
    {
        $profileData = new ProfileData($this->frames, $this->stacks, $this->samples);

        $this->frames = [];
        $this->frameHashMap = [];
        $this->stacks = [];
        $this->stackHashMap = [];
        $this->samples = [];

        return $profileData;
    }
}
