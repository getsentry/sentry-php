<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Sentry\Context\OsContext;
use Sentry\Context\RuntimeContext;
use Sentry\Event;
use Sentry\EventId;
use Sentry\Util\SentryUid;

/**
 * Type definition of the Excimer profile format.
 * This format is guranteed to be returned if the extension is loaded.
 *
 * @see https://github.com/wikimedia/mediawiki-php-excimer/blob/c4bd691a505a8349b05999526167e67027a1cc87/excimer_log.c#L455
 * @phpstan-type ExcimerProfile array{
 *     shared: array{
 *         frames: array<int, array{
 *             name: string,
 *             file: string
 *         }>,
 *     },
 *     profiles: array<int, array{
 *        startValue: int,
 *        endValue: int,
 *        samples: array<int, array<int, int>>,
 *        weights: array<int, int>
 *     }>
 * }
 *
 * Type definition of the Sentry profile format.
 * All fields are none otpional.
 *
 * @see https://develop.sentry.dev/sdk/sample-format/
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
 *    transaction?: array{
 *        id: string,
 *        name: string,
 *        trace_id: string,
 *        active_thread_id: string,
 *    },
 *    transactions: array<int, array{
 *        id: string,
 *        name: string,
 *        trace_id: string,
 *        active_thread_id: string,
 *    }>,
 *    version: string,
 *    profile: array{
 *        frames: array<int, array{
 *            function: string,
 *            filename: string,
 *            lineno?: int,
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
     * @var int The maximum duration of a profile in seconds
     */
    private const MAX_PROFILE_DURATION = 30;

    /**
     * @var string|null The start time of the profile
     */
    private $startTime = null;

    /**
     * @var ExcimerProfile|null The data of the profile
     */
    private $data = null;

    /**
     * @var EventId|null The event ID of the profile
     */
    private $eventId = null;

    public function getStartTime(): ?string
    {
        return $this->startTime;
    }

    public function setStartTime(?string $startTime): void
    {
        $this->startTime = $startTime;
    }

    /**
     * @return ExcimerProfile
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @param ExcimerProfile|null $data
     */
    public function setData(?array $data): void
    {
        $this->data = $data;
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
        if (empty($this->data)) {
            return null;
        }

        // If the profile duration is too long (> 30s), we don't send the profile.
        // We convert nanoseconds to seconds by dividing by 1e-9.
        if (($this->data['profiles'][0]['endValue'] * 1e-9) > self::MAX_PROFILE_DURATION) {
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
        foreach ($this->data['shared']['frames'] as $frame) {
            $frames[] = [
                'function' => $frame['name'],
                'filename' => $frame['file'],
            ];
        }

        $stacks = $this->data['profiles'][0]['samples'];
        foreach ($stacks as $key => $stack) {
            // Order stacks from outermost to innermost frame
            $stacks[$key] = array_reverse($stack);
        }

        $time = 0;
        $samples = [];

        $weights = $this->data['profiles'][0]['weights'];
        foreach ($weights as $key => $weight) {
            $time += $weight;
            $samples[] = [
                'elapsed_since_start_ns' => $time,
                'stack_id' => $key,
                'thread_id' => self::THREAD_ID,
            ];
        }

        // If we did not collect enough (>= 2) samples, we don't send the profile.
        if (\count($samples) < self::MIN_SAMPLE_COUNT) {
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
            'environment' => $event->getEnvironment(),
            'runtime' => [
                'name' => $runtimeContext->getName(),
                'version' => $runtimeContext->getVersion(),
            ],
            'timestamp' => $this->startTime,
            'transactions' => [
                [
                    'id' => (string) $event->getId(),
                    'name' => $event->getTransaction(),
                    'trace_id' => (string) $event->getContexts()['trace']['trace_id'],
                    'active_thread_id' => self::THREAD_ID,
                    'relative_start_ns' => 0,
                    'relative_end_ns' => $this->data['profiles'][0]['endValue'],
                ],
            ],
            // 'transaction' => [
            //     'id' => (string) $event->getId(),
            //     'name' => $event->getTransaction(),
            //     'trace_id' => (string) $event->getContexts()['trace']['trace_id'],
            //     'active_thread_id' => self::THREAD_ID,
            // ],
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

    private function validateEvent(Event $event): bool
    {
        if (null === $event->getTransaction()) {
            return false;
        }

        if (empty($event->getContexts()['trace']['trace_id'])) {
            return false;
        }

        return true;
    }
}
