<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Sentry\Event;
use Sentry\Util\SentryUid;

/** 
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
 *        frames: array<int, mixed>,
 *        samples: array<int, mixed>,
 *        stacks: array<int, mixed>,
 *    },
 * }
 *
 * @internal
 */
class Profile
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
     * @var string|null The start time of the profile
     */
    private ?string $startTime;

    /**
     * @var ExcimerProfile|null The data of the profile
     */
    private ?array $data;

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

    /**
     * @return SentryProfile|null
     */
    public function getFormatedData(Event $event): ?array
    {
        if (empty($this->data)) {
            return null;
        }
        dd($this->data);

        $frames = $this->data['shared']['frames'];
        foreach ($frames as $key => $frame) {
            $frames[$key]['function'] = $frame['name'];
            $frames[$key]['filename'] = $frame['file'];
        }

        $stacks = $this->data['profiles'][0]['samples'];
        foreach ($stacks as $key => $stack) {
            $stacks[$key] = array_reverse($stack);
        }

        $samples = [];
        $time = 0;

        $weights = $this->data['profiles'][0]['weights'];
        foreach ($weights as $key => $weight) {
            $time += $weight;
            $samples[] = [
                'elapsed_since_start_ns' => $time,
                'stack_id' => $key,
                'thread_id' => self::THREAD_ID,
            ];
        }

        if (\count($samples) < self::MIN_SAMPLE_COUNT) {
            return null;
        }

        $osContext = $event->getOsContext();
        $runtimeContext = $event->getRuntimeContext();

        // If we can't fetch the required data from the OS and runtime context, we don't send the profile
        if (null === $osContext || null === $runtimeContext) {
            return null;
        }

        return [
            'device' => [
                'architecture' => $osContext->getMachineType(),
            ],
            'event_id' => SentryUid::generate(),
            'os' => [
                'name' => $osContext->getName(),
                'version' => $osContext->getVersion(),
                'build_number' => $osContext->getBuild(),
            ],
            'platform' => 'php',
            'release' => $event->getRelease() ?? '',
            'environment' => $event->getEnvironment(),
            'runtime' => [
                'name' => $runtimeContext->getName(),
                'version' => $runtimeContext->getVersion(),
            ],
            'timestamp' => $this->startTime,
            'transaction' => [
                'id' => (string) $event->getId(),
                'name' => $event->getTransaction(),
                'trace_id' => (string) $event->getContexts()['trace']['trace_id'],
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
}
