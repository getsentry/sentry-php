<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Sentry\Options;
use Sentry\Util\PrefixStripper;

/**
 * Converts the stack samples recorded by Excimer into the deduplicated frames,
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
 *     timestamp: float,
 *     event_count?: int
 * }
 *
 * @internal
 */
final class ExcimerLogConverter
{
    use PrefixStripper;

    /**
     * Converts the given log entries. The timestamp of each sample is the number of seconds
     * elapsed since the profiler was started.
     *
     * @param iterable<\ExcimerLogEntry|ExcimerLogStackEntry> $entries
     */
    public static function convert(iterable $entries, ?Options $options = null): ProfileData
    {
        $frames = [];
        // 4D array keyed by file, line, class and function, as different functions can share a line (e.g. closures)
        $frameIndices = [];

        $stacks = [];
        // Keys are comma separated frame indices
        $stackIndices = [];

        $samples = [];
        $previousTimestamp = null;

        foreach ($entries as $entry) {
            if ($entry instanceof \ExcimerLogEntry) {
                $trace = $entry->getTrace();
                $timestamp = $entry->getTimestamp();
                $eventCount = $entry->getEventCount();
            } else {
                $trace = $entry['trace'];
                $timestamp = $entry['timestamp'];
                $eventCount = $entry['event_count'] ?? 1;
            }

            $stackFrames = [];

            foreach ($trace as $frame) {
                $absolutePath = $frame['file'];
                $lineno = $frame['line'];
                $className = $frame['class'] ?? '';
                $functionName = $frame['function'] ?? '';

                $frameIndex = $frameIndices[$absolutePath][$lineno][$className][$functionName] ?? null;

                if ($frameIndex === null) {
                    $file = self::stripPrefixFromFilePath($options, $absolutePath);
                    $module = null;

                    if (isset($frame['class'], $frame['function'])) {
                        // Class::method
                        $function = $frame['class'] . '::' . $frame['function'];
                        $module = $frame['class'];
                    } elseif (isset($frame['function'])) {
                        // {closure}, or {closure:/var/www/html/index.php:42} since PHP 8.4
                        $function = preg_replace_callback('/^\{closure:(.*?):(\d+)}$/', static function (array $matches) use ($options): string {
                            return '{closure:' . self::stripPrefixFromFilePath($options, $matches[1]) . ':' . $matches[2] . '}';
                        }, $frame['function']) ?? $frame['function'];
                    } else {
                        // /index.php
                        $function = $file;
                    }

                    $frameIndex = \count($frames);
                    $frameIndices[$absolutePath][$lineno][$className][$functionName] = $frameIndex;
                    $frames[] = new ProfileFrame($absolutePath, $file, $function, $module, $lineno);
                }

                $stackFrames[] = $frameIndex;
            }

            $stackKey = implode(',', $stackFrames);
            $stackIndex = $stackIndices[$stackKey] ?? null;

            if ($stackIndex === null) {
                $stackIndex = \count($stacks);
                $stackIndices[$stackKey] = $stackIndex;
                $stacks[] = $stackFrames;
            }

            // When blocking calls happen, Excimer will produce a sample with the timestamp when the blocking call
            // ended and with an event count to describe how many ticks were spent in the blocking call.
            // This can lead to invalid flame graphs, as Sentry shows each sample until the next one, which attributes
            // the waiting time to the previous call.
            // Here, we estimate when the blocking call actually started, leading to a correct flame graph.
            $sampleTimestamp = $timestamp;

            if ($eventCount > 1 && $previousTimestamp !== null) {
                $sampleTimestamp = $previousTimestamp + ($timestamp - $previousTimestamp) / $eventCount;
            }

            $samples[] = new ProfileSample($stackIndex, $sampleTimestamp);

            $previousTimestamp = $timestamp;
        }

        return new ProfileData($frames, $stacks, $samples);
    }
}
