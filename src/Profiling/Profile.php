<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sentry\Context\OsContext;
use Sentry\Context\RuntimeContext;
use Sentry\Event;
use Sentry\EventId;
use Sentry\Options;
use Sentry\Util\SentryUid;

/**
 * Type definition of the Sentry profile format.
 * All fields are none otpional.
 *
 * @see https://develop.sentry.dev/sdk/sample-format/
 *
 * @phpstan-import-type ExcimerLogStackEntry from ProfileDataBuffer
 *
 * @phpstan-type SentryProfileFrame array{
 *     abs_path: string,
 *     filename: string,
 *     function: string,
 *     module: string|null,
 *     lineno: int|null,
 * }
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
 *    platform: 'php',
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
 *        frames: array<int, SentryProfileFrame>,
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

    /**
     * @var Options|null
     */
    private $options;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(?Options $options = null)
    {
        $this->options = $options;
        $this->logger = $options !== null ? $options->getLoggerOrNullLogger() : new NullLogger();
    }

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
            $this->logger->warning('The profile does not contain enough samples, the profile will be discarded.');

            return null;
        }

        $osContext = $event->getOsContext();
        if (!$this->validateOsContext($osContext)) {
            $this->logger->warning('The OS context is not missing or invalid, the profile will be discarded.');

            return null;
        }

        $runtimeContext = $event->getRuntimeContext();
        if (!$this->validateRuntimeContext($runtimeContext)) {
            $this->logger->warning('The runtime context is not missing or invalid, the profile will be discarded.');

            return null;
        }

        if (!$this->validateEvent($event)) {
            $this->logger->warning('The event is missing a transaction and/or trace ID, the profile will be discarded.');

            return null;
        }

        $buffer = new ProfileDataBuffer($this->options);

        foreach ($this->excimerLog as $entry) {
            $buffer->add($entry, ProfileChunk::THREAD_ID, ProfileChunk::THREAD_NAME);
        }

        $profileData = $buffer->takeProfileData();

        $frames = [];

        foreach ($profileData->getFrames() as $frame) {
            $frames[] = [
                'filename' => $frame->getFilename(),
                'abs_path' => $frame->getAbsPath(),
                'module' => $frame->getModule(),
                'function' => $frame->getFunction(),
                'lineno' => $frame->getLineno(),
            ];
        }

        $samples = [];
        $duration = 0;

        foreach ($profileData->getSamples() as $sample) {
            $duration = $sample->getTimestamp();

            $samples[] = [
                'stack_id' => $sample->getStackId(),
                'thread_id' => self::THREAD_ID,
                'elapsed_since_start_ns' => (int) round($duration * 1e+9),
            ];
        }

        if (!$this->validateMaxDuration((float) $duration)) {
            $this->logger->warning(\sprintf('The profile is %ss which is longer than the allowed %ss, the profile will be discarded.', (float) $duration, self::MAX_PROFILE_DURATION));

            return null;
        }

        $startTime = \DateTime::createFromFormat('U.u', number_format($this->startTimeStamp, 4, '.', ''), new \DateTimeZone('UTC'));
        if ($startTime === false) {
            $this->logger->warning(\sprintf('The start time (%s) of the profile is not valid, the profile will be discarded.', $this->startTimeStamp));

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
                'sapi' => $runtimeContext->getSAPI(),
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
                'stacks' => $profileData->getStacks(),
            ],
        ];
    }

    private function validateExcimerLog(): bool
    {
        if (\is_array($this->excimerLog)) {
            $sampleCount = \count($this->excimerLog);
        } else {
            $sampleCount = $this->excimerLog->count();
        }

        return $sampleCount >= self::MIN_SAMPLE_COUNT;
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
        if ($osContext === null) {
            return false;
        }

        if ($osContext->getVersion() === null) {
            return false;
        }

        if ($osContext->getMachineType() === null) {
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
        if ($runtimeContext === null) {
            return false;
        }

        if ($runtimeContext->getVersion() === null) {
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
        if ($event->getTransaction() === null) {
            return false;
        }

        if ($event->getTraceId() === null) {
            return false;
        }

        return true;
    }
}
