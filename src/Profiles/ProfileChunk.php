<?php

declare(strict_types=1);

namespace Sentry\Profiles;

use Sentry\Event;
use Sentry\Options;
use Sentry\Util\PrefixStripper;
use Sentry\Util\SentryUid;

/**
 * Type definition of the Sentry v2 profile format (continuous profiling).
 * All fields are none otpional.
 *
 * @see https://develop.sentry.dev/sdk/telemetry/profiles/sample-format-v2/
 *
 * @phpstan-type SentryProfileFrame array{
 *     abs_path: string,
 *     filename: string,
 *     function: string,
 *     module: string|null,
 *     lineno: int|null,
 * }
 * @phpstan-type SentryV2Profile array{
 *     profiler_id: string,
 *     chunk_id: string,
 *     platform: string,
 *     release: string,
 *     environment: string,
 *     version: string,
 *     profile: array{
 *        frames: array<int, SentryProfileFrame>,
 *        samples: array<int, array{
 *            thread_id: string,
 *            stack_id: int,
 *            timestamp: float,
 *        }>,
 *        stacks: array<int, array<int, int>>,
 *    },
 *     client_sdk: array{
 *         name: string,
 *         version: string,
 *     },
 * }
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
final class ProfileChunk
{
    use PrefixStripper;

    /**
     * @var string The thread ID
     */
    public const THREAD_ID = '0';

    /**
     * @var string The thread name
     */
    public const THREAD_NAME = 'main';

    /**
     * @var string The version of the profile format
     */
    private const VERSION = '2';

    /**
     * @var float|null The start time of the profile as a Unix timestamp with microseconds
     */
    private $startTimeStamp;

    /**
     * @var string|null The profiler ID
     */
    private $profilerId;

    /**
     * @var string|null The chunk ID (null = auto-generate)
     */
    private $chunkId;

    /**
     * @var array<int, \ExcimerLog> The data of the profile
     */
    private $excimerLogs;

    /**
     * @var Options|null
     */
    private $options;

    public function __construct(?Options $options = null)
    {
        $this->options = $options;
    }

    public function setStartTimeStamp(?float $startTimeStamp): void
    {
        $this->startTimeStamp = $startTimeStamp;
    }

    public function setProfilerId(?string $profilerId): void
    {
        $this->profilerId = $profilerId;
    }

    public function setChunkId(string $chunkId): void
    {
        $this->chunkId = $chunkId;
    }

    /**
     * @param array<int, \ExcimerLog> $excimerLogs
     */
    public function setExcimerLogs($excimerLogs): void
    {
        $this->excimerLogs = $excimerLogs;
    }

    /**
     * @return SentryV2Profile|null
     */
    public function getFormattedData(Event $event): ?array
    {
        $frames = [];
        $frameHashMap = [];

        $stacks = [];
        $stackHashMap = [];

        $registerStack = static function (array $stack) use (&$stacks, &$stackHashMap): int {
            $stackHash = md5(serialize($stack));

            if (\array_key_exists($stackHash, $stackHashMap) === false) {
                $stackHashMap[$stackHash] = \count($stacks);
                $stacks[] = $stack;
            }

            return $stackHashMap[$stackHash];
        };

        $samples = [];

        $loggedStacks = $this->prepareStacks();
        foreach ($loggedStacks as $stack) {
            $stackFrames = [];

            foreach ($stack['trace'] as $frame) {
                $absolutePath = $frame['file'];
                $lineno = $frame['line'];

                $frameKey = "{$absolutePath}:{$lineno}";

                $frameIndex = $frameHashMap[$frameKey] ?? null;

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

                    $frameHashMap[$frameKey] = $frameIndex = \count($frames);
                    $frames[] = [
                        'filename' => $file,
                        'abs_path' => $absolutePath,
                        'module' => $module,
                        'function' => $function,
                        'lineno' => $lineno,
                    ];
                }

                $stackFrames[] = $frameIndex;
            }

            $stackId = $registerStack($stackFrames);

            $samples[] = [
                'stack_id' => $stackId,
                'thread_id' => self::THREAD_ID,
                'timestamp' => $this->startTimeStamp + $stack['timestamp'],
            ];
        }

        return [
            'profiler_id' => $this->profilerId,
            'chunk_id' => $this->chunkId ?? SentryUid::generate(),
            'platform' => 'php',
            'release' => $event->getRelease() ?? '',
            'environment' => $event->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT,
            'version' => self::VERSION,
            'profile' => [
                'frames' => $frames,
                'samples' => $samples,
                'stacks' => $stacks,
                'thread_metadata' => (object) [
                    self::THREAD_ID => [
                        'name' => self::THREAD_NAME,
                    ],
                ],
            ],
            'client_sdk' => [
                'name' => $event->getSdkIdentifier(),
                'version' => $event->getSdkVersion(),
            ],
        ];
    }

    /**
     * This method is mainly used to be able to mock the ExcimerLog class in the tests.
     *
     * @return array<int, ExcimerLogStackEntry>
     */
    private function prepareStacks(): array
    {
        $stacks = [];

        foreach ($this->excimerLogs as $excimerLog) {
            foreach ($excimerLog as $stack) {
                if ($stack instanceof \ExcimerLogEntry) {
                    $stacks[] = [
                        'trace' => $stack->getTrace(),
                        'timestamp' => $stack->getTimestamp(),
                    ];
                } else {
                    /** @var ExcimerLogStackEntry $stack */
                    $stacks[] = $stack;
                }
            }
        }

        return $stacks;
    }
}
