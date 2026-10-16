<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sentry\Event;
use Sentry\Options;
use Sentry\Util\SentryUid;

/**
 * Type definition of the Sentry V2 profile chunk format.
 *
 * @see https://develop.sentry.dev/sdk/telemetry/profiles/#v2-sample-format-payload
 *
 * @phpstan-type SentryProfileChunkFrame array{
 *     abs_path: string,
 *     filename: string,
 *     function: string,
 *     module: string|null,
 *     lineno: int|null,
 * }
 * @phpstan-type SentryProfileChunk array{
 *     version: string,
 *     profiler_id: string,
 *     chunk_id: string,
 *     platform: 'php',
 *     release?: string,
 *     environment: string,
 *     client_sdk: array{
 *         name: string,
 *         version: string,
 *     },
 *     profile: array{
 *         frames: array<int, SentryProfileChunkFrame>,
 *         samples: array<int, array{
 *             stack_id: int,
 *             thread_id: string,
 *             timestamp: float,
 *         }>,
 *         stacks: array<int, array<int, int>>,
 *         thread_metadata: object,
 *     },
 * }
 *
 * @internal
 */
final class ProfileChunk
{
    /**
     * @var string The version of the profile format
     */
    private const VERSION = '2';

    /**
     * @var string
     */
    public const THREAD_ID = '0';

    /**
     * @var string
     */
    public const THREAD_NAME = 'main';

    /**
     * @var int we need at least 2 samples otherwise the event is dropped
     */
    public const MIN_SAMPLE_COUNT = 2;

    /**
     * @var string
     */
    private $profilerId;

    /**
     * @var string
     */
    private $chunkId;

    /**
     * @var float The start time of the profiler as a Unix timestamp with microseconds
     */
    private $startTimestamp;

    /**
     * @var ProfileData The samples of this chunk, timestamped relative to the profiler start
     */
    private $profileData;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(string $profilerId, float $startTimestamp, ProfileData $profileData, ?Options $options = null)
    {
        $this->profilerId = $profilerId;
        $this->chunkId = SentryUid::generate();
        $this->startTimestamp = $startTimestamp;
        $this->profileData = $profileData;
        $this->logger = $options !== null ? $options->getLoggerOrNullLogger() : new NullLogger();
    }

    public function getProfilerId(): string
    {
        return $this->profilerId;
    }

    public function getChunkId(): string
    {
        return $this->chunkId;
    }

    /**
     * @return SentryProfileChunk|null
     */
    public function getFormattedData(Event $event): ?array
    {
        if (\count($this->profileData->getSamples()) < self::MIN_SAMPLE_COUNT) {
            $this->logger->warning('The profile chunk does not contain enough samples, the profile chunk will be discarded.');

            return null;
        }

        $frames = [];

        foreach ($this->profileData->getFrames() as $frame) {
            $frames[] = [
                'filename' => $frame->getFilename(),
                'abs_path' => $frame->getAbsPath(),
                'module' => $frame->getModule(),
                'function' => $frame->getFunction(),
                'lineno' => $frame->getLineno(),
            ];
        }

        $samples = [];
        $threadMetadata = [];

        foreach ($this->profileData->getSamples() as $sample) {
            $samples[] = [
                'stack_id' => $sample->getStackId(),
                'thread_id' => $sample->getThreadId(),
                'timestamp' => $this->startTimestamp + $sample->getTimestamp(),
            ];

            $threadMetadata[$sample->getThreadId()] = [
                'name' => $sample->getThreadName(),
            ];
        }

        $payload = [
            'version' => self::VERSION,
            'profiler_id' => $this->profilerId,
            'chunk_id' => $this->chunkId,
            'platform' => 'php',
            'environment' => $event->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT,
            'client_sdk' => [
                'name' => $event->getSdkIdentifier(),
                'version' => $event->getSdkVersion(),
            ],
            'profile' => [
                'frames' => $frames,
                'samples' => $samples,
                'stacks' => $this->profileData->getStacks(),
                // cast to object to force object serialization
                'thread_metadata' => (object) $threadMetadata,
            ],
        ];

        $release = $event->getRelease();
        if ($release !== null) {
            $payload['release'] = $release;
        }

        return $payload;
    }
}
