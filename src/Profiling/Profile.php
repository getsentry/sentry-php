<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Sentry\Context\OsContext;
use Sentry\Context\RuntimeContext;
use Sentry\Event;
use Sentry\EventId;
use Sentry\Util\SentryUid;

/**
 * Type definition of the Sentry profile format.
 * All fields are none otpional.
 *
 * @see https://develop.sentry.dev/sdk/sample-format/
 *
 * @phpstan-type SentryProfile array{
 *    device: array{
 *        architecture: string,
 *    },
 *    event_id: string,
 *    os: array{
 *       name: string,
 *       version: string,
 *       build_number: string,
 *    },
 *    platform: string,
 *    release: string,
 *    environment: string,
 *    runtime: array{
 *        name: string,
 *        version: string,
 *    },
 *    timestamp: string,
 *    transaction: array{
 *        id: string,
 *        name: string,
 *        trace_id: string,
 *        active_thread_id: string,
 *    },
 *    version: string,
 *    profile: array{
 *        frames: array<int, array{
 *            function: string,
 *            filename: string,
 *            lineno: int|null,
 *        }>,
 *        samples: array<int, array{
 *            elapsed_since_start_ns: int,
 *            stack_id: int,
 *            thread_id: string,
 *        }>,
 *        stacks: array<int, array<int, int>>,
 *    },
 * }
 *
 * @phpstan-type ExcimerLogStackEntryTrace array{
 *     file: string|null,
 *     line: string|int|null,
 *     class: string,
 *     function: string,
 *     closure_line: int
 * }
 *
 * @phpstan-type ExcimerLogStackEntry array{
 *     trace: array<int, ExcimerLogStackEntryTrace>,
 *     timestamp: float
 * }
 *
 * @internal
 */
final class Profile
{
    /**
     * @var string The version of the profile format
     */
    private const VERSION = '1';

    /**
     * @var string The thread ID
     */
    private const THREAD_ID = '0';

    /**
     * @var int The minimum number of samples required for a profile
     */
    private const MIN_SAMPLE_COUNT = 2;

    /**
     * @var int The maximum duration of a profile in seconds
     */
    private const MAX_PROFILE_DURATION = 30;

    /**
     * @var float The start time of the profile as a Unix timestamp with microseconds
     */
    private $startTimeStamp;

    /**
     * @var \ExcimerLog|array<int, ExcimerLogStackEntry> The data of the profile
     */
    private $excimerLog;

    /**
     * @var EventId|null The event ID of the profile
     */
    private $eventId;

    public function setStartTimeStamp(float $startTimeStamp): void
    {
        $this->startTimeStamp = $startTimeStamp;
    }

    /**
     * @param \ExcimerLog|array<int, ExcimerLogStackEntry> $excimerLog
     */
    public function setExcimerLog($excimerLog): void
    {
        $this->excimerLog = $excimerLog;
    }

    public function setEventId(EventId $eventId): void
    {
        $this->eventId = $eventId;
    }

    /**
     * @return SentryProfile|null
     */
    public function getFormattedData(Event $event): ?array
    {
        if (!$this->validateExcimerLog()) {
            return null;
        }

        $osContext = $event->getOsContext();
        if (!$this->validateOsContext($osContext)) {
            return null;
        }

        $runtimeContext = $event->getRuntimeContext();
        if (!$this->validateRuntimeContext($runtimeContext)) {
            return null;
        }

        if (!$this->validateEvent($event)) {
            return null;
        }

        $frames = [];
        $samples = [];
        $stacks = [];

        $frameIndex = 0;
        $duration = 0;

        $loggedStacks = $this->prepareStacks();
        foreach ($loggedStacks as $stackId => $stack) {
            foreach ($stack['trace'] as $frame) {
                $absolutePath = (string) $frame['file'];
                // TODO(michi) Strip the file path based on the `prefixes` option
                $file = $absolutePath;
                $module = null;

                if (isset($frame['class'], $frame['function'])) {
                    // Class::method
                    $function = $frame['class'] . '::' . $frame['function'];
                    $module = $frame['class'];
                } elseif (isset($frame['function'])) {
                    // {clousre}
                    $function = $frame['function'];
                } else {
                    // /index.php
                    $function = $file;
                }

                $frames[] = [
                    'filename' => $file,
                    'abs_path' => $absolutePath,
                    'module' => $module,
                    'function' => $function,
                    'lineno' => !empty($frame['line']) ? (int) $frame['line'] : null,
                ];

                $stacks[$stackId][] = $frameIndex;
                ++$frameIndex;
            }

            $duration = $stack['timestamp'];

            $samples[] = [
                'elapsed_since_start_ns' => (int) round($duration * 1e+9),
                'stack_id' => $stackId,
                'thread_id' => self::THREAD_ID,
            ];
        }

        if (!$this->validateMaxDuration((float) $duration)) {
            return null;
        }

        $startTime = \DateTime::createFromFormat('U.u', number_format($this->startTimeStamp, 4, '.', ''), new \DateTimeZone('UTC'));
        if (false === $startTime) {
            return null;
        }

        return [
            'device' => [
                'architecture' => $osContext->getMachineType(),
            ],
            'event_id' => $this->eventId ? (string) $this->eventId : SentryUid::generate(),
            'os' => [
                'name' => $osContext->getName(),
                'version' => $osContext->getVersion(),
                'build_number' => $osContext->getBuild() ?? '',
            ],
            'platform' => 'php',
            'release' => $event->getRelease() ?? '',
            'environment' => $event->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT,
            'runtime' => [
                'name' => $runtimeContext->getName(),
                'version' => $runtimeContext->getVersion(),
            ],
            'timestamp' => $startTime->format(\DATE_RFC3339_EXTENDED),
            'transaction' => [
                'id' => (string) $event->getId(),
                'name' => $event->getTransaction(),
                'trace_id' => $event->getTraceId(),
                'active_thread_id' => self::THREAD_ID,
            ],
            'version' => self::VERSION,
            'profile' => [
                'frames' => $frames,
                'samples' => $samples,
                'stacks' => $stacks,
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

        foreach ($this->excimerLog as $stack) {
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

        return $stacks;
    }

    private function validateExcimerLog(): bool
    {
        if (\is_array($this->excimerLog)) {
            $sampleCount = \count($this->excimerLog);
        } else {
            $sampleCount = $this->excimerLog->count();
        }

        return self::MIN_SAMPLE_COUNT <= $sampleCount;
    }

    private function validateMaxDuration(float $duration): bool
    {
        if ($duration > self::MAX_PROFILE_DURATION) {
            return false;
        }

        return true;
    }

    /**
     * @phpstan-assert-if-true OsContext $osContext
     * @phpstan-assert-if-true !null $osContext->getVersion()
     * @phpstan-assert-if-true !null $osContext->getMachineType()
     */
    private function validateOsContext(?OsContext $osContext): bool
    {
        if (null === $osContext) {
            return false;
        }

        if (null === $osContext->getVersion()) {
            return false;
        }

        if (null === $osContext->getMachineType()) {
            return false;
        }

        return true;
    }

    /**
     * @phpstan-assert-if-true RuntimeContext $runtimeContext
     * @phpstan-assert-if-true !null $runtimeContext->getVersion()
     */
    private function validateRuntimeContext(?RuntimeContext $runtimeContext): bool
    {
        if (null === $runtimeContext) {
            return false;
        }

        if (null === $runtimeContext->getVersion()) {
            return false;
        }

        return true;
    }

    /**
     * @phpstan-assert-if-true !null $event->getTransaction()
     * @phpstan-assert-if-true !null $event->getTraceId()
     */
    private function validateEvent(Event $event): bool
    {
        if (null === $event->getTransaction()) {
            return false;
        }

        if (null === $event->getTraceId()) {
            return false;
        }

        return true;
    }
}
