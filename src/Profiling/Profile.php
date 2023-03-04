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
     * @var int The minimum number of samples required to send a profile
     */
    private const MIN_SAMPLE_COUNT = 2;

    /**
     * @var float|null The start time of the profile
     */
    private $startTime = null;

    /**
     * @var \ExcimerLog|null The data of the profile
     */
    private $excimerLog = null;

    /**
     * @var EventId|null The event ID of the profile
     */
    private $eventId = null;

    public function getStartTime(): ?float
    {
        return $this->startTime;
    }

    public function setStartTime(?float $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function getExcimerLog(): ?\ExcimerLog
    {
        return $this->excimerLog;
    }

    public function setExcimerLog(?\ExcimerLog $excimerLog): void
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
    public function getFormatedData(Event $event): ?array
    {
        if (null === $this->startTime) {
            return null;
        }

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

        foreach ($this->excimerLog as $stackId => $logEntry) {
            $stack = $logEntry->getTrace();

            foreach ($stack as $frame) {
                $file = (string) $frame['file'];

                if (isset($frame['class'], $frame['function'])) {
                    $function = $frame['class'] . '::' . $frame['function'];
                } else {
                    $function = $frame['file'];
                }

                $frames[] = [
                    'function' => $function,
                    'filename' => $file,
                    'lineno' => empty($frame['line']) ? null : (int) $frame['line'],
                ];

                $stacks[$stackId][] = $frameIndex;
                ++$frameIndex;
            }

            $samples[] = [
                'elapsed_since_start_ns' => (int) round($logEntry->getTimestamp() * 1e9, 0),
                'stack_id' => $stackId,
                'thread_id' => self::THREAD_ID,
            ];
        }

        // TODO(michi) This is too hacky
        $startTime = \DateTime::createFromFormat('U.u', (string) $this->startTime);

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
                // TODO(michi)
                // Format needs to be "thread_metadata": { "0": { "name": "main" } }
                // 'thread_metadata' => [
                //     '0' => [
                //         'name' => 'main',
                //     ],
                // ],
            ],
        ];
    }

    /**
     * @phpstan-assert-if-true !null $this->excimerLog $osContext
     */
    private function validateExcimerLog(): bool
    {
        if (null === $this->excimerLog) {
            return false;
        }

        $sampleCount = $this->excimerLog->count();
        if (0 === $sampleCount) {
            return false;
        }

        if (self::MIN_SAMPLE_COUNT > $sampleCount) {
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
